/**
 * SRI Electronic Documents Scraper
 *
 * Downloads comprobantes electrónicos from Ecuador's SRI portal.
 * Uses 2Captcha to solve reCAPTCHA (same approach as Bot.cs reference).
 *
 * Receives configuration via stdin as JSON:
 * {
 *   "ruc": "0201432432001",
 *   "password": "decrypted_pass",
 *   "type": "compras" | "ventas",
 *   "year": 2026,
 *   "month": 4,
 *   "mode": "txt_download" | "table_scrape",
 *   "captchaApiKey": "your-2captcha-key",
 *   "downloadDir": "/tmp/sri-scrape-xyz"
 * }
 */

import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
import { mkdirSync, readdirSync, readFileSync, unlinkSync } from 'fs';
import { join } from 'path';

puppeteer.use(StealthPlugin());

const SRI_URLS = {
    portal: 'https://srienlinea.sri.gob.ec/sri-en-linea/contribuyente/perfil',
    compras: 'https://srienlinea.sri.gob.ec/tuportal-internet/accederAplicacion.jspa?redireccion=57&idGrupo=55',
    ventas: 'https://srienlinea.sri.gob.ec/tuportal-internet/accederAplicacion.jspa?redireccion=60&idGrupo=55',
};

const VOUCHER_TYPES = [
    { value: '1', label: 'Factura' },
    { value: '3', label: 'NotaCredito' },
    { value: '4', label: 'NotaDebito' },
    { value: '6', label: 'Retencion' },
];

// 2Captcha REST API base URL (same as Bot.cs uses)
const TWOCAPTCHA_IN = 'https://2captcha.com/in.php';
const TWOCAPTCHA_RES = 'https://2captcha.com/res.php';

function emit(event, data) {
    console.log(JSON.stringify({ event, data }));
}

