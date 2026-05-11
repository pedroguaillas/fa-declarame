"""
SRI Scraper Test Script (Python + Playwright) — 3-Layer Strategy

Layer 1: Stealth (playwright-stealth) — make browser undetectable
Layer 2: Human behavior simulation — mouse, scroll, timing
Layer 3: 2Captcha fallback — only when stealth+behavior isn't enough

Usage:
    # Install dependencies:
    pip install playwright playwright-stealth requests
    playwright install chromium

    # Run with credentials:
    echo '{"ruc":"0102030405001","password":"mypass","apiKey":"2captcha-key","year":2026,"month":4,"type":"compras","mode":"txt_download"}' | python test-scraper.py

    # Or with args:
    python test-scraper.py --ruc=0102030405001 --password=mypass --api-key=xxx --year=2026 --month=4 --type=compras

    # Visible browser (for debugging):
    python test-scraper.py --visible --ruc=... --password=... --api-key=...
"""

import json
import math
import re
import sys
import time
import argparse
import random
import requests
from datetime import datetime
from pathlib import Path
from playwright.sync_api import sync_playwright, Page

# ─── Stealth Import (v2 → v1 → none) ────────────────────────────────────────

STEALTH_VERSION = 0
try:
    from playwright_stealth import Stealth
    STEALTH_VERSION = 2
except ImportError:
    try:
        from playwright_stealth import stealth_sync
        STEALTH_VERSION = 1
    except ImportError:
        STEALTH_VERSION = 0


# ─── URLs ─────────────────────────────────────────────────────────────────────

SRI_URLS = {
    "portal": "https://srienlinea.sri.gob.ec/sri-en-linea/contribuyente/perfil",
    "compras": "https://srienlinea.sri.gob.ec/tuportal-internet/accederAplicacion.jspa?redireccion=57&idGrupo=55",
    "ventas": "https://srienlinea.sri.gob.ec/tuportal-internet/accederAplicacion.jspa?redireccion=60&idGrupo=55",
}

VOUCHER_TYPES = [
    {"value": "1", "label": "Factura"},
    {"value": "3", "label": "NotaCredito"},
    {"value": "4", "label": "NotaDebito"},
    {"value": "6", "label": "Retencion"},
]

TWOCAPTCHA_IN = "https://2captcha.com/in.php"
TWOCAPTCHA_RES = "https://2captcha.com/res.php"

# Realistic Chrome user agent
CHROME_USER_AGENT = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/147.0.0.0 Safari/537.36"
)


# ─── Helpers ──────────────────────────────────────────────────────────────────

def log(step: str, message: str) -> None:
    ts = datetime.now().strftime("%H:%M:%S")
    print(f"[{ts}] [{step}] {message}", flush=True)


def emit(event: str, data: dict) -> None:
    """Emit JSON line to stdout (matches the Node.js scraper protocol)."""
    print(json.dumps({"event": event, "data": data}), flush=True)


def progress(step: str, message: str) -> None:
    log(step, message)
    emit("progress", {"step": step, "message": message})


# ─── Layer 2: Human Behavior Simulation ──────────────────────────────────────

def random_delay(min_s: float = 0.3, max_s: float = 1.5) -> None:
    """Sleep a random duration to mimic human timing."""
    time.sleep(random.uniform(min_s, max_s))


def bezier_point(t: float, p0: tuple, p1: tuple, p2: tuple, p3: tuple) -> tuple:
    """Calculate a point on a cubic Bézier curve."""
    u = 1 - t
    return (
        u**3 * p0[0] + 3 * u**2 * t * p1[0] + 3 * u * t**2 * p2[0] + t**3 * p3[0],
        u**3 * p0[1] + 3 * u**2 * t * p1[1] + 3 * u * t**2 * p2[1] + t**3 * p3[1],
    )


def human_mouse_move(page: Page, target_x: int | None = None, target_y: int | None = None) -> None:
    """Move mouse along a Bézier curve to a target (or random position)."""
    vw = page.viewport_size["width"] if page.viewport_size else 1366
    vh = page.viewport_size["height"] if page.viewport_size else 768

    start_x = random.randint(100, vw - 100)
    start_y = random.randint(100, vh - 100)
    end_x = target_x if target_x is not None else random.randint(50, vw - 50)
    end_y = target_y if target_y is not None else random.randint(50, vh - 50)

    # Random control points for natural curve
    cp1 = (
        start_x + random.randint(-200, 200),
        start_y + random.randint(-150, 150),
    )
    cp2 = (
        end_x + random.randint(-200, 200),
        end_y + random.randint(-150, 150),
    )

    steps = random.randint(15, 30)
    for i in range(steps + 1):
        t = i / steps
        x, y = bezier_point(t, (start_x, start_y), cp1, cp2, (end_x, end_y))
        x = max(0, min(vw - 1, int(x)))
        y = max(0, min(vh - 1, int(y)))
        page.mouse.move(x, y)
        time.sleep(random.uniform(0.005, 0.025))


