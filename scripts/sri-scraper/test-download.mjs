/**
 * SRI Download Test - Comprobantes Recibidos
 *
 * Downloads .txt files for each voucher type from the previous month.
 * One .txt per voucher type (Factura, Liquidación, NC, ND, Retención).
 *
 * Usage:
 *   node test-download.mjs --ruc=0201432432001 --password=mypass
 *   node test-download.mjs --ruc=0201432432001 --password=mypass --year=2026 --month=4
 */

import puppeteer from 'puppeteer';
import { existsSync, mkdirSync, readdirSync, renameSync } from 'fs';
import { join } from 'path';

const SRI_URLS = {
    portal: 'https://srienlinea.sri.gob.ec/sri-en-linea/contribuyente/perfil',
    compras: 'https://srienlinea.sri.gob.ec/tuportal-internet/accederAplicacion.jspa?redireccion=57&idGrupo=55',
};

const VOUCHER_TYPES = [
    { value: '1', label: 'Factura' },
    { value: '3', label: 'NotaCredito' },
    { value: '4', label: 'NotaDebito' },
    { value: '6', label: 'Retencion' },
];

function parseArgs() {
    const args = {};
    process.argv.slice(2).forEach(arg => {
        const [key, value] = arg.replace('--', '').split('=');
        args[key] = value ?? true;
    });
    return args;
}

function log(step, message) {
    const ts = new Date().toISOString().substring(11, 19);
    console.log(`[${ts}] [${step}] ${message}`);
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function login(page, ruc, password) {
    log('login', 'Navegando al portal SRI...');
    await page.goto(SRI_URLS.portal, { waitUntil: 'networkidle2', timeout: 60000 });
    await delay(1500);

    const usernameEl = await page.$('#usuario');
    const passwordEl = await page.$('#password');

    if (!usernameEl || !passwordEl) {
        log('login', 'ERROR: No se encontró formulario de login');
        return false;
    }

    log('login', 'Ingresando credenciales...');
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

    log('login', 'Esperando redirección...');
    try {
        await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 });
    } catch {}

    await delay(1500);

    if (page.url().includes('/auth/')) {
        log('login', 'ERROR: Login fallido, aún en la página de auth');
        return false;
    }

    log('login', 'Login exitoso!');
    return true;
}

async function navigateToComprobantes(page) {
    log('nav', 'Navegando a Comprobantes Recibidos...');
    await page.goto(SRI_URLS.compras, { waitUntil: 'networkidle2', timeout: 60000 });
    await delay(3000);
    log('nav', `URL: ${page.url()}`);
}

