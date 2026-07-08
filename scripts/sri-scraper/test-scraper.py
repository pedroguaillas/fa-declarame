"""
SRI Scraper Test Script (Python + Playwright) — 2-Layer Strategy

Layer 1: Stealth (playwright-stealth) — make browser undetectable
Layer 2: Human behavior simulation — mouse, scroll, timing

Usage:
    # Install dependencies:
    pip install playwright playwright-stealth
    playwright install chromium

    # Run with credentials:
    echo '{"ruc":"0102030405001","password":"mypass","year":2026,"month":4,"type":"compras","mode":"txt_download"}' | python test-scraper.py

    # Or with args:
    python test-scraper.py --ruc=0102030405001 --password=mypass --year=2026 --month=4 --type=compras

    # Visible browser (for debugging):
    python test-scraper.py --visible --ruc=... --password=...
"""

import argparse
import glob
import json
import math
import os
import platform as _platform
import random
import re
import subprocess
import sys
import time
import urllib.request
from datetime import datetime
from pathlib import Path

from playwright.sync_api import Page, sync_playwright

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
    "ventas": "https://srienlinea.sri.gob.ec/tuportal-internet/accederAplicacion.jspa?redireccion=60&idGrupo=58",
}

VOUCHER_TYPES = [
    {"value": "1", "label": "Factura"},
    {"value": "3", "label": "NotaCredito"},
    {"value": "4", "label": "NotaDebito"},
    {"value": "6", "label": "Retencion"},
]

# Comprobantes recibidos (compras): facturas, notas de crédito/débito y retenciones recibidas
COMPRAS_VOUCHER_TYPES = [
    {"value": "1", "label": "Factura"},
    {"value": "3", "label": "NotaCredito"},
    {"value": "4", "label": "NotaDebito"},
    {"value": "6", "label": "Retencion"},
]

# Chrome user agent and sec-ch-ua headers.
_SYS = _platform.system()  # 'Darwin', 'Linux', 'Windows'


# The Chrome version we present is the latest STABLE consumer Chrome, fetched at
# runtime (fetch_latest_chrome_version) and used to build the whole fingerprint
# (build_chrome_fingerprint). reCAPTCHA v3 penalizes outdated browsers, so we must
# present the newest stable version — NOT the Playwright-bundled "Chrome for
# Testing" engine version, which lags stable by a release or two. Presenting an
# old version tanks the score and the SRI rejects the captcha ("Captcha
# incorrecta"). The engine-vs-claimed gap is not observable to reCAPTCHA in-page.
#
# This constant is only a fallback used when the version lookup fails; keep it
# aligned with a recent stable Chrome so the fallback stays plausible.
_CHROME_FULL_VER_DEFAULT = "150.0.7871.47"

# Chrome for Testing publishes the current stable version here (official Google).
_CHROME_VERSIONS_URL = "https://googlechromelabs.github.io/chrome-for-testing/last-known-good-versions.json"


def build_chrome_fingerprint(full_version: str) -> dict:
    """Build a consistent Chrome-on-macOS fingerprint from a real Chromium
    version string like '147.0.7727.15'. Only the version is dynamic; the macOS
    platform spoof stays fixed.

    @param string $full_version Full version, e.g. '147.0.7727.15'.
    @return array{major:string,full_version:string,ua_version:string,user_agent:string,platform:string,sec_ch_ua:string,sec_ch_ua_full:string}
    """
    major = full_version.split(".")[0]
    ua_version = f"{major}.0.0.0"  # UA reduction: major.0.0.0
    user_agent = (
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        f"Chrome/{ua_version} Safari/537.36"
    )
    sec_ch_ua = (
        f'"Google Chrome";v="{major}", "Chromium";v="{major}", "Not)A;Brand";v="24"'
    )
    sec_ch_ua_full = (
        f'"Google Chrome";v="{full_version}", "Chromium";v="{full_version}", '
        '"Not)A;Brand";v="24.0.0.0"'
    )

    return {
        "major": major,
        "full_version": full_version,
        "ua_version": ua_version,
        "user_agent": user_agent,
        "platform": '"macOS"',
        "sec_ch_ua": sec_ch_ua,
        "sec_ch_ua_full": sec_ch_ua_full,
    }


def fetch_latest_chrome_version(fallback: str = _CHROME_FULL_VER_DEFAULT) -> str:
    """Fetch the latest STABLE consumer Chrome version so the spoofed fingerprint
    presents as an up-to-date browser. This is deliberately NOT the bundled
    Chromium-for-Testing engine version (which lags stable and would lower the
    reCAPTCHA v3 score). Returns the fallback on any error.
    """
    try:
        with urllib.request.urlopen(_CHROME_VERSIONS_URL, timeout=15) as resp:
            data = json.loads(resp.read().decode("utf-8"))
        version = data["channels"]["Stable"]["version"]
        if re.fullmatch(r"\d+\.\d+\.\d+\.\d+", version):
            return version
        progress("fingerprint", f"Versión inesperada del endpoint: {version!r}")
    except Exception as e:
        progress(
            "fingerprint", f"No se pudo obtener última versión Chrome estable: {e}"
        )

    return fallback


# reCAPTCHA-hardening init script. Patches remaining headless/automation signals
# that reCAPTCHA v3 checks. Version-bearing fields use __CHROME_MAJOR__ /
# __CHROME_FULL__ tokens injected by build_stealth_init_script so they stay in
# sync with the detected engine version.
_STEALTH_INIT_TEMPLATE = """
    // Remove webdriver flag
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });

    // Ensure window.chrome exists (missing in headless Chromium)
    if (!window.chrome) {
        window.chrome = { runtime: {}, loadTimes: function(){}, csi: function(){}, app: {} };
    }

    // Spoof platform to macOS — must match CH_UA_PLATFORM header ("macOS").
    Object.defineProperty(navigator, 'platform', { get: () => 'MacIntel' });
    Object.defineProperty(navigator, 'oscpu', { get: () => undefined });

    // Patch userAgentData — reCAPTCHA reads this API directly.
    // Must match sec-ch-ua headers: Google Chrome first, then Chromium.
    if (navigator.userAgentData) {
        const brands = [
            { brand: 'Google Chrome', version: '__CHROME_MAJOR__' },
            { brand: 'Chromium',      version: '__CHROME_MAJOR__' },
            { brand: 'Not)A;Brand',   version: '24'  },
        ];
        Object.defineProperty(navigator, 'userAgentData', {
            get: () => ({
                brands,
                mobile: false,
                platform: 'macOS',
                getHighEntropyValues: (hints) => Promise.resolve({
                    brands,
                    mobile: false,
                    platform: 'macOS',
                    platformVersion: '14.0.0',
                    architecture: 'arm',
                    model: '',
                    uaFullVersion: '__CHROME_FULL__',
                    fullVersionList: [
                        { brand: 'Google Chrome', version: '__CHROME_FULL__' },
                        { brand: 'Chromium',      version: '__CHROME_FULL__' },
                        { brand: 'Not)A;Brand',   version: '24.0.0.0'       },
                    ],
                }),
                toJSON: () => ({ brands, mobile: false, platform: 'macOS' }),
            }),
        });
    }

    // Realistic plugin list
    Object.defineProperty(navigator, 'plugins', {
        get: () => {
            const p = [
                { name: 'Chrome PDF Plugin', filename: 'internal-pdf-viewer' },
                { name: 'Chrome PDF Viewer', filename: 'mhjfbmdgcfjbbpaeojofohoefgiehjai' },
                { name: 'Native Client', filename: 'internal-nacl-plugin' },
            ];
            p.__proto__ = PluginArray.prototype;
            return p;
        }
    });

    // Permissions API — avoid the headless 'denied' default for notifications
    const _origPermQuery = window.navigator.permissions.query.bind(navigator.permissions);
    window.navigator.permissions.query = (params) =>
        params.name === 'notifications'
            ? Promise.resolve({ state: Notification.permission })
            : _origPermQuery(params);
"""


