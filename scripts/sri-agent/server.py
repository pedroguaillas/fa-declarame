from __future__ import annotations

"""
SRI Scraper HTTP Server — opens Chrome only when a scrape job arrives,
then closes it when the job finishes.

Browser lifecycle: Chrome is closed at rest. When a job arrives it launches,
logs in to SRI, scrapes, sends callback, and closes. The next job opens a
fresh browser.

Usage:
    # Install dependencies (first time only):
    pip install playwright playwright-stealth requests
    playwright install chromium

    # Start the agent:
    python server.py
    python server.py --port=8765

    # Laravel sends POST requests to http://localhost:8765/scrape with:
    # { "ruc": "...", "password": "...", "type": "compras", "year": 2026, "month": 5 }
"""

import argparse
import io
import json
import os
import queue
import random
import sys
import threading
import time
import traceback
import urllib.request
from concurrent.futures import Future
from http.server import BaseHTTPRequestHandler, HTTPServer
from importlib.util import module_from_spec, spec_from_file_location
from pathlib import Path
from threading import Event, Lock

# Windows CP1252 stdout breaks on Spanish characters (ñ, á, etc.) from SRI.
# Force UTF-8 before any output so scraping never crashes mid-job.
if sys.platform == "win32":
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    if hasattr(sys.stderr, "reconfigure"):
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")

AGENT_VERSION = "1.0.0"

# ─── Load test-scraper.py as module ──────────────────────────────────────────

_spec = spec_from_file_location("scraper", Path(__file__).parent / "test-scraper.py")
scraper = module_from_spec(_spec)
_spec.loader.exec_module(scraper)

# ─── Globals ─────────────────────────────────────────────────────────────────

_browser_state = {
    "pw": None,
    "pw_cm": None,
    "context": None,
    "browser": None,
    "page": None,
    "logged_in_ruc": None,  # RUC of currently logged-in session
}

# Queue for passing work to the scraper thread. Items: (config: dict, future: Future).
_work_queue: queue.Queue = queue.Queue()

# Signals that the browser is initialized and ready to accept work.
_browser_ready = Event()

# Prevents concurrent scrape requests.
_scrape_lock = Lock()

# Tracks how many jobs have completed to apply incremental cooldowns.
_job_counter = 0

# Minutos sin jobs tras los cuales el navegador se cierra solo (keep-alive).
# Mientras vive, la sesión reCAPTCHA queda caliente → captcha pasa a la primera.
IDLE_TIMEOUT_S = 900  # 15 min

# Dominios cuyas cookies se preservan entre jobs para mantener el score reCAPTCHA v3.
GOOGLE_COOKIE_DOMAINS = ("google.com", "gstatic.com", "googleapis.com", "recaptcha.net")


# ─── Browser Close ────────────────────────────────────────────────────────────


def _close_browser() -> None:
    """Close the browser and reset all state. Safe to call even if not open."""
    try:
        if _browser_state["context"]:
            _browser_state["context"].close()
    except Exception as e:
        scraper.progress("server", f"Advertencia al cerrar context: {e}")
    try:
        if _browser_state["browser"]:
            _browser_state["browser"].close()
    except Exception as e:
        scraper.progress("server", f"Advertencia al cerrar browser: {e}")
    try:
        if _browser_state["pw_cm"]:
            _browser_state["pw_cm"].__exit__(None, None, None)
    except Exception as e:
        scraper.progress("server", f"Advertencia al cerrar playwright: {e}")

    _browser_state["pw"] = None
    _browser_state["pw_cm"] = None
    _browser_state["context"] = None
    _browser_state["browser"] = None
    _browser_state["page"] = None
    _browser_state["logged_in_ruc"] = None
    scraper.progress("server", "Navegador cerrado.")


def _browser_is_open() -> bool:
    return _browser_state["context"] is not None


# ─── Cookie Persistence (reCAPTCHA warmth) ────────────────────────────────────


def _recaptcha_cookie_path(user_data_dir: str | None) -> Path:
    base = Path(user_data_dir) if user_data_dir else Path(__file__).parent
    return base / "recaptcha_cookies.json"


def _filter_google_cookies(cookies: list[dict]) -> list[dict]:
    return [
        c
        for c in cookies
        if any(c.get("domain", "").lstrip(".").endswith(d) for d in GOOGLE_COOKIE_DOMAINS)
    ]