def human_scroll(page: Page, direction: str = "down", amount: int | None = None) -> None:
    """Scroll naturally in chunks like a human reader."""
    if amount is None:
        amount = random.randint(150, 500)

    chunks = random.randint(2, 5)
    per_chunk = amount // chunks

    for _ in range(chunks):
        delta = per_chunk + random.randint(-30, 30)
        if direction == "up":
            delta = -delta
        page.mouse.wheel(0, delta)
        time.sleep(random.uniform(0.05, 0.2))


def human_type(page: Page, selector: str, text: str) -> None:
    """Type text with variable delay per character like a human."""
    el = page.query_selector(selector)
    if not el:
        return
    el.click(click_count=3)
    random_delay(0.2, 0.5)

    for char in text:
        el.type(char, delay=0)
        time.sleep(random.uniform(0.05, 0.18))
        # Occasional longer pause (thinking)
        if random.random() < 0.05:
            time.sleep(random.uniform(0.3, 0.7))


def simulate_human_presence(page: Page, duration_s: float = 8.0) -> None:
    """Simulate a human browsing the page for a while before taking action."""
    progress("stealth", f"Simulando presencia humana ({duration_s:.0f}s)...")
    start = time.time()

    while time.time() - start < duration_s:
        action = random.choice(["move", "move", "scroll", "wait"])
        if action == "move":
            human_mouse_move(page)
            random_delay(0.5, 1.5)
        elif action == "scroll":
            human_scroll(page, random.choice(["down", "down", "up"]))
            random_delay(0.8, 2.0)
        else:
            random_delay(1.0, 3.0)


# ─── Layer 3: 2Captcha Fallback ──────────────────────────────────────────────

def solve_captcha_2captcha(api_key: str, sitekey: str, page_url: str) -> str | None:
    """Solve reCAPTCHA via 2Captcha service (paid fallback)."""
    progress("captcha-2captcha", f"Enviando reCAPTCHA a 2Captcha (sitekey={sitekey[:10]}...)...")

    resp = requests.post(TWOCAPTCHA_IN, data={
        "key": api_key,
        "method": "userrecaptcha",
        "googlekey": sitekey,
        "pageurl": page_url,
        "json": "1",
    })
    data = resp.json()

    if data.get("status") != 1:
        progress("captcha-2captcha", f"2Captcha submit error: {data.get('request')}")
        return None

    captcha_id = data["request"]
    progress("captcha-2captcha", f"Captcha enviado, ID={captcha_id}. Esperando solución...")

    time.sleep(5)

    for poll in range(24):
        resp = requests.get(TWOCAPTCHA_RES, params={
            "key": api_key,
            "action": "get",
            "id": captcha_id,
            "json": "1",
        })
        data = resp.json()

        if data.get("status") == 1:
            token = data["request"]
            progress("captcha-2captcha", f"Token obtenido ({len(token)} chars)")
            return token

        if data.get("request") != "CAPCHA_NOT_READY":
            progress("captcha-2captcha", f"2Captcha error: {data.get('request')}")
            return None

        progress("captcha-2captcha", f"Esperando solución... ({(poll + 1) * 5}s)")
        time.sleep(5)

    progress("captcha-2captcha", "2Captcha timeout - no se recibió solución")
    return None


# ─── Layer 1+2: Auto Captcha (stealth + behavior) ────────────────────────────