def build_stealth_init_script(fp: dict) -> str:
    """Return the reCAPTCHA-hardening init script with the fingerprint version
    injected, so userAgentData matches the detected Chromium version.

    @param fp A fingerprint dict from build_chrome_fingerprint().
    """
    return _STEALTH_INIT_TEMPLATE.replace("__CHROME_MAJOR__", fp["major"]).replace(
        "__CHROME_FULL__", fp["full_version"]
    )


# Backwards-compatible module constants (fallback fingerprint for standalone use).
_DEFAULT_FINGERPRINT = build_chrome_fingerprint(_CHROME_FULL_VER_DEFAULT)
_CHROME_MAJOR = _DEFAULT_FINGERPRINT["major"]
_CHROME_FULL_VER = _DEFAULT_FINGERPRINT["full_version"]
_CHROME_UA_VER = _DEFAULT_FINGERPRINT["ua_version"]
CHROME_USER_AGENT = _DEFAULT_FINGERPRINT["user_agent"]
CH_UA_PLATFORM = _DEFAULT_FINGERPRINT["platform"]
CH_UA_SEC = _DEFAULT_FINGERPRINT["sec_ch_ua"]
CH_UA_SEC_FULL = _DEFAULT_FINGERPRINT["sec_ch_ua_full"]


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


def human_mouse_move(
    page: Page, target_x: int | None = None, target_y: int | None = None
) -> None:
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


def human_scroll(
    page: Page, direction: str = "down", amount: int | None = None
) -> None:
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


# ─── Login ────────────────────────────────────────────────────────────────────


def login(page: Page, ruc: str, password: str) -> bool:
    progress("login", "Navegando al portal SRI...")

    for attempt in range(1, 4):
        try:
            page.goto(SRI_URLS["portal"], wait_until="networkidle", timeout=60000)
            break
        except Exception as e:
            if attempt == 3:
                emit(
                    "error",
                    {
                        "code": "NAV_TIMEOUT",
                        "message": f"No se pudo cargar el portal SRI: {e}",
                    },
                )
                return False
            progress("login", f"Intento {attempt} falló, reintentando...")
            random_delay(2, 5)

    # Human-like: look around the page before filling the form
    simulate_human_presence(page, duration_s=random.uniform(2, 4))

    username_el = page.query_selector("#usuario")
    password_el = page.query_selector("#password")

    if not username_el or not password_el:
        emit(
            "error",
            {
                "code": "LOGIN_FORM_NOT_FOUND",
                "message": "No se encontró el formulario de login del SRI",
            },
        )
        return False

    progress("login", "Ingresando credenciales...")

    # Move mouse to username field naturally
    box = username_el.bounding_box()
    if box:
        human_mouse_move(
            page,
            target_x=int(box["x"] + box["width"] / 2),
            target_y=int(box["y"] + box["height"] / 2),
        )
        random_delay(0.3, 0.7)

    human_type(page, "#usuario", ruc)
    random_delay(0.5, 1.2)

    # Move mouse to password field
    box = password_el.bounding_box()
    if box:
        human_mouse_move(
            page,
            target_x=int(box["x"] + box["width"] / 2),
            target_y=int(box["y"] + box["height"] / 2),
        )
        random_delay(0.3, 0.6)

    human_type(page, "#password", password)
    random_delay(0.5, 1.0)

    # Move to submit button and click
    submit_btn = page.query_selector("#kc-login")
    if submit_btn:
        box = submit_btn.bounding_box()
        if box:
            human_mouse_move(
                page,
                target_x=int(box["x"] + box["width"] / 2),
                target_y=int(box["y"] + box["height"] / 2),
            )
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
        emit(
            "error",
            {"code": "LOGIN_FAILED", "message": error_text or "Credenciales inválidas"},
        )
        return False

    progress("login", "Login exitoso")
    return True


# ─── Navigate ─────────────────────────────────────────────────────────────────


def navigate_to_comprobantes(page: Page, tipo: str) -> None:
    if tipo == "ventas":
        _navigate_ventas(page)
    else:
        _navigate_compras(page)


def _navigate_compras(page: Page) -> None:
    url = SRI_URLS["compras"]
    progress("navigate", "Navegando a Comprobantes Recibidos...")

    for attempt in range(1, 4):
        try:
            page.goto(url, wait_until="networkidle", timeout=60000)
            break
        except Exception as e:
            if attempt == 3:
                raise
            progress("navigate", f"Intento {attempt} falló, reintentando...")
            random_delay(2, 5)

    simulate_human_presence(page, duration_s=random.uniform(1, 2))
    progress("navigate", "Página de Comprobantes Recibidos cargada")


def _navigate_ventas(page: Page) -> None:
    url = SRI_URLS["ventas"]
    progress("navigate", "Navegando a Facturación Electrónica Consultas...")

    for attempt in range(1, 4):
        try:
            page.goto(url, wait_until="networkidle", timeout=60000)
            break
        except Exception as e:
            if attempt == 3:
                raise
            progress("navigate", f"Intento {attempt} falló, reintentando...")
            random_delay(2, 5)

    simulate_human_presence(page, duration_s=random.uniform(2, 3))

    # Click en "Comprobantes electrónicos emitidos" via mojarra JSF
    progress("navigate", "Ejecutando click en 'Comprobantes electrónicos emitidos'...")
    page.evaluate("""() => {
        mojarra.jsfcljs(document.getElementById('consultaDocumentoForm'),
            {'consultaDocumentoForm:j_idt22':'consultaDocumentoForm:j_idt22'},'');
    }""")

    # Esperar que cargue el formulario
    time.sleep(3)
    try:
        page.wait_for_load_state("networkidle", timeout=15000)
    except Exception:
        pass

    simulate_human_presence(page, duration_s=random.uniform(1, 2))
    progress("navigate", "Página de Comprobantes Emitidos cargada")


# ─── Set Filters ──────────────────────────────────────────────────────────────


def set_filters(
    page: Page,
    voucher_type: dict,
    year: int,
    month: int,
    day: int = 0,
    tipo: str = "compras",
) -> None:
    """Set search filters. For compras: dropdowns. For ventas: calendar date input."""
    # Move mouse near the filter area first
    human_mouse_move(
        page, target_x=random.randint(300, 600), target_y=random.randint(200, 350)
    )
    random_delay(0.3, 0.8)

    if tipo == "ventas":
        # Ventas usa calendar input con formato dd/mm/yyyy
        fecha = f"{day:02d}/{month:02d}/{year}"
        page.evaluate(f"""() => {{
            const input = document.getElementById('frmPrincipal:calendarFechaDesde_input');
            input.value = '{fecha}';
            input.dispatchEvent(new Event('change', {{ bubbles: true }}));
            input.dispatchEvent(new Event('blur', {{ bubbles: true }}));
        }}""")
        random_delay(0.8, 1.5)

        # Seleccionar tipo de comprobante
        page.select_option("#frmPrincipal\\:cmbTipoComprobante", voucher_type["value"])
        random_delay(0.8, 1.5)
    else:
        # Compras usa dropdowns de año/mes/día
        page.select_option("#frmPrincipal\\:ano", str(year))
        random_delay(0.5, 1.0)

        page.select_option("#frmPrincipal\\:mes", str(month))
        random_delay(0.5, 1.0)

        page.select_option("#frmPrincipal\\:dia", str(day))
        random_delay(0.5, 1.0)

        page.select_option("#frmPrincipal\\:cmbTipoComprobante", voucher_type["value"])
        random_delay(0.5, 1.0)


# ─── Check Page State ─────────────────────────────────────────────────────────


def get_table_id(tipo: str) -> str:
    return (
        "frmPrincipal:tablaCompEmitidos_data"
        if tipo == "ventas"
        else "frmPrincipal:tablaCompRecibidos_data"
    )


def check_page_state(page: Page, tipo: str) -> dict:
    table_id = get_table_id(tipo)
    return page.evaluate(
        """(tblId) => {
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
    }""",
        table_id,
    )


# ─── Search Direct (Ventas - no captcha) ─────────────────────────────────────