def _save_recaptcha_cookies(user_data_dir: str | None) -> None:
    """Volcar cookies Google/reCAPTCHA a JSON antes de cerrar el navegador."""
    ctx = _browser_state["context"]
    if not ctx:
        return
    try:
        google = _filter_google_cookies(ctx.cookies())
        _recaptcha_cookie_path(user_data_dir).write_text(
            json.dumps(google), encoding="utf-8"
        )
        scraper.progress("server", f"Guardadas {len(google)} cookies Google/reCAPTCHA.")
    except Exception as e:
        scraper.progress("server", f"Advertencia guardando cookies: {e}")


def _restore_recaptcha_cookies(user_data_dir: str | None) -> None:
    """Restaurar cookies Google/reCAPTCHA al abrir un navegador fresco."""
    ctx = _browser_state["context"]
    path = _recaptcha_cookie_path(user_data_dir)
    if not ctx or not path.exists():
        return
    try:
        cookies = json.loads(path.read_text(encoding="utf-8"))
        if cookies:
            ctx.add_cookies(cookies)
            scraper.progress(
                "server", f"Restauradas {len(cookies)} cookies Google/reCAPTCHA."
            )
    except Exception as e:
        scraper.progress("server", f"Advertencia restaurando cookies: {e}")


def _clear_sri_session() -> None:
    """Limpiar sesión SRI/Keycloak entre jobs, preservando cookies Google/reCAPTCHA.

    Mantiene el navegador vivo y caliente para el siguiente job sin filtrar el
    login de un RUC al siguiente."""
    ctx = _browser_state["context"]
    page = _browser_state["page"]
    if not ctx:
        return
    try:
        google = _filter_google_cookies(ctx.cookies())
        ctx.clear_cookies()
        if google:
            ctx.add_cookies(google)
        if page:
            try:
                page.goto("about:blank", wait_until="commit", timeout=10000)
                page.evaluate(
                    "() => { try { localStorage.clear(); sessionStorage.clear(); } catch(e){} }"
                )
            except Exception:
                pass
        _browser_state["logged_in_ruc"] = None
        scraper.progress(
            "server", f"Sesión SRI limpiada ({len(google)} cookies Google preservadas)."
        )
    except Exception as e:
        scraper.progress("server", f"Advertencia limpiando sesión SRI: {e}")


# ─── Auto-Update ─────────────────────────────────────────────────────────────


def _check_for_updates(update_url: str) -> None:
    """Check remote version.json; if newer, download updated .py files and restart."""
    try:
        scraper.progress("update", f"Verificando actualizaciones en {update_url} ...")
        req = urllib.request.Request(
            update_url.rstrip("/") + "/version.json",
            headers={"User-Agent": f"SRI-Agent/{AGENT_VERSION}"},
        )
        resp = urllib.request.urlopen(req, timeout=10)
        data = json.loads(resp.read())
        remote_version = data.get("version", "")
        if not remote_version or remote_version == AGENT_VERSION:
            scraper.progress("update", f"Versión {AGENT_VERSION} al día.")
            return
        scraper.progress("update", f"Nueva versión {remote_version} disponible. Actualizando...")
        agent_dir = Path(__file__).parent
        base_url = update_url.rstrip("/")
        for filename in ("server.py", "test-scraper.py"):
            dest = agent_dir / filename
            urllib.request.urlretrieve(f"{base_url}/{filename}", dest)
            scraper.progress("update", f"  {filename} actualizado.")
        scraper.progress("update", "Reiniciando agente con nueva versión...")
        os.execv(sys.executable, [sys.executable] + sys.argv)
    except Exception as e:
        scraper.progress("update", f"Advertencia: no se pudo verificar actualización: {e}")


# ─── Browser Lifecycle ──────────────────────────────────────────────────────


