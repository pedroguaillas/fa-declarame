#!/usr/bin/env python3
"""
SPIKE — ¿Se puede descargar un XML del SRI por HTTP directo (sin click, sin captcha)
reusando la sesión (JSESSIONID + ViewState) que dejó el browser tras la consulta?

Objetivo: validar la arquitectura estilo SRIFlow — browser solo para login+consulta,
luego descargas por HTTP paralelo.

Uso (corre TÚ con tus credenciales; el password no pasa por el asistente):

    cd scripts/sri-agent
    echo '{"ruc":"TU_RUC","password":"TU_PASS","type":"compras","year":2026,"month":4}' \
        | python spike-http-download.py

Resultado esperado:
  - Si imprime  RESULTADO: XML OBTENIDO POR HTTP  → arquitectura híbrida viable.
  - Si imprime  RESULTADO: HTTP NO DEVOLVIÓ XML   → SRI exige el postback vía browser.
"""

from __future__ import annotations

import json
import sys
from importlib.util import module_from_spec, spec_from_file_location
from pathlib import Path

# ── Cargar test-scraper.py como módulo (mismo truco que server.py) ──
_spec = spec_from_file_location("scraper", Path(__file__).parent / "test-scraper.py")
scraper = module_from_spec(_spec)
_spec.loader.exec_module(scraper)


def log(msg: str) -> None:
    print(f"[spike] {msg}", flush=True)


def main() -> int:
    config = json.load(sys.stdin)
    ruc = config["ruc"]
    password = config["password"]
    tipo = config.get("type", "compras")
    year = config.get("year", 2026)
    month = config.get("month", 4)

    # Un solo tipo de comprobante para el spike: Factura recibida.
    voucher_type = {"value": "1", "label": "Factura"}

    context_opts = {
        "viewport": {"width": 1366, "height": 768},
        "locale": "es-419",
        "timezone_id": "America/Guayaquil",
        "accept_downloads": True,
    }

    launch_args = [
        "--no-sandbox",
        "--disable-blink-features=AutomationControlled",
        "--lang=es-EC,es",
        "--window-size=1366,768",
    ]

    if scraper.STEALTH_VERSION == 2:
        stealth = scraper.Stealth(navigator_languages_override=("es-419", "es"))
        pw_cm = stealth.use_sync(scraper.sync_playwright())
    else:
        pw_cm = scraper.sync_playwright()

    with pw_cm as pw:
        fp = scraper.build_chrome_fingerprint(scraper.fetch_latest_chrome_version())
        context_opts["user_agent"] = fp["user_agent"]
        context_opts["extra_http_headers"] = {
            "Accept-Language": "es-419,es;q=0.9",
            "sec-ch-ua": fp["sec_ch_ua"],
            "sec-ch-ua-mobile": "?0",
            "sec-ch-ua-platform": fp["platform"],
            "sec-ch-ua-full-version-list": fp["sec_ch_ua_full"],
        }

        browser = pw.chromium.launch(headless=False, args=launch_args)
        context = browser.new_context(**context_opts)
        context.add_init_script(scraper.build_stealth_init_script(fp))
        page = context.new_page()
        if scraper.STEALTH_VERSION == 1:
            scraper.stealth_sync(page)

        # ── 1. Login ──
        log("Login...")
        if not scraper.login(page, ruc, password):
            log("LOGIN FALLÓ")
            return 1
        log("Login OK")

        # ── 2. Consulta (pasa captcha) ──
        log("Navegando a comprobantes + consulta (captcha)...")
        scraper.navigate_to_comprobantes(page, tipo)
        if not scraper.search_with_captcha(page, voucher_type, year, month, tipo, 0):
            log("CONSULTA/CAPTCHA FALLÓ — prueba otro mes con datos")
            return 1
        log("Consulta OK, tabla cargada")

        # ── 3. Extraer sesión: form frmPrincipal real + TODOS sus campos + link ──
        # El onclick real: mojarra.jsfcljs(getElementById('frmPrincipal'),
        #   {'frmPrincipal:...:lnkXml':'...:lnkXml'},'')  → submit COMPLETO, no ajax.
        # Replicamos serializando todos los campos del form + el param del link.
        info = page.evaluate(
            """() => {
                const link = document.querySelector("a[id$=':lnkXml']");
                if (!link) return { linkId: null };
                // Subir al <form> contenedor (frmPrincipal), no al primer form del DOM
                const form = link.closest('form');
                const fields = {};
                if (form) {
                    for (const el of form.querySelectorAll('input, select, textarea')) {
                        if (!el.name) continue;
                        if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) continue;
                        fields[el.name] = el.value;
                    }
                }
                return {
                    linkId: link.id,
                    onclick: link.getAttribute('onclick') || '',
                    formId: form ? form.id : null,
                    formAction: form ? form.action : null,
                    fields,
                    pageUrl: location.href,
                };
            }"""
        )

        if not info.get("linkId"):
            log("No hay link XML — tabla vacía?")
            return 1

        link_id = info["linkId"]
        log(f"Primer link XML id: {link_id}")
        log(f"Form id: {info['formId']}")
        log(f"Form action: {info['formAction']}")
        log(f"Page URL: {info['pageUrl']}")
        log(f"Campos serializados: {list(info['fields'].keys())}")

        cookies = context.cookies()
        log(f"Cookies (nombres únicos): {sorted(set(c['name'] for c in cookies))}")

        # ── 4. POST HTTP puro: todos los campos del form + param del link ──
        # mojarra añade el par {linkId: linkId} a los campos existentes y hace submit.
        post_data = dict(info["fields"])
        post_data[link_id] = link_id

        # action puede ser relativo → resolver contra la URL de la página
        from urllib.parse import urljoin

        action_url = urljoin(info["pageUrl"], info["formAction"] or "")
        log(f"POST → {action_url} ({len(post_data)} campos, sin click, sin captcha)...")

        resp = context.request.post(
            action_url,
            form=post_data,
            headers={
                "Content-Type": "application/x-www-form-urlencoded",
                "Referer": info["pageUrl"],
            },
        )

        status = resp.status
        ctype = resp.headers.get("content-type", "")
        cdisp = resp.headers.get("content-disposition", "")
        body = resp.body()
        head = body[:300].decode("utf-8", "replace")

        log(f"HTTP status: {status}")
        log(f"Content-Type: {ctype}")
        log(f"Content-Disposition: {cdisp}")
        log(f"Body[:300]: {head!r}")

        is_xml = (
            "xml" in ctype.lower()
            or ".xml" in cdisp.lower()
            or head.lstrip().startswith("<?xml")
            or "<factura" in head.lower()
            or "<autorizacion" in head.lower()
            or "<comprobante" in head.lower()
        )

        print("\n" + "=" * 60)
        if is_xml and status == 200:
            print("RESULTADO: XML OBTENIDO POR HTTP  ✅  → híbrido viable")
        else:
            print("RESULTADO: HTTP NO DEVOLVIÓ XML   ❌  → revisar body arriba")
            print("  (puede requerir params extra del onclick, o postback vía browser)")
        print("=" * 60)

        # Guardar el body completo para inspección si no fue XML
        out = Path(__file__).parent / "spike-response.bin"
        out.write_bytes(body)
        log(f"Body completo guardado en {out} ({len(body)} bytes)")

        try:
            input("\nENTER para cerrar el browser...")
        except EOFError:
            pass
        context.close()
        browser.close()

    return 0


if __name__ == "__main__":
    sys.exit(main())