def search_direct(
    page: Page, voucher_type: dict, year: int, month: int, tipo: str, day: int = 0
) -> bool:
    """For ventas (emitidos): no captcha, just click Consultar button."""
    label = voucher_type["label"]
    day_str = f", día={day}" if day > 0 else ""
    progress(
        label,
        f"Configurando filtros: año={year}, mes={month}{day_str}, tipo={label}...",
    )
    set_filters(page, voucher_type, year, month, day, tipo)

    progress(label, "Haciendo click en Consultar...")
    page.evaluate("""() => {
        document.getElementById('frmPrincipal:btnConsultar').click();
    }""")

    # Esperar respuesta
    random_delay(3, 5)
    try:
        page.wait_for_load_state("networkidle", timeout=15000)
    except Exception:
        pass

    result = check_page_state(page, tipo)
    progress(label, f"Estado: {result['state']} ({result['detail']})")

    return result["state"] in ("has_results", "no_results")


# ─── Search With Captcha (Human Simulation) ──────────────────────────────────


def _human_click_buscar(page: Page, label: str) -> bool:
    """
    Find the Buscar/Consultar button and click it using a real Playwright click
    (not JavaScript .click()). The invisible reCAPTCHA fires automatically via
    the button's onclick handler (rcBuscar) — no token injection needed.
    """
    # PrimeFaces IDs use colons which CSS requires escaping — try multiple selectors.
    # Priority: onclick handler (most reliable), then ID patterns, then text.
    selectors = [
        "[onclick*='rcBuscar']",
        "[onclick*='rcConsultar']",
        "[onclick*='Buscar']",
        "[onclick*='Consultar']",
        "[id*='btnBuscar']",
        "[id*='btnConsultar']",
        "[id*='Buscar']",
        "[id*='Consultar']",
        "button:text('Buscar')",
        "button:text('Consultar')",
        "input[type='submit'][value*='Buscar']",
        "input[type='submit'][value*='Consultar']",
        "a.ui-commandlink:text('Buscar')",
        "a.ui-commandlink:text('Consultar')",
        ".ui-button:text('Buscar')",
        ".ui-button:text('Consultar')",
    ]

    buscar_el = None
    for selector in selectors:
        try:
            loc = page.locator(selector).first
            if loc.count() > 0:
                buscar_el = loc
                progress(label, f"Botón encontrado con selector: {selector}")
                break
        except Exception:
            continue

    if buscar_el is None:
        progress(
            label, "No se encontró botón con locator, intentando query_selector..."
        )
        el = page.query_selector(
            "[onclick*='rcBuscar'], [onclick*='rcConsultar'], "
            "[onclick*='Buscar'], [onclick*='Consultar'], "
            "[id*='btnBuscar'], [id*='btnConsultar'], "
            "[id*='Buscar'][type='button'], [id*='Buscar'][type='submit'], "
            "[id*='Consultar'][type='button'], [id*='Consultar'][type='submit']"
        )
        if el:
            box = el.bounding_box()
            if box:
                human_mouse_move(
                    page,
                    target_x=int(box["x"] + box["width"] / 2),
                    target_y=int(box["y"] + box["height"] / 2),
                )
                random_delay(0.2, 0.5)
            el.click()
            progress(label, "Click en botón Consultar/Buscar (query_selector)")
            return True
        # Log buttons on page to help debug
        try:
            buttons = page.evaluate("""() => {
                const els = document.querySelectorAll('button, input[type=submit], input[type=button], a.ui-commandlink');
                return Array.from(els).slice(0, 20).map(e => ({
                    tag: e.tagName, id: e.id, text: e.textContent?.trim().substring(0, 30), onclick: e.getAttribute('onclick')
                }));
            }""")
            progress(label, f"Botones en página (debug): {buttons}")
        except Exception:
            pass
        progress(label, "ERROR: No se encontró botón Buscar/Consultar")
        return False

    try:
        box = buscar_el.bounding_box()
        if box:
            human_mouse_move(
                page,
                target_x=int(box["x"] + box["width"] / 2),
                target_y=int(box["y"] + box["height"] / 2),
            )
            random_delay(0.2, 0.5)
        buscar_el.click()
        progress(label, "Click en botón Buscar realizado")
        return True
    except Exception as e:
        progress(label, f"Error al hacer click en Buscar: {e}")
        return False


def wait_for_search_results(page: Page, tipo: str, label: str) -> bool:
    """Wait for AJAX search results to appear after clicking search."""
    random_delay(3, 6)

    # Wait for any PrimeFaces AJAX to finish
    try:
        page.wait_for_load_state("networkidle", timeout=15000)
    except Exception:
        pass

    result = check_page_state(page, tipo)
    progress(label, f"Estado: {result['state']} ({result['detail']})")

    if result["state"] in ("has_results", "no_results"):
        return True

    if result["state"] == "unknown":
        progress(label, "Estado desconocido, esperando más...")
        random_delay(4, 7)
        retry = check_page_state(page, tipo)
        progress(label, f"Segundo chequeo: {retry['state']} ({retry['detail']})")
        if retry["state"] in ("has_results", "no_results"):
            return True

    return False


def search_with_captcha(
    page: Page,
    voucher_type: dict,
    year: int,
    month: int,
    tipo: str,
    day: int = 0,
) -> bool:
    """
    Search strategy — human simulation only:
      1. Set filters
      2. Simulate human presence (mouse, scroll, timing)
      3. Real Playwright click on Buscar button → invisible reCAPTCHA fires via onclick (rcBuscar)
      4. Wait for AJAX results
      5. Retry up to 3 times reloading page between attempts
    """
    label = voucher_type["label"]
    day_str = f", día={day}" if day > 0 else ""
    progress(
        label,
        f"Configurando filtros: año={year}, mes={month}{day_str}, tipo={label}...",
    )
    set_filters(page, voucher_type, year, month, day, tipo)

    for attempt in range(1, 4):
        progress(label, f"═══ Intento {attempt}/3 ═══")

        # reCAPTCHA v3 needs enough session history to assign a good score.
        # Session already carries login + navigation signals, so the first
        # attempt only needs a short warm-up; retries warm up a bit longer.
        warmup = random.uniform(10, 15) if attempt == 1 else random.uniform(6, 11)
        progress(label, f"Warm-up reCAPTCHA: {warmup:.0f}s...")
        simulate_human_presence(page, duration_s=warmup)

        # Real Playwright click — triggers onclick="rcBuscar()" which fires the captcha
        if not _human_click_buscar(page, label):
            progress(label, f"No se encontró botón Buscar en intento {attempt}")
            if attempt < 3:
                progress(label, "Recargando página para reintentar...")
                navigate_to_comprobantes(page, tipo)
                set_filters(page, voucher_type, year, month, day, tipo)
            continue

        if wait_for_search_results(page, tipo, label):
            progress(label, f"Búsqueda exitosa en intento {attempt}")
            return True

        result = check_page_state(page, tipo)
        if result["state"] == "captcha_failed":
            progress(label, f"Captcha rechazado en intento {attempt}")

        if attempt < 3:
            progress(label, "Recargando página para nuevo intento...")
            # Do NOT clear localStorage/sessionStorage on captcha retry —
            # reCAPTCHA accumulates session signals there; clearing resets the score.
            navigate_to_comprobantes(page, tipo)
            set_filters(page, voucher_type, year, month, day, tipo)

    progress(label, "Búsqueda falló después de todos los intentos")
    return False


# ─── Download For Voucher Type ────────────────────────────────────────────────