def start_browser(user_data_dir: str | None = None, headless: bool = False, channel: str = "chromium") -> None:
    """Launch the browser (headless or visible), ready to receive requests.
    Must be called from the scraper thread — Playwright is not thread-safe.

    channel="chrome"    — use the real Chrome installed on the system (best reCAPTCHA score)
    channel="chromium"  — use Playwright's bundled Chromium (required when Chrome not installed)
    """
    use_real_chrome = channel != "chromium"

    launch_args = [
        "--no-sandbox",
        "--disable-setuid-sandbox",
        "--disable-dev-shm-usage",
        "--disable-blink-features=AutomationControlled",
        "--lang=es-EC,es",
        "--window-size=1366,768",
        "--window-position=0,0",
    ]

    # Disable GPU on Linux headless servers — no real GPU, Chrome crashes without this.
    # On macOS with real Chrome leave GPU enabled for authentic fingerprint signals.
    if not use_real_chrome or headless:
        launch_args.append("--disable-gpu")
        launch_args.append("--disable-software-rasterizer")

    if headless:
        # Use Chrome's new headless mode — shares the same rendering engine as the
        # visible browser, making it far harder to detect than the classic headless.
        launch_args.append("--headless=new")

    # playwright-stealth wrapper: only apply for Playwright Chromium.
    # Real Chrome already has authentic fingerprints — stealth patches would conflict.
    if scraper.STEALTH_VERSION == 2 and not use_real_chrome:
        stealth = scraper.Stealth(navigator_languages_override=("es-419", "es"))
        pw_cm = stealth.use_sync(scraper.sync_playwright())
    else:
        pw_cm = scraper.sync_playwright()

    pw = pw_cm.__enter__()
    _browser_state["pw_cm"] = pw_cm
    _browser_state["pw"] = pw

    context_opts: dict = {
        "viewport": {"width": 1366, "height": 768},
        "locale": "es-419",
        "timezone_id": "America/Guayaquil",
        "accept_downloads": True,
        "extra_http_headers": {
            "Accept-Language": "es-419,es;q=0.9",
        },
    }

    if use_real_chrome:
        # Real Chrome reports its own authentic UA, sec-ch-ua, and all fingerprint
        # signals natively — overriding them would create detectable mismatches.
        scraper.progress("server", f"Chrome real ({channel}) — fingerprints nativos")
        fp = None
    else:
        # Playwright Chromium needs spoofed fingerprints: its bundled version lags
        # stable Chrome and reCAPTCHA penalizes the version mismatch.
        fp = scraper.build_chrome_fingerprint(scraper.fetch_latest_chrome_version())
        scraper.progress("server", f"Fingerprint Chrome {fp['full_version']} (última estable)")
        context_opts["user_agent"] = fp["user_agent"]
        context_opts["extra_http_headers"].update({
            "sec-ch-ua": fp["sec_ch_ua"],
            "sec-ch-ua-mobile": "?0",
            "sec-ch-ua-platform": fp["platform"],
            "sec-ch-ua-full-version-list": fp["sec_ch_ua_full"],
        })

    # When using --headless=new we pass headless=False to Playwright so it does not
    # add its own --headless flag (which would downgrade to the detectable old mode).
    pw_headless = False  # always False; new headless via arg above

    launch_kwargs = {"channel": channel} if use_real_chrome else {}

    if user_data_dir:
        scraper.progress("server", f"Contexto persistente: {user_data_dir}")
        Path(user_data_dir).mkdir(parents=True, exist_ok=True)
        # Clear Chromium Singleton locks left by any previous crashed session
        for lock in Path(user_data_dir).glob("Singleton*"):
            try:
                lock.unlink()
            except Exception:
                pass
        context = pw.chromium.launch_persistent_context(
            user_data_dir,
            headless=pw_headless,
            args=launch_args,
            **launch_kwargs,
            **context_opts,
        )
        page = context.pages[0] if context.pages else context.new_page()
    else:
        browser = pw.chromium.launch(headless=pw_headless, args=launch_args, **launch_kwargs)
        _browser_state["browser"] = browser
        context = browser.new_context(**context_opts)
        page = context.new_page()

    if use_real_chrome:
        # Only patch webdriver flag — real Chrome already has correct native fingerprints.
        context.add_init_script(
            "Object.defineProperty(navigator, 'webdriver', { get: () => undefined });"
        )
    else:
        # Full stealth init: patches userAgentData, plugins, permissions, etc.
        context.add_init_script(scraper.build_stealth_init_script(fp))
        if scraper.STEALTH_VERSION == 1:
            scraper.stealth_sync(page)

    _browser_state["context"] = context
    _browser_state["page"] = page

    scraper.progress("server", "Navegador abierto. Esperando peticiones...")