def try_auto_captcha(page: Page, tipo: str) -> str | None:
    """
    Attempt to pass reCAPTCHA automatically by triggering grecaptcha.execute()
    and waiting for the token. Works when the browser looks legitimate enough
    that Google gives a high score (like manual navigation).

    Returns the token if auto-passed, None otherwise.
    """
    progress("captcha-auto", "Intentando pasar reCAPTCHA automáticamente (stealth)...")

    # Simulate human presence before triggering captcha
    simulate_human_presence(page, duration_s=random.uniform(5, 10))

    # Move mouse near the form area
    human_mouse_move(page, target_x=random.randint(400, 800), target_y=random.randint(300, 500))
    random_delay(0.5, 1.0)

    # Try to trigger reCAPTCHA naturally via grecaptcha.execute()
    execute_result = page.evaluate("""() => {
        // Clear any existing token first
        const respEl = document.getElementById('g-recaptcha-response');
        if (respEl) respEl.value = '';

        if (typeof grecaptcha === 'undefined') {
            return { executed: false, error: 'grecaptcha not defined' };
        }

        try {
            // Method 1: Execute with explicit widget ID 0
            grecaptcha.execute(0);
            return { executed: true, method: 'execute_widget_0' };
        } catch(e1) {
            try {
                // Method 2: Execute without arguments
                grecaptcha.execute();
                return { executed: true, method: 'execute_default' };
            } catch(e2) {
                try {
                    // Method 3: Find widget via container
                    const container = document.querySelector('.g-recaptcha');
                    if (container) {
                        const widgetId = container.getAttribute('data-widget-id');
                        if (widgetId !== null) {
                            grecaptcha.execute(parseInt(widgetId));
                            return { executed: true, method: 'execute_widget_' + widgetId };
                        }
                        // Try rendering and executing
                        const sitekey = container.getAttribute('data-sitekey');
                        if (sitekey) {
                            const wid = grecaptcha.render(container, {
                                sitekey: sitekey,
                                size: 'invisible',
                                callback: function(token) {
                                    document.getElementById('g-recaptcha-response').value = token;
                                }
                            });
                            grecaptcha.execute(wid);
                            return { executed: true, method: 'render_and_execute' };
                        }
                    }
                    return { executed: false, error: 'no widget found: ' + e2.message };
                } catch(e3) {
                    return { executed: false, error: e3.message };
                }
            }
        }
    }""")

    progress("captcha-auto", f"grecaptcha.execute() → {execute_result}")

    if not execute_result.get("executed"):
        progress("captcha-auto", "No se pudo ejecutar grecaptcha, probando click en botón buscar...")
        # Try clicking the actual search button which may trigger captcha
        clicked = page.evaluate("""() => {
            // Look for a search/buscar button that triggers captcha
            const buttons = document.querySelectorAll('button, input[type="submit"], a.ui-commandlink');
            for (const btn of buttons) {
                const text = (btn.textContent || btn.value || '').toLowerCase();
                if (text.includes('buscar') || text.includes('consultar')) {
                    btn.click();
                    return true;
                }
            }
            return false;
        }""")
        if not clicked:
            progress("captcha-auto", "No se encontró botón de búsqueda")
            return None

    # Poll for token (Google may auto-generate it if score is high)
    progress("captcha-auto", "Esperando token automático de reCAPTCHA...")

    for i in range(20):  # Poll for ~10 seconds
        time.sleep(0.5)

        token = page.evaluate("""() => {
            const el = document.getElementById('g-recaptcha-response');
            if (el && el.value && el.value.length > 20) {
                return el.value;
            }
            // Also check if callback already fired and search executed
            const allTextareas = document.querySelectorAll('textarea[name="g-recaptcha-response"]');
            for (const ta of allTextareas) {
                if (ta.value && ta.value.length > 20) return ta.value;
            }
            return null;
        }""")

        if token:
            progress("captcha-auto", f"Token automático obtenido ({len(token)} chars) — stealth funcionó!")
            return token

        # Check if a visible challenge appeared (means stealth wasn't enough)
        challenge_visible = page.evaluate("""() => {
            // Check for visible reCAPTCHA challenge iframe
            const frames = document.querySelectorAll('iframe[src*="recaptcha"][title*="challenge"]');
            for (const frame of frames) {
                const rect = frame.getBoundingClientRect();
                if (rect.width > 100 && rect.height > 100) return true;
            }
            // Check for reCAPTCHA overlay
            const overlay = document.querySelector('.rc-imageselect, .rc-audiochallenge');
            if (overlay) return true;

            return false;
        }""")

        if challenge_visible:
            progress("captcha-auto", "Challenge visible detectado — stealth insuficiente, necesita 2Captcha")
            return None

    progress("captcha-auto", "No se obtuvo token automático en 10s")
    return None


# ─── Login ────────────────────────────────────────────────────────────────────

def login(page: Page, ruc: str, password: str) -> bool:
    progress("login", "Navegando al portal SRI...")

    for attempt in range(1, 4):
        try:
            page.goto(SRI_URLS["portal"], wait_until="networkidle", timeout=60000)
            break
        except Exception as e:
            if attempt == 3:
                emit("error", {"code": "NAV_TIMEOUT", "message": f"No se pudo cargar el portal SRI: {e}"})
                return False
            progress("login", f"Intento {attempt} falló, reintentando...")
            random_delay(2, 5)

    # Human-like: look around the page before filling the form
    simulate_human_presence(page, duration_s=random.uniform(2, 4))

    username_el = page.query_selector("#usuario")
    password_el = page.query_selector("#password")

    if not username_el or not password_el:
        emit("error", {"code": "LOGIN_FORM_NOT_FOUND", "message": "No se encontró el formulario de login del SRI"})
        return False

    progress("login", "Ingresando credenciales...")

    # Move mouse to username field naturally
    box = username_el.bounding_box()
    if box:
        human_mouse_move(page, target_x=int(box["x"] + box["width"] / 2), target_y=int(box["y"] + box["height"] / 2))
        random_delay(0.3, 0.7)

    human_type(page, "#usuario", ruc)
    random_delay(0.5, 1.2)

    # Move mouse to password field
    box = password_el.bounding_box()
    if box:
        human_mouse_move(page, target_x=int(box["x"] + box["width"] / 2), target_y=int(box["y"] + box["height"] / 2))
        random_delay(0.3, 0.6)

    human_type(page, "#password", password)
    random_delay(0.5, 1.0)

    # Move to submit button and click
    submit_btn = page.query_selector("#kc-login")
    if submit_btn:
        box = submit_btn.bounding_box()
        if box:
            human_mouse_move(page, target_x=int(box["x"] + box["width"] / 2), target_y=int(box["y"] + box["height"] / 2))
            random_delay(0.2, 0.5)
        submit_btn.click()
    else:
        password_el.press("Enter")

    progress("login", "Esperando redirección...")
    try:
        page.wait_for_load_state("networkidle", timeout=60000)
    except Exception:
        pass

    random_delay(1.0, 2.5)

    if "/auth/" in page.url:
        error_text = page.evaluate("""() => {
            const el = document.querySelector('.alert-error, #input-error, .kc-feedback-text, .error-message');
            return el ? el.textContent.trim() : null;
        }""")
        emit("error", {"code": "LOGIN_FAILED", "message": error_text or "Credenciales inválidas"})
        return False

    progress("login", "Login exitoso")
    return True