def download_for_voucher_type(
    page: Page,
    voucher_type: dict,
    year: int,
    month: int,
    download_dir: Path,
    tipo: str,
    day: int = 0,
    skip_claves: set | None = None,
) -> dict:
    label = voucher_type["label"]
    if tipo == "ventas":
        search_ok = search_direct(page, voucher_type, year, month, tipo, day)
    else:
        search_ok = search_with_captcha(page, voucher_type, year, month, tipo, day)

    if not search_ok:
        return {"type": label, "status": "captcha_failed", "content": None, "xmls": []}

    table_id = get_table_id(tipo)
    table_info = page.evaluate(
        """(tblId) => {
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
    }""",
        table_id,
    )

    progress(label, f"Resultado: {table_info['message']}")

    if table_info["rows"] == 0:
        return {"type": label, "status": "no_records", "content": None, "xmls": []}

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
        if (
            "attachment" in content_disp
            or "text/plain" in content_type
            or "octet-stream" in content_type
        ):
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
                    progress(
                        label,
                        f"Respuesta interceptada: {len(captured['content'])} bytes, filename={captured['filename']}",
                    )
                except Exception:
                    pass

    if not target_id:
        progress(label, "No se encontró link de descarga con onclick")
        return {
            "type": label,
            "status": "download_button_not_found",
            "content": None,
            "xmls": [],
        }

    page.on("response", on_response)
    final_content = None

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
                final_content = raw.decode("utf-8")
            except UnicodeDecodeError:
                final_content = raw.decode("latin-1")
            file_path.unlink(missing_ok=True)
            progress(label, f"Descargado via Playwright: {filename}")

        except Exception as e:
            progress(label, f"expect_download falló ({e}), verificando interceptor...")

        if final_content is None and captured["content"]:
            filename = captured["filename"] or f"{label}.txt"
            progress(label, f"Descargado via interceptor: {filename}")
            final_content = captured["content"]

        if final_content is None:
            # Fallback 2: wait a bit more for interceptor (PrimeFaces may be slow)
            progress(label, "Esperando respuesta del servidor...")
            for _ in range(20):
                time.sleep(0.5)
                if captured["content"]:
                    final_content = captured["content"]
                    filename = captured["filename"] or f"{label}.txt"
                    progress(label, f"Descargado via interceptor (delayed): {filename}")
                    break

    finally:
        page.remove_listener("response", on_response)

    if final_content is None:
        progress(label, "No se pudo capturar la descarga")
        return {"type": label, "status": "download_failed", "content": None, "xmls": []}

    # ── Classify claves: recent (≤30d → SOAP) vs old (>30d → direct extract) ──
    claves = extract_claves_from_txt(final_content)
    today = datetime.now()
    recent_claves, old_claves = classify_claves(claves, today=today)
    progress(
        label,
        f"Claves: {len(claves)} total → {len(old_claves)} >30d (XML) + {len(recent_claves)} ≤30d (SOAP)  [hoy={today.strftime('%d/%m/%Y')}]",
    )

    # ── Skip claves already in the DB ────────────────────────
    if skip_claves:
        before = len(old_claves) + len(recent_claves)
        old_claves = [c for c in old_claves if c not in skip_claves]
        recent_claves = [c for c in recent_claves if c not in skip_claves]
        skipped_count = before - len(old_claves) - len(recent_claves)
        if skipped_count:
            progress(
                label,
                f"Saltando {skipped_count} ya importadas → {len(old_claves)} old + {len(recent_claves)} recent pendientes",
            )

    # Build clave → txt line lookup for old claves (needed by PHP for ventas modal entries)
    clave_to_line: dict[str, str] = {}
    if old_claves:
        for line in final_content.split("\n")[1:]:
            stripped = line.strip()
            if not stripped:
                continue
            for col in stripped.split("\t"):
                if col.strip() in old_claves:
                    clave_to_line[col.strip()] = stripped
                    break

    xmls: list[dict] = []
    modal_entries: list[dict] = []
    retention_modal_entries: list[dict] = []

    if old_claves:
        if tipo == "compras":
            # Compras: column 10 has a direct XML download button
            progress(
                label,
                f"{len(old_claves)} claves >30d: descargando XMLs de tabla (col 10)...",
            )
            xmls = download_xmls_from_table(page, tipo, set(old_claves))
        else:
            # Ventas (emitidos): no XML button — click clave in col 3 to open modal
            progress(
                label,
                f"{len(old_claves)} claves >30d: extrayendo datos de modal (col 3)...",
            )
            xmls_modal, modal_scraped = scrape_modals_from_table(
                page, tipo, set(old_claves), clave_to_line, label
            )
            xmls.extend(xmls_modal)
            if label == "Retencion":
                retention_modal_entries = modal_scraped
            else:
                modal_entries = modal_scraped

    if recent_claves:
        progress(label, f"{len(recent_claves)} claves ≤30d: se procesarán via SOAP")

    filtered_content = filter_txt_by_claves(final_content, set(recent_claves))

    return {
        "type": label,
        "status": "downloaded",
        "content": filtered_content,
        "xmls": xmls,
        "modal_entries": modal_entries,
        "retention_modal_entries": retention_modal_entries,
        "rows": table_info["rows"],
    }


# ─── Download For Voucher Type By Day (Ventas) ───────────────────────────────


def download_for_voucher_type_by_day(
    page: Page,
    voucher_type: dict,
    year: int,
    month: int,
    download_dir: Path,
    tipo: str,
    max_days: int = 0,
    skip_claves: set | None = None,
) -> dict:
    """For ventas (emitidos): download day by day and concatenate all content."""
    import calendar

    label = voucher_type["label"]
    days_in_month = calendar.monthrange(year, month)[1]

    all_content = ""
    all_xmls: list[dict] = []
    all_modal_entries: list[dict] = []
    all_retention_modal_entries: list[dict] = []
    total_rows = 0
    header_saved = False

    progress(label, f"Ventas: descargando día por día ({days_in_month} días)...")

    for day in range(1, days_in_month + 1):
        progress(label, f"Día {day}/{days_in_month}...")

        result = download_for_voucher_type(
            page, voucher_type, year, month, download_dir, tipo, day, skip_claves
        )

        if result["status"] == "downloaded":
            if result.get("content"):
                lines = result["content"].split("\n")
                if not header_saved:
                    # Keep header from first file
                    all_content = result["content"]
                    header_saved = True
                else:
                    # Skip header line, append only data lines
                    data_lines = lines[1:] if len(lines) > 1 else lines
                    all_content += "\n" + "\n".join(
                        line for line in data_lines if line.strip()
                    )
            all_xmls.extend(result.get("xmls") or [])
            all_modal_entries.extend(result.get("modal_entries") or [])
            all_retention_modal_entries.extend(
                result.get("retention_modal_entries") or []
            )
            total_rows += result.get("rows", 0)
            progress(label, f"Día {day}: {result.get('rows', 0)} registros")
        elif result["status"] == "no_records":
            progress(label, f"Día {day}: sin registros")
        elif result["status"] == "captcha_failed":
            progress(label, f"Día {day}: captcha falló, continuando...")
        else:
            progress(label, f"Día {day}: {result['status']}")

        random_delay(0.5, 1.5)

    if (
        not all_content
        and not all_xmls
        and not all_modal_entries
        and not all_retention_modal_entries
    ):
        return {
            "type": label,
            "status": "no_records",
            "content": None,
            "xmls": [],
            "modal_entries": [],
            "retention_modal_entries": [],
        }

    progress(
        label, f"Total ventas {label}: {total_rows} registros en {days_in_month} días"
    )
    return {
        "type": label,
        "status": "downloaded",
        "content": all_content or None,
        "xmls": all_xmls,
        "modal_entries": all_modal_entries,
        "retention_modal_entries": all_retention_modal_entries,
        "rows": total_rows,
    }


# ─── Clave Acceso Classification ─────────────────────────────────────────────


def extract_claves_from_txt(content: str) -> list[str]:
    """Extract 49-digit access keys from SRI txt file content (any column)."""
    claves = []
    lines = content.split("\n")
    for line in lines[1:]:  # skip header
        line = line.strip()
        if not line:
            continue
        for col in line.split("\t"):
            col = col.strip()
            if re.match(r"^\d{49}$", col):
                claves.append(col)
                break
    return claves


def parse_date_from_clave(clave: str) -> datetime | None:
    """Parse date from first 8 digits of access key (ddmmYYYY format)."""
    try:
        dd = int(clave[0:2])
        mm = int(clave[2:4])
        yyyy = int(clave[4:8])
        return datetime(yyyy, mm, dd)
    except (ValueError, IndexError):
        return None


def classify_claves(
    claves: list[str], threshold_days: int = 30, today: datetime | None = None
) -> tuple[list[str], list[str]]:
    """
    Split claves into:
    - recent (≤threshold_days old): use SOAP to fetch XML
    - old (>threshold_days old): download XML directly from table
    """
    if today is None:
        today = datetime.now()
    recent = []
    old = []
    for clave in claves:
        dt = parse_date_from_clave(clave)
        if dt is None:
            recent.append(clave)  # fallback to SOAP if date unparseable
            continue
        if (today - dt).days <= threshold_days:
            recent.append(clave)
        else:
            old.append(clave)
    return recent, old