def ensure_logged_in(ruc: str, password: str) -> bool:
    """Login or re-login if needed. Handles persistent context with saved cookies."""
    page = _browser_state["page"]

    # Different RUC — clear cookies to force a clean login
    if _browser_state["logged_in_ruc"] and _browser_state["logged_in_ruc"] != ruc:
        scraper.progress(
            "server", f"Cambiando de RUC {_browser_state['logged_in_ruc']} → {ruc}, limpiando sesion..."
        )
        try:
            _browser_state["context"].clear_cookies()
        except Exception as e:
            scraper.progress("server", f"Advertencia al limpiar cookies: {e}")
        _browser_state["logged_in_ruc"] = None

    # Google warmup: navigate briefly to google.com before SRI so reCAPTCHA sees
    # prior same-session Google interaction (boosts v3 score). Also tests whether
    # Google account cookies are accessible to this Playwright-controlled session.
    try:
        page.goto("https://www.google.com", wait_until="domcontentloaded", timeout=15000)
        # Check if Google account is accessible (cookie encryption test)
        google_logged_in = page.evaluate(
            "() => !!document.querySelector('a[aria-label*=\"Google Account\"], "
            "a[aria-label*=\"Cuenta de Google\"], #gb_70, [data-ogsr-up]')"
        )
        scraper.progress(
            "server",
            f"Google warmup: cuenta={'SI' if google_logged_in else 'NO (sin cuenta o cookies no accesibles)'}",
        )
        time.sleep(random.uniform(8, 12))
    except Exception as e:
        scraper.progress("server", f"Google warmup: error ({e}), continuando...")

    # Always verify session first — covers both in-memory flag and persistent
    # context cookies saved from a previous run (open-on-demand restores cookies
    # from user-data-dir, so the browser may already be logged in without us knowing).
    try:
        page.goto(scraper.SRI_URLS["portal"], wait_until="networkidle", timeout=30000)
        current_url = page.url
        if "/auth/" not in current_url and "about:blank" not in current_url:
            scraper.progress("server", f"Sesion activa detectada para RUC {ruc}")
            _browser_state["logged_in_ruc"] = ruc
            return True
        scraper.progress("server", "Sin sesion activa, logueando...")
    except Exception as e:
        scraper.progress("server", f"Error verificando sesion ({e}), logueando...")

    # Login
    logged_in = scraper.login(page, ruc, password)
    if logged_in:
        _browser_state["logged_in_ruc"] = ruc
        scraper.progress("server", f"Login exitoso para RUC {ruc}")
    else:
        _browser_state["logged_in_ruc"] = None

    return logged_in


# ─── Scraper Thread ───────────────────────────────────────────────────────────


def _scraper_thread_main(
    user_data_dir: str | None,
    headless: bool = False,
    channel: str = "chromium",
    idle_timeout_s: int = IDLE_TIMEOUT_S,
) -> None:
    """Dedicated thread that owns the browser and processes all scrape work.

    Keep-alive con idle timeout: el navegador se abre con el primer job y se
    mantiene VIVO entre jobs (sesión reCAPTCHA caliente → captcha pasa a la
    primera). Tras `idle_timeout_s` segundos sin jobs se cierra solo, así en
    días sin uso no queda ninguna ventana abierta molestando al usuario.

    Playwright's sync API uses greenlets and is NOT thread-safe — all browser
    operations must happen in this single thread. The HTTP handlers communicate
    via _work_queue using concurrent.futures.Future objects.
    """
    _browser_ready.set()  # Ready to accept requests immediately (no pre-launch)
    global _job_counter

    while True:
        try:
            work = _work_queue.get(timeout=idle_timeout_s)
        except queue.Empty:
            # Sin jobs durante el timeout → cerrar navegador preservando cookies.
            if _browser_is_open():
                scraper.progress(
                    "server",
                    f"Sin jobs {idle_timeout_s // 60} min → cerrando navegador (cookies reCAPTCHA preservadas).",
                )
                _save_recaptcha_cookies(user_data_dir)
                _close_browser()
            continue

        if work is None:
            break  # Shutdown signal
        config, future = work

        job_ok = False
        try:
            if not _browser_is_open():
                browser_label = "Chrome real" if channel != "chromium" else "Chromium"
                scraper.progress("server", f"Abriendo {browser_label} para este job...")
                start_browser(user_data_dir, headless=headless, channel=channel)
                _restore_recaptcha_cookies(user_data_dir)
            else:
                scraper.progress("server", "Reusando navegador caliente (sesión viva).")
            result = handle_scrape(config)
            future.set_result(result)
            job_ok = True
        except Exception as e:
            traceback.print_exc()
            future.set_exception(e)
        finally:
            _job_counter += 1

        if job_ok:
            # Mantener navegador vivo; limpiar solo sesión SRI para el próximo RUC.
            _clear_sri_session()
        else:
            # Error → recuperar estado limpio para el próximo job, guardando cookies.
            _save_recaptcha_cookies(user_data_dir)
            _close_browser()

        # Brief pause between consecutive queued jobs.
        if _work_queue.empty():
            continue
        cooldown = random.uniform(3, 8)
        scraper.progress(
            "server",
            f"Pausa {cooldown:.0f}s antes del siguiente job (job #{_job_counter})...",
        )
        time.sleep(cooldown)