function progress(step, message) {
    // Log to stderr for diagnostics (PHP captures this)
    const ts = new Date().toISOString().substring(11, 19);
    process.stderr.write(`[${ts}] ${step}: ${message}\n`);
    emit('progress', { step, message });
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function readStdin() {
    const chunks = [];
    for await (const chunk of process.stdin) {
        chunks.push(chunk);
    }
    return JSON.parse(Buffer.concat(chunks).toString());
}

// --- 2Captcha REST API (matching Bot.cs exactly) ---
// Bot.cs sends to in.php with method=userrecaptcha, then polls res.php
// This avoids any SDK abstraction that might add unwanted params.

async function solveCaptchaRaw(apiKey, sitekey, pageUrl) {
    progress('captcha', `Enviando reCAPTCHA a 2Captcha (sitekey=${sitekey.substring(0, 10)}...)...`);

    // Send as standard v2 (NOT enterprise). Enterprise mode times out on 2Captcha
    // but standard v2 solves in ~20s. Confirmed by testing both modes.
    // This matches Bot.cs which also sends as method=userrecaptcha without enterprise flag.
    const inParams = new URLSearchParams({
        key: apiKey,
        method: 'userrecaptcha',
        googlekey: sitekey,
        pageurl: pageUrl,
        json: '1',
    });

    const inRes = await fetch(TWOCAPTCHA_IN, {
        method: 'POST',
        body: inParams,
    });
    const inData = await inRes.json();

    if (inData.status !== 1) {
        progress('captcha', `2Captcha submit error: ${inData.request}`);
        return null;
    }

    const captchaId = inData.request;
    progress('captcha', `Captcha enviado, ID=${captchaId}. Esperando solución...`);

    // Step 2: Poll for result (GET to res.php) — same as Bot.cs loop
    await delay(15000); // Wait 15s before first poll (2Captcha recommendation)

    for (let poll = 0; poll < 24; poll++) { // Max ~2 minutes of polling
        const resParams = new URLSearchParams({
            key: apiKey,
            action: 'get',
            id: captchaId,
            json: '1',
        });

        const resRes = await fetch(`${TWOCAPTCHA_RES}?${resParams}`);
        const resData = await resRes.json();

        if (resData.status === 1) {
            const token = resData.request;
            progress('captcha', `Token obtenido (${token.length} chars)`);
            return token;
        }

        if (resData.request !== 'CAPCHA_NOT_READY') {
            progress('captcha', `2Captcha error: ${resData.request}`);
            return null;
        }

        progress('captcha', `Esperando solución... (${(poll + 1) * 5}s)`);
        await delay(5000);
    }

    progress('captcha', '2Captcha timeout - no se recibió solución');
    return null;
}

// --- Login ---

async function login(page, ruc, password) {
    progress('login', 'Navegando al portal SRI...');

    // Retry navigation up to 3 times (SRI portal can be slow)
    for (let attempt = 1; attempt <= 3; attempt++) {
        try {
            await page.goto(SRI_URLS.portal, { waitUntil: 'networkidle2', timeout: 60000 });
            break;
        } catch (err) {
            if (attempt === 3) {
                emit('error', { code: 'NAV_TIMEOUT', message: `No se pudo cargar el portal SRI después de 3 intentos: ${err.message}` });
                return false;
            }
            progress('login', `Intento ${attempt} falló, reintentando...`);
            await delay(3000);
        }
    }

    await delay(2000);

    const usernameEl = await page.$('#usuario');
    const passwordEl = await page.$('#password');

    if (!usernameEl || !passwordEl) {
        emit('error', { code: 'LOGIN_FORM_NOT_FOUND', message: 'No se encontró el formulario de login del SRI' });
        return false;
    }

    progress('login', 'Ingresando credenciales...');
    await usernameEl.click({ clickCount: 3 });
    await usernameEl.type(ruc, { delay: 80 });
    await delay(500);

    await passwordEl.click({ clickCount: 3 });
    await passwordEl.type(password, { delay: 80 });
    await delay(500);

    const submitBtn = await page.$('#kc-login');
    if (submitBtn) {
        await submitBtn.click();
    } else {
        await passwordEl.press('Enter');
    }

    progress('login', 'Esperando redirección...');
    try {
        await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 });
    } catch {}

    await delay(1500);

    if (page.url().includes('/auth/')) {
        const errorText = await page.evaluate(() => {
            const el = document.querySelector('.alert-error, #input-error, .kc-feedback-text, .error-message');
            return el ? el.textContent.trim() : null;
        });
        emit('error', { code: 'LOGIN_FAILED', message: errorText || 'Credenciales inválidas' });
        return false;
    }

    progress('login', 'Login exitoso');
    return true;
}

// --- Navigate to Comprobantes ---

async function navigateToComprobantes(page, type) {
    const url = type === 'ventas' ? SRI_URLS.ventas : SRI_URLS.compras;
    const label = type === 'ventas' ? 'Comprobantes Emitidos' : 'Comprobantes Recibidos';

    progress('navigate', `Navegando a ${label}...`);

    for (let attempt = 1; attempt <= 3; attempt++) {
        try {
            await page.goto(url, { waitUntil: 'networkidle2', timeout: 60000 });
            break;
        } catch (err) {
            if (attempt === 3) throw err;
            progress('navigate', `Intento ${attempt} falló, reintentando...`);
            await delay(3000);
        }
    }

    await delay(3000);
    progress('navigate', `Página de ${label} cargada`);
}

// --- Extract sitekey dynamically from page (same as Bot.cs) ---