def filter_txt_by_claves(content: str, keep_claves: set[str]) -> str | None:
    """Return txt content filtered to only rows whose clave_acceso is in keep_claves."""
    if not keep_claves:
        return None
    lines = content.split("\n")
    if not lines:
        return None
    header = lines[0]
    data_lines = []
    for line in lines[1:]:
        stripped = line.strip()
        if not stripped:
            continue
        for col in stripped.split("\t"):
            if col.strip() in keep_claves:
                data_lines.append(line)
                break
    if not data_lines:
        return None
    return "\n".join([header] + data_lines)


# ─── Modal Scraping (Ventas / Emitidos) ──────────────────────────────────────


def scrape_modals_from_table(
    page: Page,
    tipo: str,
    old_claves: set[str],
    clave_to_line: dict[str, str],
    voucher_label: str = "",
) -> tuple[list[dict], list[dict]]:
    """
    For ventas (emitidos): iterate the results table and for each old clave,
    click the clave de acceso link (column 3) to open the detail modal,
    extract the data, and close it.

    Returns (xmls, modal_entries):
      - xmls: entries where the modal contained downloadable XML
      - modal_entries: entries where data was scraped from the modal HTML
    """
    if not old_claves:
        return [], []

    table_id = get_table_id(tipo)
    xmls: list[dict] = []
    modal_entries: list[dict] = []
    remaining = set(old_claves)
    page_num = 1

    progress(
        "modal-scrape",
        f"Extrayendo datos de modal para {len(remaining)} comprobantes emitidos...",
    )

    # Close any stale dialogs left open from previous iterations or runs
    _close_modal(page)

    while remaining:
        progress("modal-scrape", f"Página {page_num}, pendientes: {len(remaining)}...")

        rows_info = page.evaluate(
            """(tblId) => {
            const tbody = document.getElementById(tblId);
            if (!tbody) return [];
            const rows = tbody.querySelectorAll('tr');
            return Array.from(rows).map((row, i) => {
                const cells = row.querySelectorAll('td');
                // Find clave de acceso (49 digits) in any column
                let clave = null;
                let claveColIdx = -1;
                for (let ci = 0; ci < cells.length; ci++) {
                    const t = cells[ci].textContent?.trim();
                    if (t && /^\\d{49}$/.test(t)) { clave = t; claveColIdx = ci; break; }
                }
                const claveCell = claveColIdx >= 0 ? cells[claveColIdx] : null;
                const hasLink = claveCell
                    ? !!(claveCell.querySelector('a, button, span.ui-link, [onclick]') || claveCell.closest('[onclick]'))
                    : false;
                return { rowIndex: i, clave, claveColIdx, hasLink };
            });
        }""",
            table_id,
        )

        for row_info in rows_info:
            clave = row_info.get("clave")
            if not clave or clave not in remaining:
                continue

            progress("modal-scrape", f"Abriendo modal ...{clave[-10:]}...")
            result = _click_and_scrape_modal(
                page, table_id, row_info["rowIndex"], voucher_label
            )

            if result is None:
                progress(
                    "modal-scrape", f"No se pudo abrir modal para ...{clave[-10:]}"
                )
                remaining.discard(clave)
                continue

            result["clave"] = clave
            # Attach txt line so PHP has the financial data from the report
            result["txt_line"] = clave_to_line.get(clave, "")

            if "xml" in result:
                xmls.append({"clave": clave, "xml": result["xml"]})
                progress(
                    "modal-scrape",
                    f"XML obtenido del modal ({len(result['xml'])} bytes)",
                )
            else:
                modal_entries.append(result)
                progress(
                    "modal-scrape", f"Datos extraídos del modal para ...{clave[-10:]}"
                )

            remaining.discard(clave)
            random_delay(0.5, 1.5)

        has_next = page.evaluate(
            """() => !!document.querySelector('.ui-paginator-next:not(.ui-state-disabled)')"""
        )
        if not has_next:
            break

        page.click(".ui-paginator-next:not(.ui-state-disabled)")
        random_delay(2, 4)
        page_num += 1

    progress(
        "modal-scrape",
        f"Procesados: {len(xmls)} XMLs + {len(modal_entries)} scrapeados de {len(old_claves)} solicitados",
    )
    return xmls, modal_entries