async function waitForNewFile(downloadDir, existingFiles, timeoutMs = 15000) {
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

/**
 * Execute reCAPTCHA Enterprise and get a token.
 * The SRI page uses grecaptcha.enterprise.execute() with action 'consulta_cel_recibidos'.
 * The token is stored in #g-recaptcha-response textarea.
 * Then the search function rcBuscar() is called via PrimeFaces AJAX.
 */
async function executeRecaptchaAndSearch(page) {
    try {
        log('captcha', 'Ejecutando reCAPTCHA Enterprise...');

        const result = await page.evaluate(async () => {
            const sitekey = '6LdukTQsAAAAAIcciM4GZq4ibeyplUhmWvlScuQE';

            if (!window.grecaptcha?.enterprise?.execute) {
                return { success: false, error: 'grecaptcha.enterprise.execute no disponible' };
            }

            try {
                const token = await window.grecaptcha.enterprise.execute(sitekey, { action: 'consulta_cel_recibidos' });

                if (!token) {
                    return { success: false, error: 'Token vacío' };
                }

                // Set the token in the response field (this is what the SRI page does)
                document.querySelectorAll('textarea[name="g-recaptcha-response"]').forEach(el => {
                    el.value = token;
                });

                return { success: true, tokenLength: token.length };
            } catch (e) {
                return { success: false, error: e.message };
            }
        });

        if (!result.success) {
            log('captcha', `Error: ${result.error}`);
            return false;
        }

        log('captcha', `Token obtenido (${result.tokenLength} chars), ejecutando búsqueda...`);

        // Now trigger the actual search function that the button would call
        await page.evaluate(() => {
            if (typeof rcBuscar === 'function') {
                rcBuscar();
            }
        });

        return true;
    } catch (err) {
        log('captcha', `Error: ${err.message}`);
        return false;
    }
}

async function downloadForVoucherType(page, voucherType, year, month, downloadDir) {
    log(voucherType.label, `Configurando filtros: año=${year}, mes=${month}, tipo=${voucherType.label}...`);

    // Select year
    await page.select('#frmPrincipal\\:ano', String(year));
    await delay(1500);

    // Select month
    await page.select('#frmPrincipal\\:mes', String(month));
    await delay(1500);

    // Select day = Todos (0)
    await page.select('#frmPrincipal\\:dia', '0');
    await delay(1000);

    // Select voucher type
    await page.select('#frmPrincipal\\:cmbTipoComprobante', voucherType.value);
    await delay(1000);

    // Execute reCAPTCHA and search - with retry on captcha failure
    let searchSuccess = false;
    for (let attempt = 1; attempt <= 3; attempt++) {
        const searchOk = await executeRecaptchaAndSearch(page);
        if (!searchOk) {
            log(voucherType.label, 'Fallback: click directo en Consultar...');
            await page.click('#frmPrincipal\\:btnBuscar');
        }

        log(voucherType.label, `Esperando resultados (intento ${attempt}/3)...`);
        await delay(7000);

        // Check for captcha error
        const captchaError = await page.evaluate(() => {
            const body = document.body?.innerText || '';
            return body.includes('Captcha incorrecta');
        });

        if (!captchaError) {
            searchSuccess = true;
            break;
        }

        log(voucherType.label, `Captcha incorrecta en intento ${attempt}, reintentando...`);

        // Reset captcha and close the error message
        await page.evaluate(() => {
            if (window.grecaptcha?.enterprise?.reset) {
                window.grecaptcha.enterprise.reset();
            }
            // Close the warning message
            const closeBtn = document.querySelector('.ui-messages-close, .ui-icon-close');
            if (closeBtn) closeBtn.click();
        });
        await delay(2000);
    }

    if (!searchSuccess) {
        log(voucherType.label, 'ERROR: Captcha falló después de 3 intentos');
        await page.screenshot({ path: join('screenshots', `captcha-failed-${voucherType.label}.png`), fullPage: true });
        return null;
    }

    // Take screenshot to see what happened
    await page.screenshot({ path: join('screenshots', `search-${voucherType.label}.png`), fullPage: true });

    // Check if there are results in the table
    const tableInfo = await page.evaluate(() => {
        const tbody = document.getElementById('frmPrincipal:tablaCompRecibidos_data');
        if (!tbody) return { found: false, rows: 0, message: 'Tabla no encontrada' };

        const rows = tbody.querySelectorAll('tr');
        if (rows.length === 0) return { found: true, rows: 0, message: 'Sin registros' };

        // Check if first row has "no records" message
        const firstRowText = rows[0]?.textContent?.trim() || '';
        if (firstRowText.includes('No se encontraron') || firstRowText.includes('No existen') || rows[0]?.classList.contains('ui-datatable-empty-message')) {
            return { found: true, rows: 0, message: firstRowText.substring(0, 100) };
        }

        return { found: true, rows: rows.length, message: `${rows.length} registros encontrados` };
    });

    log(voucherType.label, `Resultado: ${tableInfo.message}`);

    if (tableInfo.rows === 0) {
        log(voucherType.label, 'Sin registros, saltando descarga.');
        return null;
    }

    // There are results - download the report
    log(voucherType.label, `${tableInfo.rows} registros, descargando reporte...`);

    const existingFiles = readdirSync(downloadDir);

    // Click "Descargar reporte" link
    const clicked = await page.evaluate(() => {
        const links = document.querySelectorAll('a');
        for (const link of links) {
            const text = (link.textContent || '').toLowerCase();
            if (text.includes('descargar') && (text.includes('reporte') || text.includes('report'))) {
                link.click();
                return true;
            }
        }
        // Also try buttons
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
        log(voucherType.label, 'WARN: No se encontró botón de descarga');
        return null;
    }

    // Wait for download
    const newFile = await waitForNewFile(downloadDir, existingFiles, 20000);

    if (!newFile) {
        log(voucherType.label, 'WARN: Timeout esperando archivo descargado');
        return null;
    }

    // Rename file to include voucher type
    const ext = newFile.includes('.') ? newFile.substring(newFile.lastIndexOf('.')) : '.txt';
    const newName = `comprobantes_recibidos_${year}_${String(month).padStart(2, '0')}_${voucherType.label}${ext}`;
    const oldPath = join(downloadDir, newFile);
    const newPath = join(downloadDir, newName);

    try {
        renameSync(oldPath, newPath);
    } catch {
        log(voucherType.label, `No se pudo renombrar, archivo original: ${newFile}`);
        return join(downloadDir, newFile);
    }

    log(voucherType.label, `Descargado: ${newName}`);
    return newPath;
}

async function main() {
    const args = parseArgs();

    if (!args.ruc || !args.password) {
        console.error('Uso: node test-download.mjs --ruc=RUC --password=CLAVE [--year=2026] [--month=4]');
        process.exit(1);
    }

    // Default to previous month
    const now = new Date();
    const prevMonth = now.getMonth() === 0 ? 12 : now.getMonth(); // getMonth() is 0-indexed
    const prevYear = now.getMonth() === 0 ? now.getFullYear() - 1 : now.getFullYear();

    const year = parseInt(args.year) || prevYear;
    const month = parseInt(args.month) || prevMonth;

    const downloadDir = join(process.cwd(), 'downloads');
    mkdirSync(downloadDir, { recursive: true });
    mkdirSync('screenshots', { recursive: true });

    log('init', `Descargando comprobantes recibidos: ${year}-${String(month).padStart(2, '0')} (todos los días)`);
    log('init', `Directorio de descarga: ${downloadDir}`);

    const browser = await puppeteer.launch({
        headless: false,
        defaultViewport: { width: 1366, height: 768 },
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
        slowMo: 30,
    });

    const page = await browser.newPage();
    await page.setUserAgent(
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36'
    );

    // Configure download directory
    const client = await page.createCDPSession();
    await client.send('Page.setDownloadBehavior', {
        behavior: 'allow',
        downloadPath: downloadDir,
    });

    try {
        // Step 1: Login
        const loggedIn = await login(page, args.ruc, args.password);
        if (!loggedIn) {
            await browser.close();
            process.exit(1);
        }

        // Step 2: Navigate to comprobantes recibidos
        await navigateToComprobantes(page);

        // Step 3: For each voucher type, search and download
        const results = [];

        for (const vt of VOUCHER_TYPES) {
            try {
                const filePath = await downloadForVoucherType(page, vt, year, month, downloadDir);
                results.push({ type: vt.label, file: filePath, status: filePath ? 'downloaded' : 'no_records' });
            } catch (err) {
                log(vt.label, `ERROR: ${err.message}`);
                results.push({ type: vt.label, file: null, status: 'error', error: err.message });

                // Re-navigate to comprobantes page to reset state
                await navigateToComprobantes(page);
            }

            // Small delay between types
            await delay(2000);
        }

        // Summary
        log('summary', '═══════════════════════════════════════');
        log('summary', `Resultados para ${year}-${String(month).padStart(2, '0')}:`);
        for (const r of results) {
            const icon = r.status === 'downloaded' ? '✓' : r.status === 'no_records' ? '-' : '✗';
            log('summary', `  ${icon} ${r.type}: ${r.status}${r.file ? ` -> ${r.file}` : ''}`);
        }
        log('summary', '═══════════════════════════════════════');

        const downloaded = results.filter(r => r.status === 'downloaded');
        log('summary', `${downloaded.length} de ${VOUCHER_TYPES.length} tipos descargados`);

    } catch (error) {
        log('error', `Error inesperado: ${error.message}`);
        await page.screenshot({ path: 'screenshots/error.png', fullPage: true });
    } finally {
        log('info', 'Cerrando navegador en 5s...');
        await delay(5000);
        await browser.close();
    }
}

main();