# ─── Navigate ─────────────────────────────────────────────────────────────────

def navigate_to_comprobantes(page: Page, tipo: str) -> None:
    url = SRI_URLS["ventas"] if tipo == "ventas" else SRI_URLS["compras"]
    label = "Comprobantes Emitidos" if tipo == "ventas" else "Comprobantes Recibidos"

    progress("navigate", f"Navegando a {label}...")

    for attempt in range(1, 4):
        try:
            page.goto(url, wait_until="networkidle", timeout=60000)
            break
        except Exception as e:
            if attempt == 3:
                raise
            progress("navigate", f"Intento {attempt} falló, reintentando...")
            random_delay(2, 5)

    # Human: look around after page loads
    simulate_human_presence(page, duration_s=random.uniform(2, 4))
    progress("navigate", f"Página de {label} cargada")


# ─── Sitekey Extraction ──────────────────────────────────────────────────────

def extract_sitekey(page: Page) -> str | None:
    return page.evaluate("""() => {
        const el = document.getElementsByClassName('g-recaptcha')[0];
        if (el) return el.getAttribute('data-sitekey');

        const iframes = document.querySelectorAll('iframe[src*="recaptcha"]');
        for (const iframe of iframes) {
            const match = iframe.src.match(/[?&]k=([^&]+)/);
            if (match) return match[1];
        }

        const scripts = document.querySelectorAll('script');
        for (const script of scripts) {
            const text = script.textContent || '';
            const cargarMatch = text.match(/cargarRecaptcha\\s*\\([^,]+,\\s*['"]([A-Za-z0-9_-]{40})['"]/);
            if (cargarMatch) return cargarMatch[1];
            const match = text.match(/sitekey['\":\\s]+['"]([A-Za-z0-9_-]{40})['"]/);
            if (match) return match[1];
        }

        const allElements = document.querySelectorAll('[data-sitekey]');
        if (allElements.length > 0) return allElements[0].getAttribute('data-sitekey');

        return null;
    }""")


# ─── Set Filters ──────────────────────────────────────────────────────────────

def set_filters(page: Page, voucher_type: dict, year: int, month: int) -> None:
    # Move mouse near the filter area first
    human_mouse_move(page, target_x=random.randint(300, 600), target_y=random.randint(200, 350))
    random_delay(0.3, 0.8)

    page.select_option("#frmPrincipal\\:ano", str(year))
    random_delay(1.0, 2.0)

    page.select_option("#frmPrincipal\\:mes", str(month))
    random_delay(1.0, 2.0)

    page.select_option("#frmPrincipal\\:dia", "0")
    random_delay(0.8, 1.5)

    page.select_option("#frmPrincipal\\:cmbTipoComprobante", voucher_type["value"])
    random_delay(0.8, 1.5)


# ─── Inject Token & Search ────────────────────────────────────────────────────

def inject_token_and_search(page: Page, token: str) -> None:
    page.evaluate("""(t) => {
        const el = document.getElementById('g-recaptcha-response');
        if (el) {
            el.style.display = 'block';
            el.removeAttribute('disabled');
            el.value = t;
        }
        document.querySelectorAll('textarea[name="g-recaptcha-response"]').forEach(el => {
            el.style.display = 'block';
            el.removeAttribute('disabled');
            el.value = t;
        });
    }""", token)

    page.evaluate("(t) => { rcBuscar(t); }", token)


# ─── Check Page State ─────────────────────────────────────────────────────────

def get_table_id(tipo: str) -> str:
    return "frmPrincipal:tablaCompEmitidos_data" if tipo == "ventas" else "frmPrincipal:tablaCompRecibidos_data"