def _click_and_scrape_modal(
    page: Page, table_id: str, row_index: int, voucher_label: str = ""
) -> dict | None:
    """
    Click the clave de acceso link (column 3) of a table row, wait for the
    SRI 'Detalle del Comprobante' modal to open, extract the required fields,
    close the modal, and return the structured data.

    Fields extracted (based on actual SRI modal labels):
      clave_acceso, establecimiento, punto_emision, secuencial,
      tipo_identificacion_comprador, razon_social_comprador,
      identificacion_comprador, total_sin_impuestos, total_descuento,
      importe_total, impuestos (list of {codigo, base_imponible, valor})

    Returns {"xml": "..."} if raw XML was found in the modal, or the structured
    dict above otherwise. Returns None if modal could not be opened/scraped.
    """
    clicked = page.evaluate(
        """({tblId, rowIndex}) => {
        const tbody = document.getElementById(tblId);
        if (!tbody) return { ok: false, reason: 'tabla no encontrada' };
        const rows = tbody.querySelectorAll('tr');
        const row = rows[rowIndex];
        if (!row) return { ok: false, reason: 'fila no encontrada rowIndex=' + rowIndex };
        const cells = row.querySelectorAll('td');

        // Find the cell that contains the 49-digit clave (any column)
        let targetCell = null;
        let targetColIdx = -1;
        for (let i = 0; i < cells.length; i++) {
            const t = cells[i].textContent?.trim();
            if (t && /^\d{49}$/.test(t)) {
                targetCell = cells[i];
                targetColIdx = i;
                break;
            }
        }

        if (!targetCell) return { ok: false, reason: 'clave no encontrada en ninguna celda' };

        // Try to click a link inside the clave cell first
        const claveLink = targetCell.querySelector('a, button, span.ui-link, [onclick]')
            || (targetCell.closest('[onclick]') ? targetCell : null);

        if (claveLink) {
            claveLink.click();
            return { ok: true, colIdx: targetColIdx, method: 'clave_link' };
        }

        // Clave cell has no link (e.g. retenciones) — scan other cells for a detail/view button
        let actionBtn = null;
        for (let i = cells.length - 1; i >= 0; i--) {
            if (cells[i] === targetCell) continue;
            const btn = cells[i].querySelector(
                'a[onclick], button, span[class*="ui-icon-search"], span[class*="ui-icon-info"], ' +
                'span[class*="ui-icon-eye"], .ui-button, a.ui-commandlink'
            );
            if (btn) { actionBtn = btn; break; }
        }

        if (actionBtn) {
            actionBtn.click();
            return { ok: true, colIdx: targetColIdx, method: 'action_button' };
        }

        // Last fallback: click the cell itself
        targetCell.click();
        return { ok: true, colIdx: targetColIdx, method: 'cell_click' };
    }""",
        {"tblId": table_id, "rowIndex": row_index},
    )

    if not clicked.get("ok"):
        return None

    if clicked.get("method") and clicked["method"] != "clave_link":
        progress(
            "modal-scrape",
            f"Click via {clicked['method']} (col {clicked.get('colIdx')})",
        )

    # Force-hide ALL existing detail dialogs so the NEW one is the only visible one
    page.evaluate("""() => {
        document.querySelectorAll('.ui-dialog').forEach(d => {
            const title = (d.querySelector('.ui-dialog-title')?.textContent ?? '').trim();
            if (title && title !== 'Espere por favor') {
                d.style.display = 'none';
            }
        });
    }""")

    # Wait for PrimeFaces to show the NEW dialog (display != 'none', has content rows)
    random_delay(1.0, 2.0)
    try:
        page.wait_for_function(
            """() => {
                for (const d of document.querySelectorAll('.ui-dialog')) {
                    if (d.style.display === 'none') continue;
                    const rect = d.getBoundingClientRect();
                    if (rect.width === 0 || rect.height === 0) continue;
                    const title = (d.querySelector('.ui-dialog-title')?.textContent ?? '').trim();
                    if (!title || title === 'Espere por favor') continue;
                    if (d.querySelectorAll('tr').length > 5) return true;
                }
                return false;
            }""",
            timeout=10000,
        )
    except Exception:
        pass  # If dialog never appears, modal_data will be None below

    # Check if dialog is actually visible before attempting extraction
    dialog_visible = page.evaluate("""() => {
        for (const d of document.querySelectorAll('.ui-dialog')) {
            if (d.style.display === 'none') continue;
            if (d.getAttribute('aria-hidden') === 'true') continue;
            const rect = d.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0) {
                const title = d.querySelector('.ui-dialog-title')?.textContent?.trim() || '(sin título)';
                return title;
            }
        }
        return null;
    }""")

    if dialog_visible:
        progress("modal-scrape", f"Modal abierto: '{dialog_visible}'")
    else:
        progress("modal-scrape", "Modal no visible después de espera")

    modal_data = page.evaluate(
        """(voucherLabel) => {
        // ── Find the visible PrimeFaces dialog ──
        // We force-set display:none on stale dialogs before each click, so the only
        // dialog WITHOUT display:none after the click is the newly opened one.
        // Do NOT check aria-hidden — PrimeFaces toggles it during AJAX transitions.
        function findDialog() {
            let best = null;
            for (const d of document.querySelectorAll('.ui-dialog')) {
                if (d.style.display === 'none') continue;
                const rect = d.getBoundingClientRect();
                if (rect.width === 0 || rect.height === 0) continue;
                const title = (d.querySelector('.ui-dialog-title')?.textContent ?? '').trim();
                if (!title || title === 'Espere por favor') continue;
                best = d;
            }
            return best;
        }

        const modal = findDialog();
        if (!modal) return null;

        // ── Resolve content root (dialog may render content inside an iframe) ──
        let contentRoot = modal;
        const modalIframe = modal.querySelector('iframe');
        if (modalIframe) {
            try {
                const iframeDoc = modalIframe.contentDocument || modalIframe.contentWindow?.document;
                if (iframeDoc && iframeDoc.body) contentRoot = iframeDoc.body;
            } catch(e) {}
        }

        // ── Helpers ──
        function num(s) {
            if (s === null || s === undefined) return 0;
            return parseFloat(String(s).trim().replace(',', '.')) || 0;
        }

        // Find the value cell by exact label text in any 2-column table row
        function byLabel(labelText) {
            for (const row of contentRoot.querySelectorAll('tr')) {
                const cells = row.querySelectorAll('td');
                if (cells.length < 2) continue;
                if ((cells[0].textContent ?? '').trim() === labelText) {
                    return (cells[1].textContent ?? '').trim();
                }
            }
            return '';
        }

        // ── Retenciones branch ──
        if (voucherLabel === 'Retencion') {
            const data = {
                clave_acceso:          byLabel('Clave de acceso'),
                establecimiento:       byLabel('Establecimiento'),
                punto_emision:         byLabel('Punto de emisión'),
                secuencial:            byLabel('Secuencial'),
                tipo_id_sujeto:        byLabel('Tipo de Id de Sujeto Retenido'),
                id_sujeto:             byLabel('Id de Sujeto Retenido'),
                razon_social_sujeto:   byLabel('Razón Social de Sujeto Retenido'),
                retenciones:           [],
            };

            // Find the retenciones detail table by header containing "Porcentaje"
            for (const tbl of contentRoot.querySelectorAll('table')) {
                const ths = Array.from(tbl.querySelectorAll('th'))
                    .map(th => (th.textContent ?? '').trim());
                if (!ths.some(h => h.includes('Porcentaje') || h.includes('Retenido'))) continue;

                // Map column headers to indexes dynamically.
                // NOTE: check 'Fecha' BEFORE 'Doc. Sustento' — "Fecha Doc. Sustento"
                // would otherwise match the Doc.Sustento rule and overwrite idx.doc.
                const idx = {};
                ths.forEach((h, i) => {
                    if (h === 'Impuesto') idx.impuesto = i;
                    else if (h.includes('Base Imponible')) idx.base = i;
                    else if (h.includes('Porcentaje')) idx.pct = i;
                    else if (h.includes('Valor') && h.includes('Retenido')) idx.val = i;
                    else if (h.includes('Fecha')) idx.fecha = i;
                    else if (h.includes('Doc. Sustento') || h.includes('Número Doc') || h.includes('Num')) idx.doc = i;
                });

                for (const row of tbl.querySelectorAll('tr')) {
                    const cells = row.querySelectorAll('td');
                    if (cells.length < 4) continue;
                    const impuesto = idx.impuesto !== undefined
                        ? (cells[idx.impuesto]?.textContent ?? '').trim() : '';
                    // Skip paginator/navigation rows (RichFaces scroller footer «»)
                    if (!impuesto || /[«»]/.test(impuesto)) continue;
                    data.retenciones.push({
                        impuesto,
                        base_imponible:      num(idx.base  !== undefined ? cells[idx.base]?.textContent  : 0),
                        porcentaje_retenido: num(idx.pct   !== undefined ? cells[idx.pct]?.textContent   : 0),
                        valor_retenido:      num(idx.val   !== undefined ? cells[idx.val]?.textContent   : 0),
                        num_doc_sustento:    idx.doc   !== undefined ? (cells[idx.doc]?.textContent   ?? '').trim() : '',
                        fecha_doc_sustento:  idx.fecha !== undefined ? (cells[idx.fecha]?.textContent ?? '').trim() : '',
                    });
                }
                break;
            }

            if (!data.id_sujeto && !data.clave_acceso) return null;
            return data;
        }

        // ── Regular comprobante branch ──
        const data = {
            clave_acceso:                    byLabel('Clave de acceso'),
            establecimiento:                 byLabel('Establecimiento'),
            punto_emision:                   byLabel('Punto de emisión'),
            secuencial:                      byLabel('Secuencial'),
            tipo_identificacion_comprador:   byLabel('Tipo Identificación Comprador'),
            razon_social_comprador:          byLabel('Razón Social Comprador'),
            identificacion_comprador:        byLabel('Identificación Comprador'),
            total_sin_impuestos:             num(byLabel('Total Sin impuestos')),
            total_descuento:                 num(byLabel('Total Descuento')),
            importe_total:                   num(byLabel('Importe Total')),
            impuestos:                       [],
        };

        // ── Totales por Impuesto table ──
        for (const tbl of contentRoot.querySelectorAll('table')) {
            const headerTexts = Array.from(tbl.querySelectorAll('th'))
                .map(th => (th.textContent ?? '').trim());
            if (
                headerTexts.includes('Código porcentaje') &&
                headerTexts.includes('Base Imponible')
            ) {
                for (const row of tbl.querySelectorAll('tr')) {
                    const cells = row.querySelectorAll('td');
                    // Row format: Nro | Impuesto | Código porcentaje | Base Imponible | Valor
                    if (cells.length < 5) continue;
                    const codigo = num(cells[2].textContent);
                    const base   = num(cells[3].textContent);
                    const valor  = num(cells[4].textContent);
                    if (codigo > 0 || base > 0) {
                        data.impuestos.push({ codigo, base_imponible: base, valor });
                    }
                }
                break;
            }
        }

        if (!data.identificacion_comprador && !data.clave_acceso) return null;
        return data;
    }""",
        voucher_label,
    )

    if modal_data is None:
        progress("modal-scrape", "No se pudieron extraer datos del modal (null)")
        # Dump ALL dialogs to diagnose which one is being picked and why it's empty
        debug = page.evaluate("""() => {
            const all = [];
            document.querySelectorAll('.ui-dialog').forEach((d, i) => {
                const rect = d.getBoundingClientRect();
                const hidden = d.style.display === 'none' || d.getAttribute('aria-hidden') === 'true';
                const title = d.querySelector('.ui-dialog-title')?.textContent?.trim() || '(sin título)';
                const trCount = d.querySelectorAll('tr').length;
                const iframeCount = d.querySelectorAll('iframe').length;
                const iframeSrcs = Array.from(d.querySelectorAll('iframe')).map(f => f.src || f.name || '?');
                const textSnippet = d.textContent?.trim().substring(0, 80) || '';

                // Try iframe content
                let iframeRows = 0;
                for (const f of d.querySelectorAll('iframe')) {
                    try {
                        const doc = f.contentDocument || f.contentWindow?.document;
                        if (doc) iframeRows += doc.querySelectorAll('tr').length;
                    } catch(e) {}
                }

                all.push({
                    index: i,
                    hidden,
                    title,
                    width: Math.round(rect.width),
                    height: Math.round(rect.height),
                    trCount,
                    iframeCount,
                    iframeSrcs,
                    iframeRows,
                    textSnippet,
                });
            });
            return all;
        }""")
        for d in debug:
            progress(
                "modal-scrape",
                f"Dialog[{d['index']}] hidden={d['hidden']} title='{d['title']}' "
                f"size={d['width']}x{d['height']} trs={d['trCount']} "
                f"iframes={d['iframeCount']}(rows={d['iframeRows']}) "
                f"text='{d['textSnippet'][:60]}'",
            )
    elif voucher_label == "Retencion":
        clave = modal_data.get("clave_acceso", "")
        sujeto = modal_data.get("id_sujeto", "")
        razon = modal_data.get("razon_social_sujeto", "")
        n_ret = len(modal_data.get("retenciones", []))
        progress(
            "modal-scrape",
            f"Datos extraídos: clave={clave[:20]}... sujeto={sujeto} razon='{razon}' retenciones={n_ret}",
        )
        for i, ret in enumerate(modal_data.get("retenciones", [])):
            progress(
                "modal-scrape",
                f"  Ret {i + 1}: impuesto={ret.get('impuesto')} base={ret.get('base_imponible')} "
                f"pct={ret.get('porcentaje_retenido')}% valor={ret.get('valor_retenido')} "
                f"doc={ret.get('num_doc_sustento')} fecha={ret.get('fecha_doc_sustento')}",
            )
    else:
        clave = modal_data.get("clave_acceso", "")
        comprador = modal_data.get("identificacion_comprador", "")
        total = modal_data.get("importe_total", 0)
        progress(
            "modal-scrape",
            f"Datos extraídos: clave={clave[:20]}... comprador={comprador} total={total}",
        )

    _close_modal(page)
    return modal_data


