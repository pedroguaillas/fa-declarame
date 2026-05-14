"""
SRI Scraper HTTP Server — opens a visible browser and waits for scrape
requests from Laravel. All parameters (ruc, password, type, year, month)
come in each POST request — no credentials needed at startup.

Usage:
    # Install dependencies (first time only):
    pip install playwright playwright-stealth requests
    playwright install chromium

    # Start the server (just opens browser, no login yet):
    python server.py
    python server.py --port=8765

    # Laravel sends POST requests to http://localhost:8765/scrape with:
    # { "ruc": "...", "password": "...", "type": "compras", "year": 2026, "month": 5 }
"""

import argparse
import json
import traceback
from http.server import BaseHTTPRequestHandler, HTTPServer
from importlib.util import module_from_spec, spec_from_file_location
from pathlib import Path
from threading import Lock

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
_scrape_lock = Lock()


# ─── Browser Lifecycle ──────────────────────────────────────────────────────


def start_browser(user_data_dir: str | None = None) -> None:
    """Launch the browser visible, ready to receive requests."""

    launch_args = [
        "--no-sandbox",
        "--disable-setuid-sandbox",
        "--disable-dev-shm-usage",
        "--disable-gpu",
        "--disable-blink-features=AutomationControlled",
        "--lang=es-EC,es",
        "--window-size=1366,768",
    ]

    context_opts = {
        "viewport": {"width": 1366, "height": 768},
        "locale": "es-EC",
        "timezone_id": "America/Guayaquil",
        "user_agent": scraper.CHROME_USER_AGENT,
        "accept_downloads": True,
        "extra_http_headers": {
            "Accept-Language": "es-EC,es;q=0.9,en;q=0.8",
            "sec-ch-ua": '"Chromium";v="147", "Google Chrome";v="147", "Not/A)Brand";v="99"',
            "sec-ch-ua-mobile": "?0",
            "sec-ch-ua-platform": '"macOS"',
        },
    }

    if scraper.STEALTH_VERSION == 2:
        stealth = scraper.Stealth(navigator_languages_override=("es-EC", "es"))
        pw_cm = stealth.use_sync(scraper.sync_playwright())
    else:
        pw_cm = scraper.sync_playwright()

    pw = pw_cm.__enter__()
    _browser_state["pw_cm"] = pw_cm
    _browser_state["pw"] = pw

    if user_data_dir:
        scraper.progress("server", f"Contexto persistente: {user_data_dir}")
        Path(user_data_dir).mkdir(parents=True, exist_ok=True)
        context = pw.chromium.launch_persistent_context(
            user_data_dir,
            headless=False,
            args=launch_args,
            **context_opts,
        )
        page = context.pages[0] if context.pages else context.new_page()
    else:
        browser = pw.chromium.launch(headless=False, args=launch_args)
        _browser_state["browser"] = browser
        context = browser.new_context(**context_opts)
        page = context.new_page()

    if scraper.STEALTH_VERSION == 1:
        scraper.stealth_sync(page)

    _browser_state["context"] = context
    _browser_state["page"] = page

    scraper.progress("server", "Navegador abierto. Esperando peticiones...")


def ensure_logged_in(ruc: str, password: str) -> bool:
    """Login or re-login if needed. Handles switching between different RUCs."""
    page = _browser_state["page"]

    # Different RUC than current session — need fresh login
    if _browser_state["logged_in_ruc"] and _browser_state["logged_in_ruc"] != ruc:
        scraper.progress(
            "server", f"Cambiando de RUC {_browser_state['logged_in_ruc']} → {ruc}"
        )
        _browser_state["logged_in_ruc"] = None

    # Already logged in with this RUC — check if session is still valid
    if _browser_state["logged_in_ruc"] == ruc:
        try:
            current_url = page.url
            # Navigate to portal to verify session is actually alive
            page.goto(scraper.SRI_URLS["portal"], wait_until="networkidle", timeout=30000)
            current_url = page.url
            if "/auth/" not in current_url and "about:blank" not in current_url:
                scraper.progress("server", "Sesion activa verificada")
                return True
            scraper.progress("server", "Sesion expirada, re-logueando...")
        except Exception:
            scraper.progress("server", "Sesion perdida, re-logueando...")

    # Login
    logged_in = scraper.login(page, ruc, password)
    if logged_in:
        _browser_state["logged_in_ruc"] = ruc
        scraper.progress("server", f"Login exitoso para RUC {ruc}")
    else:
        _browser_state["logged_in_ruc"] = None

    return logged_in


# ─── Scrape Handler ──────────────────────────────────────────────────────────