def check_page_state(page: Page, tipo: str) -> dict:
    table_id = get_table_id(tipo)
    return page.evaluate("""(tblId) => {
        const msgs = document.getElementById('formMessages:messages');
        const msgsText = msgs ? msgs.innerText : '';

        if (msgsText.includes('Captcha incorrecta')) {
            return { state: 'captcha_failed', detail: msgsText.substring(0, 100) };
        }
        if (msgsText.includes('No existen datos')) {
            return { state: 'no_results', detail: msgsText.substring(0, 100) };
        }

        const tbody = document.getElementById(tblId);
        if (tbody) {
            const rows = tbody.querySelectorAll('tr');
            if (rows.length > 0) {
                const firstText = rows[0]?.textContent?.trim() || '';
                const emptyMsg = tbody.querySelector('.ui-datatable-empty-message');
                if (!firstText.includes('No se encontraron') && !emptyMsg && firstText.length > 0) {
                    return { state: 'has_results', detail: rows.length + ' rows' };
                }
            }
        }

        let ajaxBusy = false;
        try {
            ajaxBusy = typeof PrimeFaces !== 'undefined' &&
                PrimeFaces.ajax && PrimeFaces.ajax.Queue && !PrimeFaces.ajax.Queue.isEmpty();
        } catch(e) {}

        const blockUI = !!document.querySelector('.ui-blockui, .ui-blockui-content, .ui-widget-overlay');
        return {
            state: 'unknown',
            detail: 'msgs=[' + msgsText.substring(0, 60) + '] ajaxBusy=' + ajaxBusy + ' blockUI=' + blockUI,
        };
    }""", table_id)


# ─── Search With Captcha (3-Layer Strategy) ──────────────────────────────────

def search_with_captcha(page: Page, voucher_type: dict, year: int, month: int,
                        api_key: str | None, tipo: str) -> bool:
    """
    3-layer captcha strategy:
      1. Try auto-pass via stealth + human behavior (free, fast)
      2. If auto-pass gives token, use it with rcBuscar
      3. If auto-pass fails, fall back to 2Captcha (paid, slow)
    """
    label = voucher_type["label"]
    progress(label, f"Configurando filtros: año={year}, mes={month}, tipo={label}...")
    set_filters(page, voucher_type, year, month)

    sitekey = extract_sitekey(page)
    if not sitekey:
        progress(label, "ERROR: No se encontró el sitekey de reCAPTCHA")
        return False

    progress(label, f"Sitekey extraído: {sitekey}")

    diag = page.evaluate("""() => {
        const hasRcBuscar = typeof rcBuscar === 'function';
        const hasGrecaptcha = typeof grecaptcha !== 'undefined';
        return {
            hasRcBuscar,
            hasGrecaptcha,
            hasRecaptchaEl: !!document.getElementById('g-recaptcha-response'),
        };
    }""")

    progress(label, f"Diagnóstico: rcBuscar={diag['hasRcBuscar']}, grecaptcha={diag.get('hasGrecaptcha')}")

    if not diag["hasRcBuscar"]:
        progress(label, "ERROR: rcBuscar no encontrado en la página")
        return False

    for attempt in range(1, 4):
        progress(label, f"═══ Intento {attempt}/3 ═══")

        # ── Layer 1+2: Try auto-pass (stealth + human behavior) ──
        token = try_auto_captcha(page, tipo)

        if token:
            progress(label, f"[LAYER 1+2] Token automático, inyectando en rcBuscar...")
            try:
                inject_token_and_search(page, token)
            except Exception as e:
                progress(label, f"Error al inyectar token auto: {e}")
                token = None

        # ── Layer 3: 2Captcha fallback ──
        if not token:
            if not api_key:
                progress(label, "[LAYER 3] Sin API key de 2Captcha, no hay fallback disponible")
                if attempt < 3:
                    progress(label, "Recargando para reintentar stealth...")
                    navigate_to_comprobantes(page, tipo)
                    set_filters(page, voucher_type, year, month)
                continue

            progress(label, f"[LAYER 3] Usando 2Captcha como fallback (intento {attempt})...")
            token = solve_captcha_2captcha(api_key, sitekey, "https://srienlinea.sri.gob.ec")

            if not token:
                progress(label, f"2Captcha falló en intento {attempt}")
                if attempt < 3:
                    progress(label, "Recargando página para nuevo intento...")
                    navigate_to_comprobantes(page, tipo)
                    set_filters(page, voucher_type, year, month)
                continue

            progress(label, f"[LAYER 3] Token 2Captcha obtenido, inyectando...")
            try:
                inject_token_and_search(page, token)
            except Exception as e:
                progress(label, f"Error al inyectar token 2Captcha: {e}")
                if attempt < 3:
                    navigate_to_comprobantes(page, tipo)
                    set_filters(page, voucher_type, year, month)
                continue

        # ── Check results ──
        random_delay(3, 6)

        result = check_page_state(page, tipo)
        progress(label, f"Estado: {result['state']} ({result['detail']})")

        if result["state"] in ("has_results", "no_results"):
            progress(label, f"Búsqueda exitosa en intento {attempt}")
            return True

        if result["state"] == "captcha_failed":
            progress(label, f"Captcha rechazado en intento {attempt}")
        else:
            progress(label, "Estado desconocido, esperando más...")
            random_delay(4, 7)
            retry = check_page_state(page, tipo)
            progress(label, f"Segundo chequeo: {retry['state']} ({retry['detail']})")
            if retry["state"] in ("has_results", "no_results"):
                return True

        if attempt < 3:
            progress(label, "Recargando página para nuevo intento...")
            navigate_to_comprobantes(page, tipo)
            set_filters(page, voucher_type, year, month)

    progress(label, "Captcha falló después de 3 intentos")
    return False