def _close_modal(page: Page) -> None:
    """Close ALL open SRI PrimeFaces dialogs and overlays."""
    # 1. Click all visible close buttons via Playwright (reliable real click)
    try:
        close_btns = page.locator(".ui-dialog:visible .ui-dialog-titlebar-close").all()
        for btn in close_btns:
            try:
                btn.click(timeout=1000)
                random_delay(0.1, 0.2)
            except Exception:
                pass
    except Exception:
        pass

    # 2. Press Escape to dismiss any remaining dialog
    try:
        page.keyboard.press("Escape")
        random_delay(0.2, 0.4)
    except Exception:
        pass

    # 3. Force-hide all remaining dialogs and overlays via JS
    try:
        page.evaluate("""() => {
            document.querySelectorAll('.ui-dialog').forEach(d => {
                d.style.display = 'none';
                d.setAttribute('aria-hidden', 'true');
            });
            document.querySelectorAll('.ui-widget-overlay, .ui-blockui').forEach(o => {
                o.style.display = 'none';
            });
        }""")
    except Exception:
        pass
    # Give PrimeFaces a moment to settle after force-close
    random_delay(0.3, 0.5)

    random_delay(0.2, 0.4)


# ─── XML Download from Table ──────────────────────────────────────────────────


def download_xmls_from_table(page: Page, tipo: str, old_claves: set[str]) -> list[dict]:
    """
    Iterate the results table and for each row that has an XML download button,
    click it with a real Playwright click and capture the XML response.

    Detection strategy: querySelectorAll('a[id$=":lnkXml"]') finds all XML buttons
    directly — no column-index guessing, no data-ri construction, no getElementById.
    Clave is extracted from the closest <tr> by scanning <a> text for 49 digits.
    """
    if not old_claves:
        return []

    results = []
    page_num = 1

    progress(
        "xml-tabla",
        f"Descargando XMLs de tabla para {len(old_claves)} comprobantes...",
    )

    while True:
        progress("xml-tabla", f"Página {page_num}...")

        # Collect all XML button IDs and associated claves visible on this page.
        # Done in a single evaluate so we can iterate safely before any clicks.
        items = page.evaluate("""() => {
            const result = [];
            const xmlLinks = document.querySelectorAll('a[id$=":lnkXml"]');
            for (const link of xmlLinks) {
                const row = link.closest('tr');
                let clave = null;
                if (row) {
                    // Scan every <a> in the row whose text is exactly 49 digits
                    for (const a of row.querySelectorAll('a')) {
                        const t = (a.textContent ?? '').trim();
                        if (/^\\d{49}$/.test(t)) { clave = t; break; }
                    }
                    // Fallback: hidden inputs
                    if (!clave) {
                        for (const inp of row.querySelectorAll('input[type="hidden"]')) {
                            const v = (inp.value ?? '').trim();
                            if (/^\\d{49}$/.test(v)) { clave = v; break; }
                        }
                    }
                }
                result.push({ linkId: link.id, clave });
            }
            return result;
        }""")

        progress(
            "xml-tabla", f"Página {page_num}: {len(items)} botones XML encontrados"
        )

        for item in items:
            link_id = item.get("linkId", "")
            clave = item.get("clave")

            if not link_id:
                continue

            if not clave:
                progress("xml-tabla", f"Sin clave en fila de {link_id}, omitiendo")
                continue

            if clave not in old_claves:
                progress("xml-tabla", f"Clave ≤30d, saltando XML ...{clave[-10:]}")
                continue

            progress("xml-tabla", f"Click en {link_id} (clave ...{clave[-10:]})...")
            xml_content = _capture_xml_by_link_id(page, link_id)

            if xml_content:
                results.append({"clave": clave, "xml": xml_content})
                progress("xml-tabla", f"XML capturado ({len(xml_content)} bytes)")
            else:
                progress("xml-tabla", f"No se pudo capturar XML para ...{clave[-10:]}")

            random_delay(0.5, 1.5)

        has_next = page.evaluate(
            """() => !!document.querySelector('.ui-paginator-next:not(.ui-state-disabled)')"""
        )
        if not has_next:
            break

        page.click(".ui-paginator-next:not(.ui-state-disabled)")
        random_delay(2, 4)
        page_num += 1

    progress(
        "xml-tabla",
        f"XMLs descargados: {len(results)} de {len(old_claves)} solicitados",
    )
    return results


def _capture_xml_by_link_id(page: Page, xml_link_id: str) -> str | None:
    """Click an XML download link using a real Playwright click and capture the response.

    The onclick calls mojarra.jsfcljs (JSF form POST). A JS .click() via page.evaluate
    does NOT reliably trigger JSF form submissions in Playwright — a real browser-level
    click via page.locator().click() is required to fire the onclick handler properly.

    Selector uses [id='...'] attribute form to avoid CSS colon-escaping issues.
    """
    captured = {"content": None}

    def on_xml_response(response):
        content_type = response.headers.get("content-type", "").lower()
        content_disp = response.headers.get("content-disposition", "").lower()
        if "xml" in content_type or (
            "attachment" in content_disp and ".xml" in content_disp
        ):
            try:
                raw = response.body()
                try:
                    captured["content"] = raw.decode("utf-8")
                except UnicodeDecodeError:
                    captured["content"] = raw.decode("latin-1")
            except Exception:
                pass

    # [id='...'] attribute selector avoids CSS colon-escaping for IDs like
    # "frmPrincipal:tablaCompRecibidos:250:lnkXml"
    selector = f"[id='{xml_link_id}']"
    locator = page.locator(selector).first

    page.on("response", on_xml_response)
    try:
        # Attempt 1: real Playwright click + expect_download (JSF sends file as attachment)
        try:
            with page.expect_download(timeout=15000) as dl_info:
                # force=True bypasses actionability checks (visibility, overlap, etc.)
                locator.click(timeout=5000, force=True)
            dl = dl_info.value
            tmp_path = Path(dl.path()) if dl.path() else None
            if tmp_path and tmp_path.exists():
                raw = tmp_path.read_bytes()
                try:
                    return raw.decode("utf-8")
                except UnicodeDecodeError:
                    return raw.decode("latin-1")
        except Exception as e:
            progress(
                "xml-tabla", f"expect_download falló ({e}), intentando interceptor..."
            )

        # Attempt 2: response interceptor — JSF may stream XML inline (no download dialog)
        if not captured["content"]:
            try:
                locator.click(timeout=5000, force=True)
            except Exception as e:
                progress("xml-tabla", f"Click falló: {e}")
            for _ in range(20):
                time.sleep(0.5)
                if captured["content"]:
                    break

        return captured.get("content")
    finally:
        page.remove_listener("response", on_xml_response)