# ─── Scrape Handler ──────────────────────────────────────────────────────────


def _post_callback(url: str, body: dict) -> None:
    """POST a JSON body to the callback URL. Silently ignores errors."""
    try:
        data = json.dumps(body, ensure_ascii=False).encode("utf-8")
        req = urllib.request.Request(
            url, data=data,
            headers={"Content-Type": "application/json; charset=utf-8"},
            method="POST",
        )
        urllib.request.urlopen(req, timeout=300)
    except Exception as e:
        scraper.progress("callback", f"Error al enviar callback: {e}")


def handle_scrape(config: dict) -> dict:
    """Execute a scrape request using the open browser.
    Must only be called from the scraper thread."""
    ruc = config.get("ruc")
    password = config.get("password")

    if not ruc or not password:
        return {
            "event": "error",
            "data": {
                "code": "MISSING_CREDENTIALS",
                "message": "ruc y password son requeridos",
            },
        }

    page = _browser_state["page"]
    tipo = config.get("type", "compras")
    year = config.get("year", 2026)
    month = config.get("month", 5)
    # Descarga semestral: lista de meses en una sola sesión (fallback: mes único)
    months = config.get("months") or [month]
    day = config.get("day") or 0  # 0 = todos los días, 1-31 = día específico
    mode = config.get("mode", "txt_download")
    download_dir = Path(config.get("downloadDir", "/tmp/sri-scrape-py"))
    skip_claves = set(config.get("skipClaves") or [])
    download_dir.mkdir(parents=True, exist_ok=True)

    if not ensure_logged_in(ruc, password):
        return {
            "event": "error",
            "data": {
                "code": "LOGIN_FAILED",
                "message": "No se pudo iniciar sesion en SRI",
            },
        }

    # 'ambos' navigates per section inside the mode handlers below
    if tipo != "ambos":
        scraper.navigate_to_comprobantes(page, tipo)

    if mode == "txt_download":
        files = []
        selected_values = set(config.get("voucherTypes") or ["1", "3", "4"])
        sections = ["compras", "ventas"] if tipo == "ambos" else [tipo]

        for month_index, current_month in enumerate(months):
            if len(months) > 1:
                scraper.progress(
                    "mes",
                    f"Mes {month_index + 1}/{len(months)}: {current_month:02d}/{year}",
                )

            for section in sections:
                if tipo == "ambos":
                    scraper.navigate_to_comprobantes(page, section)

                base_types = scraper.COMPRAS_VOUCHER_TYPES if section == "compras" else scraper.VOUCHER_TYPES
                voucher_types = [vt for vt in base_types if vt["value"] in selected_values]

                # ventas mes completo: día-por-día (el portal solo expone un día a la vez)
                use_day_by_day = (day == 0 and section == "ventas")

                day_str = f"día {day}" if day > 0 else "mes completo"
                scraper.progress(
                    "server",
                    f"[{section}] Estrategia: {'dia-por-dia' if use_day_by_day else day_str} "
                    f"(año={year}, mes={current_month})",
                )

                for i, vt in enumerate(voucher_types):
                    try:
                        if use_day_by_day:
                            result = scraper.download_for_voucher_type_by_day(
                                page, vt, year, current_month, download_dir, section,
                                skip_claves=skip_claves,
                            )
                        else:
                            result = scraper.download_for_voucher_type(
                                page, vt, year, current_month, download_dir, section,
                                day=day,
                                skip_claves=skip_claves,
                            )
                        result["section"] = section
                        result["month"] = current_month
                        files.append(result)
                        content_len = len(result.get("content") or "")
                        scraper.progress(
                            "summary",
                            f"[{section}] {current_month:02d}/{year} {vt['label']}: status={result['status']}, content={content_len} bytes, rows={result.get('rows', 0)}",
                        )
                    except Exception as e:
                        scraper.progress(vt["label"], f"[{section}] Error: {e}")
                        files.append(
                            {
                                "type": vt["label"],
                                "section": section,
                                "month": current_month,
                                "status": "error",
                                "content": None,
                                "error": str(e),
                            }
                        )
                    scraper.random_delay(0.2, 0.5)

        # Log resumen antes de enviar a Laravel
        scraper.progress("response", f"Enviando {len(files)} archivos a Laravel")
        for f in files:
            has_content = "SI" if f.get("content") else "NO"
            xml_count = len(f.get("xmls") or [])
            modal_count = len(f.get("modal_entries") or [])
            ret_modal_count = len(f.get("retention_modal_entries") or [])
            scraper.progress(
                "response",
                f"  [{f.get('section', '?')}] {f.get('type')}: status={f['status']}, "
                f"contenido={has_content}, bytes={len(f.get('content') or '')}, "
                f"xmls={xml_count}, modales={modal_count}, ret_modales={ret_modal_count}",
            )

        return {"event": "result", "data": {"mode": "txt_download", "files": files}}

    elif mode == "table_scrape":
        all_claves = []
        selected_values = set(config.get("voucherTypes") or ["1", "3", "4"])
        sections = ["compras", "ventas"] if tipo == "ambos" else [tipo]

        for section in sections:
            if tipo == "ambos":
                scraper.navigate_to_comprobantes(page, section)

            base_types = scraper.COMPRAS_VOUCHER_TYPES if section == "compras" else scraper.VOUCHER_TYPES
            active_voucher_types = [vt for vt in base_types if vt["value"] in selected_values]

            for i, vt in enumerate(active_voucher_types):
                if i > 0:
                    scraper.navigate_to_comprobantes(page, section)
                try:
                    search_ok = scraper.search_with_captcha(page, vt, year, month, section)
                    if search_ok:
                        claves = scraper.scrape_table_data(page, section)
                        all_claves.extend(claves)
                except Exception as e:
                    scraper.progress(vt["label"], f"Error: {e}")
                scraper.random_delay(0.2, 0.5)

        unique_claves = list(dict.fromkeys(all_claves))
        return {
            "event": "result",
            "data": {"mode": "table_scrape", "clavesAcceso": unique_claves},
        }

    return {
        "event": "error",
        "data": {"code": "INVALID_MODE", "message": f"Modo no soportado: {mode}"},
    }