# ─── Download For Voucher Type ────────────────────────────────────────────────

def download_for_voucher_type(page: Page, voucher_type: dict, year: int, month: int,
                               download_dir: Path, api_key: str | None, tipo: str) -> dict:
    label = voucher_type["label"]
    search_ok = search_with_captcha(page, voucher_type, year, month, api_key, tipo)

    if not search_ok:
        return {"type": label, "status": "captcha_failed", "content": None}

    table_id = get_table_id(tipo)
    table_info = page.evaluate("""(tblId) => {
        const tbody = document.getElementById(tblId);
        if (!tbody) return { found: false, rows: 0, message: 'Tabla no encontrada' };
        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) return { found: true, rows: 0, message: 'Sin registros' };
        const firstRowText = rows[0]?.textContent?.trim() || '';
        if (firstRowText.includes('No se encontraron') || firstRowText.includes('No existen') ||
            rows[0]?.classList.contains('ui-datatable-empty-message')) {
            return { found: true, rows: 0, message: firstRowText.substring(0, 100) };
        }
        return { found: true, rows: rows.length, message: rows.length + ' registros encontrados' };
    }""", table_id)

    progress(label, f"Resultado: {table_info['message']}")

    if table_info["rows"] == 0:
        return {"type": label, "status": "no_records", "content": None}

    progress(label, f"{table_info['rows']} registros, descargando reporte...")

    # Find the PrimeFaces download link (has onclick + text "descargar")
    target_id = page.evaluate("""() => {
        const links = document.querySelectorAll('a');
        for (const link of links) {
            const text = (link.textContent || '').trim().toLowerCase();
            const hasOnclick = !!link.onclick || !!link.getAttribute('onclick');
            if (hasOnclick && text.includes('descargar')) {
                return link.id || null;
            }
        }
        return null;
    }""")

    progress(label, f"Link de descarga: id={target_id}")

    # Set up response interceptor to capture file content (PrimeFaces fallback)
    captured = {"content": None, "filename": None}

    def on_response(response):
        content_type = response.headers.get("content-type", "")
        content_disp = response.headers.get("content-disposition", "")
        if "attachment" in content_disp or "text/plain" in content_type or "octet-stream" in content_type:
            if "attachment" in content_disp or response.url != page.url:
                try:
                    raw = response.body()
                    try:
                        captured["content"] = raw.decode("utf-8")
                    except UnicodeDecodeError:
                        captured["content"] = raw.decode("latin-1")
                    match = re.search(r'filename[*]?=["\']?([^"\';\n]+)', content_disp)
                    if match:
                        captured["filename"] = match.group(1).strip()
                    progress(label, f"Respuesta interceptada: {len(captured['content'])} bytes, filename={captured['filename']}")
                except Exception:
                    pass

    if not target_id:
        progress(label, "No se encontró link de descarga con onclick")
        return {"type": label, "status": "download_button_not_found", "content": None}

    page.on("response", on_response)

    try:
        # Try Playwright download event first (click via JavaScript for PrimeFaces)
        try:
            with page.expect_download(timeout=30000) as download_info:
                page.evaluate("(id) => document.getElementById(id).click()", target_id)

            download = download_info.value
            filename = download.suggested_filename or f"{label}.txt"
            file_path = download_dir / filename
            download.save_as(file_path)
            raw = file_path.read_bytes()
            try:
                content = raw.decode("utf-8")
            except UnicodeDecodeError:
                content = raw.decode("latin-1")
            file_path.unlink(missing_ok=True)
            progress(label, f"Descargado via Playwright: {filename}")
            return {"type": label, "status": "downloaded", "content": content, "rows": table_info["rows"]}

        except Exception as e:
            progress(label, f"expect_download falló ({e}), verificando interceptor...")

        # Fallback: check if response interceptor captured the file
        if captured["content"]:
            filename = captured["filename"] or f"{label}.txt"
            progress(label, f"Descargado via interceptor: {filename}")
            return {"type": label, "status": "downloaded", "content": captured["content"], "rows": table_info["rows"]}

        # Fallback 2: wait a bit more for interceptor (PrimeFaces may be slow)
        progress(label, "Esperando respuesta del servidor...")
        for _ in range(20):
            time.sleep(0.5)
            if captured["content"]:
                break

        if captured["content"]:
            filename = captured["filename"] or f"{label}.txt"
            progress(label, f"Descargado via interceptor (delayed): {filename}")
            return {"type": label, "status": "downloaded", "content": captured["content"], "rows": table_info["rows"]}

        progress(label, "No se pudo capturar la descarga")
        return {"type": label, "status": "download_failed", "content": None}

    finally:
        page.remove_listener("response", on_response)