async function extractSitekey(page) {
    const sitekey = await page.evaluate(() => {
        // Bot.cs: document.getElementsByClassName('g-recaptcha')[0].getAttribute('data-sitekey')
        const el = document.getElementsByClassName('g-recaptcha')[0];
        if (el) return el.getAttribute('data-sitekey');

        // Fallback: look in any script or iframe
        const iframes = document.querySelectorAll('iframe[src*="recaptcha"]');
        for (const iframe of iframes) {
            const match = iframe.src.match(/[?&]k=([^&]+)/);
            if (match) return match[1];
        }

        // Fallback: look for cargarRecaptcha('container', 'sitekey') call (SRI specific)
        const scripts = document.querySelectorAll('script');
        for (const script of scripts) {
            const text = script.textContent || '';
            // Match cargarRecaptcha('recaptcha-container', "sitekey")
            const cargarMatch = text.match(/cargarRecaptcha\s*\([^,]+,\s*['"]([A-Za-z0-9_-]{40})['"]/);
            if (cargarMatch) return cargarMatch[1];
            // Generic sitekey pattern
            const match = text.match(/sitekey['":\s]+['"]([A-Za-z0-9_-]{40})['"]/);
            if (match) return match[1];
        }

        // Fallback: look in data attributes of any element
        const allElements = document.querySelectorAll('[data-sitekey]');
        if (allElements.length > 0) return allElements[0].getAttribute('data-sitekey');

        return null;
    });

    return sitekey;
}

// --- Inject token and trigger search ---
// rcBuscar is a PrimeFaces remoteCommand with { params: arguments[0] }.
// PrimeFaces serializes the entire form (frmPrincipal) including g-recaptcha-response.
// We set the token in the form field AND pass it to rcBuscar (same as Bot.cs).

async function injectTokenAndSearch(page, token) {
    // Step 1: Set token in g-recaptcha-response (Bot.cs does this)
    await page.evaluate((t) => {
        const el = document.getElementById('g-recaptcha-response');
        if (el) {
            el.value = t;
            el.innerHTML = t;
        }
        document.querySelectorAll('textarea[name="g-recaptcha-response"]').forEach(textarea => {
            textarea.value = t;
            textarea.innerHTML = t;
        });
    }, token);

    // Step 2: Call rcBuscar with token as argument (same as Bot.cs: rcBuscar('token'))
    await page.evaluate((t) => {
        rcBuscar(t);
    }, token);
}

// --- Wait for new file in download dir ---

async function waitForNewFile(downloadDir, existingFiles, timeoutMs = 30000) {
    const start = Date.now();
    while (Date.now() - start < timeoutMs) {
        await delay(500);
        const currentFiles = readdirSync(downloadDir);
        const newFiles = currentFiles.filter(
            f => !existingFiles.includes(f) && !f.endsWith('.crdownload') && !f.endsWith('.tmp')
        );
        if (newFiles.length > 0) {
            return newFiles[0];
        }
    }
    return null;
}

// --- Set filters on the comprobantes page ---

async function setFilters(page, voucherType, year, month) {
    await page.select('#frmPrincipal\\:ano', String(year));
    await delay(1500);

    await page.select('#frmPrincipal\\:mes', String(month));
    await delay(1500);

    await page.select('#frmPrincipal\\:dia', '0');
    await delay(1000);

    await page.select('#frmPrincipal\\:cmbTipoComprobante', voucherType.value);
    await delay(1000);
}

// --- Check page state after search ---

function getTableId(type) {
    return type === 'ventas'
        ? 'frmPrincipal:tablaCompEmitidos_data'
        : 'frmPrincipal:tablaCompRecibidos_data';
}

async function checkPageState(page, type) {
    const tableId = getTableId(type);

    return page.evaluate((tblId) => {
        // Check PrimeFaces messages for captcha error or no-data message
        const msgs = document.getElementById('formMessages:messages');
        const msgsText = msgs ? msgs.innerText : '';

        if (msgsText.includes('Captcha incorrecta')) {
            return { state: 'captcha_failed', detail: msgsText.substring(0, 100) };
        }
        if (msgsText.includes('No existen datos')) {
            return { state: 'no_results', detail: msgsText.substring(0, 100) };
        }

        // Check table for actual data rows
        const tbody = document.getElementById(tblId);
        if (tbody) {
            const rows = tbody.querySelectorAll('tr');
            if (rows.length > 0) {
                const firstText = rows[0]?.textContent?.trim() || '';
                const emptyMsg = tbody.querySelector('.ui-datatable-empty-message');
                if (!firstText.includes('No se encontraron') && !emptyMsg && firstText.length > 0) {
                    return { state: 'has_results', detail: `${rows.length} rows` };
                }
            }
        }

        // Check if PrimeFaces AJAX is still running
        let ajaxBusy = false;
        try {
            ajaxBusy = typeof PrimeFaces !== 'undefined' &&
                PrimeFaces.ajax &&
                PrimeFaces.ajax.Queue &&
                !PrimeFaces.ajax.Queue.isEmpty();
        } catch {}

        const blockUI = !!document.querySelector('.ui-blockui, .ui-blockui-content, .ui-widget-overlay');

        return {
            state: 'unknown',
            detail: `msgs=[${msgsText.substring(0, 60)}] ajaxBusy=${ajaxBusy} blockUI=${blockUI}`,
        };
    }, tableId);
}

// --- Search with 2Captcha for a single voucher type ---

async function searchWithCaptcha(page, voucherType, year, month, apiKey, type) {
    progress(voucherType.label, `Configurando filtros: año=${year}, mes=${month}, tipo=${voucherType.label}...`);
    await setFilters(page, voucherType, year, month);

    // Extract sitekey dynamically from the page (like Bot.cs)
    const sitekey = await extractSitekey(page);
    if (!sitekey) {
        progress(voucherType.label, 'ERROR: No se encontró el sitekey de reCAPTCHA en la página');
        // Try to get page HTML for debugging
        const debugInfo = await page.evaluate(() => {
            const recaptchaEls = document.getElementsByClassName('g-recaptcha');
            const iframes = document.querySelectorAll('iframe[src*="recaptcha"]');
            return {
                recaptchaCount: recaptchaEls.length,
                iframeCount: iframes.length,
                bodySnippet: document.body?.innerHTML?.substring(0, 500) || 'empty',
            };
        });
        progress(voucherType.label, `Debug: recaptchaEls=${debugInfo.recaptchaCount}, iframes=${debugInfo.iframeCount}`);
        return false;
    }

    progress(voucherType.label, `Sitekey extraído: ${sitekey}`);

    // Diagnostic: check rcBuscar function
    const diag = await page.evaluate(() => {
        const hasRcBuscar = typeof rcBuscar === 'function';
        const rcSource = hasRcBuscar ? rcBuscar.toString().substring(0, 300) : 'NOT_FOUND';
        const recaptchaEl = document.getElementById('g-recaptcha-response');
        return { hasRcBuscar, rcSource, hasRecaptchaEl: !!recaptchaEl };
    });
    progress(voucherType.label, `rcBuscar=${diag.hasRcBuscar}, g-recaptcha-response=${diag.hasRecaptchaEl}`);
    progress(voucherType.label, `rcBuscar source: ${diag.rcSource}`);

    if (!diag.hasRcBuscar) {
        progress(voucherType.label, 'ERROR: rcBuscar function not found on page');
        return false;
    }

    // Solve captcha (max 3 attempts, standard v2 — confirmed working via test)
    for (let attempt = 1; attempt <= 3; attempt++) {
        progress(voucherType.label, `Resolviendo captcha (intento ${attempt}/3)...`);

        const token = await solveCaptchaRaw(apiKey, sitekey, 'https://srienlinea.sri.gob.ec');

        if (!token) {
            progress(voucherType.label, `No se pudo resolver captcha en intento ${attempt}`);
            continue;
        }

        progress(voucherType.label, `Inyectando token y ejecutando rcBuscar (intento ${attempt})...`);

        try {
            await injectTokenAndSearch(page, token);
        } catch (err) {
            progress(voucherType.label, `Error al inyectar token: ${err.message}`);
            continue;
        }

        // Wait 5 seconds (same as Bot.cs: Thread.Sleep(5000))
        await delay(5000);

        // Check result
        const result = await checkPageState(page, type);
        progress(voucherType.label, `Estado: ${result.state} (${result.detail})`);

        if (result.state === 'has_results' || result.state === 'no_results') {
            progress(voucherType.label, `Búsqueda exitosa en intento ${attempt}`);
            return true;
        }

        if (result.state === 'captcha_failed') {
            progress(voucherType.label, `Captcha rechazado en intento ${attempt}`);
        } else {
            // Unknown state — wait a bit more, AJAX might still be processing
            progress(voucherType.label, `Estado desconocido, esperando 5s más...`);
            await delay(5000);

            const retry = await checkPageState(page, type);
            progress(voucherType.label, `Segundo chequeo: ${retry.state} (${retry.detail})`);

            if (retry.state === 'has_results' || retry.state === 'no_results') {
                return true;
            }
        }

        // Re-navigate for fresh captcha widget (important: Bot.cs does a fresh page for each type)
        if (attempt < 3) {
            progress(voucherType.label, 'Recargando página para nuevo intento...');
            await navigateToComprobantes(page, type);
            await setFilters(page, voucherType, year, month);
        }
    }

    progress(voucherType.label, 'Captcha falló después de 3 intentos');
    return false;
}

// --- Download for a single voucher type ---

async function downloadForVoucherType(page, voucherType, year, month, downloadDir, apiKey, type) {
    const searchOk = await searchWithCaptcha(page, voucherType, year, month, apiKey, type);

    if (!searchOk) {
        return { type: voucherType.label, status: 'captcha_failed', content: null };
    }

    const tableId = getTableId(type);

    const tableInfo = await page.evaluate((tblId) => {
        const tbody = document.getElementById(tblId);
        if (!tbody) return { found: false, rows: 0, message: 'Tabla no encontrada' };

        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) return { found: true, rows: 0, message: 'Sin registros' };

        const firstRowText = rows[0]?.textContent?.trim() || '';
        if (firstRowText.includes('No se encontraron') || firstRowText.includes('No existen') || rows[0]?.classList.contains('ui-datatable-empty-message')) {
            return { found: true, rows: 0, message: firstRowText.substring(0, 100) };
        }

        return { found: true, rows: rows.length, message: `${rows.length} registros encontrados` };
    }, tableId);

    progress(voucherType.label, `Resultado: ${tableInfo.message}`);

    if (tableInfo.rows === 0) {
        return { type: voucherType.label, status: 'no_records', content: null };
    }

    progress(voucherType.label, `${tableInfo.rows} registros, descargando reporte...`);

    const existingFiles = readdirSync(downloadDir);

    const clicked = await page.evaluate(() => {
        const links = document.querySelectorAll('a');
        for (const link of links) {
            const text = (link.textContent || '').toLowerCase();
            if (text.includes('descargar') && (text.includes('reporte') || text.includes('report'))) {
                link.click();
                return true;
            }
        }
        const buttons = document.querySelectorAll('button');
        for (const btn of buttons) {
            const text = (btn.textContent || '').toLowerCase();
            if (text.includes('descargar')) {
                btn.click();
                return true;
            }
        }
        return false;
    });

    if (!clicked) {
        progress(voucherType.label, 'No se encontró botón de descarga');
        return { type: voucherType.label, status: 'download_button_not_found', content: null };
    }

    const newFile = await waitForNewFile(downloadDir, existingFiles, 30000);

    if (!newFile) {
        progress(voucherType.label, 'Timeout esperando archivo descargado');
        return { type: voucherType.label, status: 'download_timeout', content: null };
    }

    const filePath = join(downloadDir, newFile);
    const content = readFileSync(filePath, 'utf-8');
    try { unlinkSync(filePath); } catch {}

    progress(voucherType.label, `Descargado: ${tableInfo.rows} registros`);
    return { type: voucherType.label, status: 'downloaded', content, rows: tableInfo.rows };
}

// --- Scrape Table Data (for table_scrape mode) ---

async function scrapeTableData(page, type) {
    const tableId = getTableId(type);

    progress('scrape', 'Extrayendo datos de la tabla...');

    const allClaves = [];
    let pageNum = 1;

    while (true) {
        progress('scrape', `Procesando página ${pageNum}...`);

        const pageData = await page.evaluate((tblId) => {
            const tbody = document.getElementById(tblId);
            if (!tbody) return { claves: [], hasNext: false };

            const rows = tbody.querySelectorAll('tr');
            const claves = [];

            for (const row of rows) {
                const cells = row.querySelectorAll('td');
                for (const cell of cells) {
                    const text = cell.textContent?.trim();
                    if (text && /^\d{49}$/.test(text)) {
                        claves.push(text);
                    }
                }

                const inputs = row.querySelectorAll('input[type="hidden"]');
                for (const input of inputs) {
                    const val = input.value?.trim();
                    if (val && /^\d{49}$/.test(val)) {
                        claves.push(val);
                    }
                }
            }

            const nextBtn = document.querySelector('.ui-paginator-next:not(.ui-state-disabled)');
            return { claves, hasNext: nextBtn !== null };
        }, tableId);

        allClaves.push(...pageData.claves);
        progress('scrape', `Página ${pageNum}: ${pageData.claves.length} claves (total: ${allClaves.length})`);

        if (!pageData.hasNext) break;

        await page.click('.ui-paginator-next:not(.ui-state-disabled)');
        await delay(3000);
        pageNum++;
    }

    const uniqueClaves = [...new Set(allClaves)];
    progress('scrape', `Extracción completada: ${uniqueClaves.length} claves únicas`);
    return uniqueClaves;
}

// --- Main ---

async function main() {
    let config;
    try {
        config = await readStdin();
    } catch (e) {
        emit('error', { code: 'INVALID_CONFIG', message: `Error leyendo configuración: ${e.message}` });
        process.exit(1);
    }

    const { ruc, password, type, year, month, mode, captchaApiKey, downloadDir } = config;

    if (!ruc || !password || !type || !year || !month || !mode) {
        emit('error', { code: 'MISSING_PARAMS', message: 'Faltan parámetros requeridos' });
        process.exit(1);
    }

    if (!captchaApiKey) {
        emit('error', { code: 'NO_CAPTCHA_KEY', message: 'Se requiere API key de 2Captcha (SRI_CAPTCHA_API_KEY)' });
        process.exit(1);
    }

    if (downloadDir) {
        mkdirSync(downloadDir, { recursive: true });
    }

    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined,
        defaultViewport: { width: 1366, height: 768 },
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--disable-blink-features=AutomationControlled',
            '--lang=es-EC,es',
        ],
    });

    const page = await browser.newPage();

    await page.setExtraHTTPHeaders({
        'Accept-Language': 'es-EC,es;q=0.9,en-US;q=0.8,en;q=0.7',
    });

    if (downloadDir) {
        const client = await page.createCDPSession();
        await client.send('Page.setDownloadBehavior', {
            behavior: 'allow',
            downloadPath: downloadDir,
        });
    }

    try {
        // Step 1: Login
        const loggedIn = await login(page, ruc, password);
        if (!loggedIn) {
            await browser.close();
            process.exit(1);
        }

        // Step 2: Navigate to comprobantes
        await navigateToComprobantes(page, type);

        // Step 3: Process based on mode
        if (mode === 'txt_download') {
            const files = [];

            for (let i = 0; i < VOUCHER_TYPES.length; i++) {
                const vt = VOUCHER_TYPES[i];

                // Re-navigate before each voucher type for fresh captcha (like Bot.cs)
                if (i > 0) {
                    await navigateToComprobantes(page, type);
                }

                try {
                    const result = await downloadForVoucherType(page, vt, year, month, downloadDir, captchaApiKey, type);
                    files.push(result);

                    if (result.status === 'downloaded') {
                        progress('summary', `${vt.label}: ${result.rows} registros descargados`);
                    } else {
                        progress('summary', `${vt.label}: ${result.status}`);
                    }
                } catch (err) {
                    progress(vt.label, `Error: ${err.message}`);
                    files.push({ type: vt.label, status: 'error', content: null, error: err.message });
                }

                await delay(2000);
            }

            emit('result', { mode: 'txt_download', files });

        } else if (mode === 'table_scrape') {
            const allClaves = [];

            for (let i = 0; i < VOUCHER_TYPES.length; i++) {
                const vt = VOUCHER_TYPES[i];

                if (i > 0) {
                    await navigateToComprobantes(page, type);
                }

                try {
                    const searchOk = await searchWithCaptcha(page, vt, year, month, captchaApiKey, type);

                    if (searchOk) {
                        const claves = await scrapeTableData(page, type);
                        allClaves.push(...claves);
                    }
                } catch (err) {
                    progress(vt.label, `Error: ${err.message}`);
                }

                await delay(2000);
            }

            const uniqueClaves = [...new Set(allClaves)];
            emit('result', { mode: 'table_scrape', clavesAcceso: uniqueClaves });
        }

    } catch (error) {
        emit('error', { code: 'UNEXPECTED_ERROR', message: error.message });
    } finally {
        await browser.close();
    }
}

main();