# ─── HTTP Server ─────────────────────────────────────────────────────────────


class ScrapeRequestHandler(BaseHTTPRequestHandler):
    def do_OPTIONS(self):
        """Handle CORS preflight from browser (fetch from HTTPS page → localhost)."""
        self.send_response(204)
        self._add_cors_headers()
        self.end_headers()

    def do_GET(self):
        if self.path == "/health":
            self._json_response(
                200,
                {
                    "status": "ok",
                    "version": AGENT_VERSION,
                    "logged_in_ruc": _browser_state["logged_in_ruc"],
                },
            )
        else:
            self._json_response(404, {"error": "Not found"})

    def do_POST(self):
        if self.path != "/scrape":
            self._json_response(404, {"error": "Not found"})
            return

        content_length = int(self.headers.get("Content-Length", 0))
        body = self.rfile.read(content_length)

        try:
            config = json.loads(body) if body else {}
        except json.JSONDecodeError:
            self._json_response(400, {"error": "Invalid JSON"})
            return

        scraper.progress(
            "server",
            f"Peticion recibida: ruc={config.get('ruc')}, type={config.get('type')}, "
            f"year={config.get('year')}, month={config.get('month')}, day={config.get('day') or 'todos'}",
        )

        callback_url = config.get("callbackUrl")
        future: Future = Future()

        if callback_url:
            # Async mode: always queue — the single scraper thread serializes execution
            # naturally. Never reject with 409; multiple companies can queue up safely.
            def _on_done(f: Future) -> None:
                try:
                    _post_callback(callback_url, f.result())
                except Exception as e:
                    _post_callback(callback_url, {
                        "event": "error",
                        "data": {"code": "SCRAPE_ERROR", "message": str(e)},
                    })

            future.add_done_callback(_on_done)
            _work_queue.put((config, future))
            self._json_response(200, {"status": "accepted"})
            return

        # Sync mode (no callbackUrl): reject if already busy to avoid blocking
        # the HTTP handler indefinitely while the queue drains.
        acquired = _scrape_lock.acquire(blocking=False)
        if not acquired:
            self._json_response(409, {"error": "Scrape en progreso. Intente de nuevo."})
            return

        _work_queue.put((config, future))
        try:
            result = future.result(timeout=600)
            self._json_response(200, result)
        except Exception as e:
            traceback.print_exc()
            self._json_response(
                500,
                {"event": "error", "data": {"code": "SERVER_ERROR", "message": str(e)}},
            )
        finally:
            _scrape_lock.release()

    def _add_cors_headers(self) -> None:
        # Localhost only binds to 127.0.0.1 so wildcard origin is safe here.
        self.send_header("Access-Control-Allow-Origin", "*")
        self.send_header("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
        self.send_header("Access-Control-Allow-Headers", "Content-Type")

    def _json_response(self, status: int, data: dict):
        body = json.dumps(data, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self._add_cors_headers()
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, format, *args):
        scraper.log("http", f"{self.client_address[0]} {args[0]}")


# ─── Main ────────────────────────────────────────────────────────────────────


def main():
    parser = argparse.ArgumentParser(description="SRI Scraper HTTP Server")
    parser.add_argument(
        "--port", type=int, default=8765, help="Puerto del servidor (default: 8765)"
    )
    parser.add_argument("--host", default="127.0.0.1", help="Host (default: 127.0.0.1)")
    parser.add_argument(
        "--user-data-dir",
        dest="user_data_dir",
        default=None,
        help="Directorio para persistir sesion del navegador",
    )
    parser.add_argument(
        "--headless",
        action="store_true",
        default=False,
        help="Ejecutar el navegador en modo headless (sin ventana)",
    )
    parser.add_argument(
        "--update-url",
        dest="update_url",
        default=None,
        help="URL base donde se publican server.py, test-scraper.py y version.json para auto-update",
    )
    parser.add_argument(
        "--no-update",
        dest="no_update",
        action="store_true",
        default=False,
        help="Saltar verificación de actualizaciones al arrancar",
    )
    import sys as _sys
    _default_channel = "chrome" if _sys.platform == "darwin" else "chromium"
    parser.add_argument(
        "--channel",
        default=_default_channel,
        help="Canal del navegador: 'chrome' para Chrome real del sistema (default en macOS), "
             "'chromium' para Playwright Chromium (default en Linux/VPS)",
    )
    parser.add_argument(
        "--idle-timeout",
        dest="idle_timeout",
        type=int,
        default=IDLE_TIMEOUT_S,
        help=f"Segundos sin jobs antes de cerrar el navegador (default: {IDLE_TIMEOUT_S})",
    )
    args = parser.parse_args()

    scraper.progress("server", f"SRI Agent v{AGENT_VERSION}")
    scraper.progress(
        "server",
        f"Stealth: {'v' + str(scraper.STEALTH_VERSION) if scraper.STEALTH_VERSION else 'NO'}",
    )

    if args.update_url and not args.no_update:
        _check_for_updates(args.update_url)

    channel_label = f"Chrome real ({args.channel})" if args.channel != "chromium" else "Playwright Chromium"
    scraper.progress(
        "server",
        f"Agente listo. {channel_label} abre con el primer job y se mantiene vivo "
        f"{args.idle_timeout // 60} min tras el último ({'headless' if args.headless else 'visible'}).",
    )

    # Start the dedicated scraper thread — it owns the browser and all Playwright calls.
    scraper_thread = threading.Thread(
        target=_scraper_thread_main,
        args=(args.user_data_dir, args.headless, args.channel, args.idle_timeout),
        daemon=True,
    )
    scraper_thread.start()

    # Wait for the browser to be ready before accepting HTTP requests.
    _browser_ready.wait()

    server = HTTPServer((args.host, args.port), ScrapeRequestHandler)
    scraper.progress("server", f"Servidor escuchando en http://{args.host}:{args.port}")
    scraper.progress(
        "server", "POST /scrape  — enviar { ruc, password, type, year, month, mode }"
    )
    scraper.progress("server", "GET  /health  — estado del servidor")

    try:
        server.serve_forever()
    except KeyboardInterrupt:
        scraper.progress("server", "Apagando servidor...")
        server.shutdown()
        _work_queue.put(None)  # Signal scraper thread to stop
        _close_browser()


if __name__ == "__main__":
    main()