# ─── Scrape Table ─────────────────────────────────────────────────────────────

def scrape_table_data(page: Page, tipo: str) -> list[str]:
    table_id = get_table_id(tipo)
    all_claves = []
    page_num = 1

    progress("scrape", "Extrayendo datos de la tabla...")

    while True:
        progress("scrape", f"Procesando página {page_num}...")

        page_data = page.evaluate("""(tblId) => {
            const tbody = document.getElementById(tblId);
            if (!tbody) return { claves: [], hasNext: false };
            const rows = tbody.querySelectorAll('tr');
            const claves = [];
            for (const row of rows) {
                for (const cell of row.querySelectorAll('td')) {
                    const text = cell.textContent?.trim();
                    if (text && /^\\d{49}$/.test(text)) claves.push(text);
                }
                for (const input of row.querySelectorAll('input[type="hidden"]')) {
                    const val = input.value?.trim();
                    if (val && /^\\d{49}$/.test(val)) claves.push(val);
                }
            }
            const nextBtn = document.querySelector('.ui-paginator-next:not(.ui-state-disabled)');
            return { claves, hasNext: nextBtn !== null };
        }""", table_id)

        all_claves.extend(page_data["claves"])
        progress("scrape", f"Página {page_num}: {len(page_data['claves'])} claves (total: {len(all_claves)})")

        if not page_data["hasNext"]:
            break

        page.click(".ui-paginator-next:not(.ui-state-disabled)")
        random_delay(2, 4)
        page_num += 1

    unique_claves = list(dict.fromkeys(all_claves))
    progress("scrape", f"Extracción completada: {len(unique_claves)} claves únicas")
    return unique_claves


# ─── Main ─────────────────────────────────────────────────────────────────────

def parse_args():
    parser = argparse.ArgumentParser(description="SRI Scraper (Python) — 3-Layer Strategy")
    parser.add_argument("--ruc", help="RUC del contribuyente")
    parser.add_argument("--password", help="Contraseña SRI")
    parser.add_argument("--api-key", dest="api_key", help="API key de 2Captcha (opcional, fallback)")
    parser.add_argument("--type", dest="tipo", default="compras", choices=["compras", "ventas"])
    parser.add_argument("--year", type=int, default=2026)
    parser.add_argument("--month", type=int, default=4)
    parser.add_argument("--mode", default="txt_download", choices=["txt_download", "table_scrape"])
    parser.add_argument("--headless", action="store_true", default=True)
    parser.add_argument("--visible", action="store_true", help="Abrir navegador visible")
    parser.add_argument("--download-dir", dest="download_dir", default="/tmp/sri-scrape-py")
    parser.add_argument("--user-data-dir", dest="user_data_dir", default=None,
                        help="Directorio para persistir sesión del navegador (cookies, localStorage)")
    return parser.parse_args()


def load_config() -> dict:
    """Load config from stdin (JSON) if piped, otherwise from CLI args."""
    if not sys.stdin.isatty():
        raw = sys.stdin.read().strip()
        if raw:
            return json.loads(raw)

    args = parse_args()
    if not args.ruc or not args.password:
        print("Usage: echo '{\"ruc\":\"...\",\"password\":\"...\"}' | python test-scraper.py", file=sys.stderr)
        print("   or: python test-scraper.py --ruc=... --password=...", file=sys.stderr)
        print("", file=sys.stderr)
        print("Nota: --api-key es opcional (fallback). Sin él, solo usa stealth.", file=sys.stderr)
        sys.exit(1)

    return {
        "ruc": args.ruc,
        "password": args.password,
        "apiKey": args.api_key,
        "type": args.tipo,
        "year": args.year,
        "month": args.month,
        "mode": args.mode,
        "headless": not args.visible,
        "downloadDir": args.download_dir,
        "userDataDir": args.user_data_dir,
    }