# ─── Scrape Table ─────────────────────────────────────────────────────────────


def scrape_table_data(page: Page, tipo: str) -> list[str]:
    table_id = get_table_id(tipo)
    all_claves = []
    page_num = 1

    progress("scrape", "Extrayendo datos de la tabla...")

    while True:
        progress("scrape", f"Procesando página {page_num}...")

        page_data = page.evaluate(
            """(tblId) => {
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
        }""",
            table_id,
        )

        all_claves.extend(page_data["claves"])
        progress(
            "scrape",
            f"Página {page_num}: {len(page_data['claves'])} claves (total: {len(all_claves)})",
        )

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
    parser = argparse.ArgumentParser(
        description="SRI Scraper (Python) — Stealth + Human Simulation"
    )
    parser.add_argument("--ruc", help="RUC del contribuyente")
    parser.add_argument("--password", help="Contraseña SRI")
    parser.add_argument(
        "--type", dest="tipo", default="compras", choices=["compras", "ventas", "ambos"]
    )
    parser.add_argument("--year", type=int, default=2026)
    parser.add_argument("--month", type=int, default=4)
    parser.add_argument(
        "--mode", default="txt_download", choices=["txt_download", "table_scrape"]
    )
    parser.add_argument("--headless", action="store_true", default=True)
    parser.add_argument(
        "--visible", action="store_true", help="Abrir navegador visible"
    )
    parser.add_argument(
        "--download-dir", dest="download_dir", default="/tmp/sri-scrape-py"
    )
    parser.add_argument(
        "--user-data-dir",
        dest="user_data_dir",
        default=None,
        help="Directorio para persistir sesión del navegador (cookies, localStorage)",
    )
    return parser.parse_args()


def load_config() -> dict:
    """Load config from stdin (JSON) if piped, otherwise from CLI args."""
    if not sys.stdin.isatty():
        raw = sys.stdin.read().strip()
        if raw:
            return json.loads(raw)

    args = parse_args()
    if not args.ruc or not args.password:
        print(
            'Usage: echo \'{"ruc":"...","password":"..."}\' | python test-scraper.py',
            file=sys.stderr,
        )
        print("   or: python test-scraper.py --ruc=... --password=...", file=sys.stderr)
        sys.exit(1)

    return {
        "ruc": args.ruc,
        "password": args.password,
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
    day = config.get("day", 0) or 0
    mode = config.get("mode", "txt_download")
    download_dir = Path(config.get("downloadDir", "/tmp/sri-scrape-py"))
    headless = config.get("headless", True)
    user_data_dir = config.get("userDataDir")
    skip_claves_set = set(config.get("skipClaves") or [])

    # Tipos de comprobante seleccionados por el usuario (valores: "1", "3", "4")
    selected_voucher_values = set(config.get("voucherTypes") or ["1", "3", "4"])

    download_dir.mkdir(parents=True, exist_ok=True)

    # ── Log strategy info ──
    progress(
        "init",
        f"Stealth: {'v' + str(STEALTH_VERSION) if STEALTH_VERSION else 'NO INSTALADO'}",
    )

    if STEALTH_VERSION == 0:
        progress(
            "init",
            "ADVERTENCIA: playwright-stealth no instalado. Instala con: pip install playwright-stealth",
        )

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
        "locale": "es-419",
        "timezone_id": "America/Guayaquil",
        "user_agent": CHROME_USER_AGENT,
        "accept_downloads": True,
        "extra_http_headers": {
            "Accept-Language": "es-419,es;q=0.9",
            "sec-ch-ua": CH_UA_SEC,
            "sec-ch-ua-mobile": "?0",
            "sec-ch-ua-platform": CH_UA_PLATFORM,
            "sec-ch-ua-full-version-list": CH_UA_SEC_FULL,
        },
    }

    # ── Launch with stealth ──
    if STEALTH_VERSION == 2:
        stealth = Stealth(
            navigator_languages_override=("es-419", "es"),
        )
        pw_context_manager = stealth.use_sync(sync_playwright())
    else:
        pw_context_manager = sync_playwright()

    with pw_context_manager as pw:
        # Present the latest STABLE consumer Chrome so reCAPTCHA v3 sees an
        # up-to-date browser (the bundled engine version lags stable).
        fp = build_chrome_fingerprint(fetch_latest_chrome_version())
        progress("init", f"Fingerprint Chrome {fp['full_version']} (última estable)")
        context_opts["user_agent"] = fp["user_agent"]
        context_opts["extra_http_headers"]["sec-ch-ua"] = fp["sec_ch_ua"]
        context_opts["extra_http_headers"]["sec-ch-ua-platform"] = fp["platform"]
        context_opts["extra_http_headers"]["sec-ch-ua-full-version-list"] = fp[
            "sec_ch_ua_full"
        ]

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

            # Step 2: Navigate (for 'ambos' we navigate per section inside the mode handler)
            if tipo != "ambos":
                navigate_to_comprobantes(page, tipo)

            # Step 3: Process
            if mode == "txt_download":
                files = []

                sections = ["compras", "ventas"] if tipo == "ambos" else [tipo]

                for section in sections:
                    if tipo == "ambos":
                        navigate_to_comprobantes(page, section)

                    base_types = (
                        COMPRAS_VOUCHER_TYPES if section == "compras" else VOUCHER_TYPES
                    )
                    active_voucher_types = [
                        vt
                        for vt in base_types
                        if vt["value"] in selected_voucher_values
                    ]
                    skip_set = skip_claves_set or None

                    for i, vt in enumerate(active_voucher_types):
                        # Para ventas, recargar entre tipos de comprobante para resetear el formulario.
                        if i > 0 and section == "ventas":
                            navigate_to_comprobantes(page, section)

                        try:
                            result = download_for_voucher_type(
                                page,
                                vt,
                                year,
                                month,
                                download_dir,
                                section,
                                day,
                                skip_set,
                            )
                            result["section"] = section
                            files.append(result)

                            if result["status"] == "downloaded":
                                progress(
                                    "summary",
                                    f"[{section}] {vt['label']}: {result['rows']} registros descargados",
                                )
                            else:
                                progress(
                                    "summary",
                                    f"[{section}] {vt['label']}: {result['status']}",
                                )

                        except Exception as e:
                            progress(vt["label"], f"[{section}] Error: {e}")
                            files.append(
                                {
                                    "type": vt["label"],
                                    "section": section,
                                    "status": "error",
                                    "content": None,
                                    "error": str(e),
                                }
                            )

                        random_delay(1, 3)

                emit("result", {"mode": "txt_download", "files": files})

            elif mode == "table_scrape":
                all_claves = []

                sections = ["compras", "ventas"] if tipo == "ambos" else [tipo]

                for section in sections:
                    if tipo == "ambos":
                        navigate_to_comprobantes(page, section)

                    base_types = (
                        COMPRAS_VOUCHER_TYPES if section == "compras" else VOUCHER_TYPES
                    )
                    active_voucher_types = [
                        vt
                        for vt in base_types
                        if vt["value"] in selected_voucher_values
                    ]

                    for i, vt in enumerate(active_voucher_types):
                        if i > 0 and section == "ventas":
                            navigate_to_comprobantes(page, section)

                        try:
                            search_ok = search_with_captcha(
                                page, vt, year, month, section
                            )
                            if search_ok:
                                claves = scrape_table_data(page, section)
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