def handle_scrape(config: dict) -> dict:
    """Execute a scrape request using the open browser."""
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
    api_key = config.get("apiKey")
    tipo = config.get("type", "compras")
    year = config.get("year", 2026)
    month = config.get("month", 5)
    mode = config.get("mode", "txt_download")
    download_dir = Path(config.get("downloadDir", "/tmp/sri-scrape-py"))
    download_dir.mkdir(parents=True, exist_ok=True)

    if not ensure_logged_in(ruc, password):
        return {
            "event": "error",
            "data": {
                "code": "LOGIN_FAILED",
                "message": "No se pudo iniciar sesion en SRI",
            },
        }

    scraper.navigate_to_comprobantes(page, tipo)

    if mode == "txt_download":
        files = []
        selected_values = set(config.get("voucherTypes") or ["1", "3", "4"])
        base_types = scraper.COMPRAS_VOUCHER_TYPES if tipo == "compras" else scraper.VOUCHER_TYPES
        voucher_types = [vt for vt in base_types if vt["value"] in selected_values]
        for i, vt in enumerate(voucher_types):
            if i > 0:
                scraper.navigate_to_comprobantes(page, tipo)
            try:
                if tipo == "ventas":
                    # Emitidos: consultar día por día (TEST: solo 10 días)
                    result = scraper.download_for_voucher_type_by_day(
                        page, vt, year, month, download_dir, api_key, tipo
                    )
                else:
                    # Recibidos: consultar todo el mes
                    result = scraper.download_for_voucher_type(
                        page, vt, year, month, download_dir, api_key, tipo
                    )
                files.append(result)
                content_len = len(result.get("content") or "")
                scraper.progress(
                    "summary",
                    f"{vt['label']}: status={result['status']}, content={content_len} bytes, rows={result.get('rows', 0)}",
                )
            except Exception as e:
                scraper.progress(vt["label"], f"Error: {e}")
                files.append(
                    {
                        "type": vt["label"],
                        "status": "error",
                        "content": None,
                        "error": str(e),
                    }
                )
            scraper.random_delay(1, 3)

        # Log resumen antes de enviar a Laravel
        scraper.progress("response", f"Enviando {len(files)} archivos a Laravel")
        for f in files:
            has_content = "SI" if f.get("content") else "NO"
            xml_count = len(f.get("xmls") or [])
            modal_count = len(f.get("modal_entries") or [])
            ret_modal_count = len(f.get("retention_modal_entries") or [])
            scraper.progress(
                "response",
                f"  {f.get('type')}: status={f['status']}, contenido={has_content}, bytes={len(f.get('content') or '')}, xmls={xml_count}, modales={modal_count}, ret_modales={ret_modal_count}",
            )

        return {"event": "result", "data": {"mode": "txt_download", "files": files}}

    elif mode == "table_scrape":
        all_claves = []
        selected_values = set(config.get("voucherTypes") or ["1", "3", "4"])
        base_types = scraper.COMPRAS_VOUCHER_TYPES if tipo == "compras" else scraper.VOUCHER_TYPES
        active_voucher_types = [vt for vt in base_types if vt["value"] in selected_values]
        for i, vt in enumerate(active_voucher_types):
            if i > 0:
                scraper.navigate_to_comprobantes(page, tipo)
            try:
                search_ok = scraper.search_with_captcha(
                    page, vt, year, month, api_key, tipo
                )
                if search_ok:
                    claves = scraper.scrape_table_data(page, tipo)
                    all_claves.extend(claves)
            except Exception as e:
                scraper.progress(vt["label"], f"Error: {e}")
            scraper.random_delay(1, 3)

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
    def do_GET(self):
        if self.path == "/health":
            self._json_response(
                200,
                {
                    "status": "ok",
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

        acquired = _scrape_lock.acquire(blocking=False)
        if not acquired:
            self._json_response(409, {"error": "Scrape en progreso. Intente de nuevo."})
            return

        try:
            scraper.progress(
                "server",
                f"Peticion recibida: ruc={config.get('ruc')}, type={config.get('type')}, year={config.get('year')}, month={config.get('month')}",
            )
            result = handle_scrape(config)
            self._json_response(200, result)
        except Exception as e:
            traceback.print_exc()
            self._json_response(
                500,
                {"event": "error", "data": {"code": "SERVER_ERROR", "message": str(e)}},
            )
        finally:
            _scrape_lock.release()

    def _json_response(self, status: int, data: dict):
        body = json.dumps(data, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
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
    args = parser.parse_args()

    scraper.progress(
        "server",
        f"Stealth: {'v' + str(scraper.STEALTH_VERSION) if scraper.STEALTH_VERSION else 'NO'}",
    )
    scraper.progress("server", "Iniciando navegador visible...")

    start_browser(user_data_dir=args.user_data_dir)

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
        _browser_state["context"].close()
        if _browser_state.get("browser"):
            _browser_state["browser"].close()


if __name__ == "__main__":
    main()