def main():
    config = load_config()

    ruc = config["ruc"]
    password = config["password"]
    tipo = config.get("type", "compras")
    year = config.get("year", 2026)
    month = config.get("month", 4)
    mode = config.get("mode", "txt_download")
    api_key = config.get("apiKey") or config.get("captchaApiKey")
    download_dir = Path(config.get("downloadDir", "/tmp/sri-scrape-py"))
    headless = config.get("headless", True)
    user_data_dir = config.get("userDataDir")

    download_dir.mkdir(parents=True, exist_ok=True)

    # ── Log strategy info ──
    progress("init", f"Stealth: {'v' + str(STEALTH_VERSION) if STEALTH_VERSION else 'NO INSTALADO'}")
    progress("init", f"2Captcha fallback: {'SI' if api_key else 'NO (solo stealth)'}")
    if not api_key:
        progress("init", "Tip: usa --api-key para habilitar 2Captcha como fallback")

    if STEALTH_VERSION == 0:
        progress("init", "ADVERTENCIA: playwright-stealth no instalado. Instala con: pip install playwright-stealth")

    # ── Browser launch args ──
    launch_args = [
        "--no-sandbox",
        "--disable-setuid-sandbox",
        "--disable-dev-shm-usage",
        "--disable-gpu",
        "--disable-blink-features=AutomationControlled",
        "--lang=es-EC,es",
        "--window-size=1366,768",
    ]

    # ── Context options (realistic browser profile) ──
    context_opts = {
        "viewport": {"width": 1366, "height": 768},
        "locale": "es-EC",
        "timezone_id": "America/Guayaquil",
        "user_agent": CHROME_USER_AGENT,
        "accept_downloads": True,
        "extra_http_headers": {
            "Accept-Language": "es-EC,es;q=0.9,en;q=0.8",
            "sec-ch-ua": '"Chromium";v="147", "Google Chrome";v="147", "Not/A)Brand";v="99"',
            "sec-ch-ua-mobile": "?0",
            "sec-ch-ua-platform": '"macOS"',
        },
    }

    # ── Launch with stealth ──
    if STEALTH_VERSION == 2:
        stealth = Stealth(
            navigator_languages_override=("es-EC", "es"),
        )
        pw_context_manager = stealth.use_sync(sync_playwright())
    else:
        pw_context_manager = sync_playwright()

    with pw_context_manager as pw:
        # Use persistent context if user-data-dir provided (keeps cookies between runs)
        if user_data_dir:
            progress("init", f"Usando contexto persistente: {user_data_dir}")
            Path(user_data_dir).mkdir(parents=True, exist_ok=True)
            context = pw.chromium.launch_persistent_context(
                user_data_dir,
                headless=headless,
                args=launch_args,
                **context_opts,
            )
            page = context.pages[0] if context.pages else context.new_page()
        else:
            browser = pw.chromium.launch(
                headless=headless,
                args=launch_args,
            )
            context = browser.new_context(**context_opts)
            page = context.new_page()

        # Apply stealth v1 per-page if v2 not available
        if STEALTH_VERSION == 1:
            stealth_sync(page)
            progress("init", "Stealth v1 aplicado a la página")

        try:
            # Step 1: Login
            logged_in = login(page, ruc, password)
            if not logged_in:
                context.close()
                if not user_data_dir:
                    browser.close()
                sys.exit(1)

            # Step 2: Navigate
            navigate_to_comprobantes(page, tipo)

            # Step 3: Process
            if mode == "txt_download":
                files = []

                for i, vt in enumerate(VOUCHER_TYPES):
                    if i > 0:
                        navigate_to_comprobantes(page, tipo)

                    try:
                        result = download_for_voucher_type(page, vt, year, month, download_dir, api_key, tipo)
                        files.append(result)

                        if result["status"] == "downloaded":
                            progress("summary", f"{vt['label']}: {result['rows']} registros descargados")
                        else:
                            progress("summary", f"{vt['label']}: {result['status']}")

                    except Exception as e:
                        progress(vt["label"], f"Error: {e}")
                        files.append({"type": vt["label"], "status": "error", "content": None, "error": str(e)})

                    random_delay(1, 3)

                emit("result", {"mode": "txt_download", "files": files})

            elif mode == "table_scrape":
                all_claves = []

                for i, vt in enumerate(VOUCHER_TYPES):
                    if i > 0:
                        navigate_to_comprobantes(page, tipo)

                    try:
                        search_ok = search_with_captcha(page, vt, year, month, api_key, tipo)
                        if search_ok:
                            claves = scrape_table_data(page, tipo)
                            all_claves.extend(claves)
                    except Exception as e:
                        progress(vt["label"], f"Error: {e}")

                    random_delay(1, 3)

                unique_claves = list(dict.fromkeys(all_claves))
                emit("result", {"mode": "table_scrape", "clavesAcceso": unique_claves})

        except Exception as e:
            emit("error", {"code": "UNEXPECTED_ERROR", "message": str(e)})
            raise
        finally:
            context.close()
            if not user_data_dir:
                browser.close()


if __name__ == "__main__":
    main()
