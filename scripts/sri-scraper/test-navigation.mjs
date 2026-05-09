/**
 * SRI Navigation Test Script
 *
 * Tests the full navigation flow on srienlinea.sri.gob.ec:
 * 1. Login via Keycloak
 * 2. Navigate to comprobantes recibidos (compras)
 * 3. Navigate to comprobantes emitidos (ventas)
 * 4. Inspect page structure (forms, tables, captcha)
 *
 * Usage:
 *   node test-navigation.mjs --ruc=0102030405001 --password=mypass
 *   node test-navigation.mjs --ruc=0102030405001 --password=mypass --headless
 *   node test-navigation.mjs --ruc=0102030405001 --password=mypass --type=compras
 *   node test-navigation.mjs --ruc=0102030405001 --password=mypass --type=ventas
 */

import puppeteer from 'puppeteer';

const SRI_URLS = {
    // Navigate directly to comprobantes - SRI will redirect to login if not authenticated
    portal: 'https://srienlinea.sri.gob.ec/sri-en-linea/contribuyente/perfil',
    compras: 'https://srienlinea.sri.gob.ec/tuportal-internet/accederAplicacion.jspa?redireccion=57&idGrupo=55',
    ventas: 'https://srienlinea.sri.gob.ec/tuportal-internet/accederAplicacion.jspa?redireccion=60&idGrupo=55',
};

function parseArgs() {
    const args = {};
    process.argv.slice(2).forEach(arg => {
        const [key, value] = arg.replace('--', '').split('=');
        args[key] = value ?? true;
    });
    return args;
}

function log(step, message) {
    const timestamp = new Date().toISOString().substring(11, 19);
    console.log(`[${timestamp}] [${step}] ${message}`);
}

async function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function takeScreenshot(page, name) {
    const path = `screenshots/${name}.png`;
    await page.screenshot({ path, fullPage: true });
    log('screenshot', `Saved: ${path}`);
}

async function login(page, ruc, password) {
    // Navigate to the portal - SRI will redirect to Keycloak login if not authenticated
    log('login', 'Navigating to SRI portal (will redirect to login)...');
    await page.goto(SRI_URLS.portal, { waitUntil: 'networkidle2', timeout: 30000 });

    await delay(2000);
    await takeScreenshot(page, '01-redirected-page');

    const currentUrl = page.url();
    log('login', `Current URL: ${currentUrl}`);

    // Inspect the page to find the login form elements
    const pageStructure = await page.evaluate(() => {
        const inputs = Array.from(document.querySelectorAll('input')).map(i => ({
            id: i.id, name: i.name, type: i.type, placeholder: i.placeholder,
            className: i.className?.substring(0, 60),
        }));
        const buttons = Array.from(document.querySelectorAll('button, input[type="submit"]')).map(b => ({
            id: b.id, type: b.type, text: (b.textContent || b.value || '').trim().substring(0, 50),
            className: b.className?.substring(0, 60),
        }));
        const forms = Array.from(document.querySelectorAll('form')).map(f => ({
            id: f.id, action: f.action, method: f.method,
        }));
        const links = Array.from(document.querySelectorAll('a')).map(a => ({
            text: a.textContent?.trim().substring(0, 40),
            href: a.href?.substring(0, 100),
        })).filter(a => a.text && a.text.length > 0);

        return { inputs, buttons, forms, links: links.slice(0, 20), bodyText: document.body?.innerText?.substring(0, 500) };
    });

    log('login', `Inputs found: ${pageStructure.inputs.length}`);
    pageStructure.inputs.forEach(i => log('login', `  Input: id=${i.id}, name=${i.name}, type=${i.type}, placeholder=${i.placeholder}`));

    log('login', `Buttons found: ${pageStructure.buttons.length}`);
    pageStructure.buttons.forEach(b => log('login', `  Button: id=${b.id}, text="${b.text}"`));

    log('login', `Forms found: ${pageStructure.forms.length}`);
    pageStructure.forms.forEach(f => log('login', `  Form: id=${f.id}, action=${f.action}`));

    log('login', `Links found: ${pageStructure.links.length}`);
    pageStructure.links.forEach(l => log('login', `  Link: "${l.text}" -> ${l.href}`));

    log('login', `Body text preview: ${pageStructure.bodyText?.substring(0, 200)}`);

    // Try multiple possible selectors for the username field
    const usernameSelectors = ['#usuario', '#username', 'input[name="usuario"]', 'input[name="username"]', 'input[type="text"]'];
    const passwordSelectors = ['#password', '#clave', 'input[name="password"]', 'input[name="clave"]', 'input[type="password"]'];
    const submitSelectors = ['#kc-login', '#login', 'button[type="submit"]', 'input[type="submit"]', '#btnIngresar'];

    let usernameEl = null;
    let passwordEl = null;
    let submitEl = null;

    for (const sel of usernameSelectors) {
        try {
            usernameEl = await page.$(sel);
            if (usernameEl) { log('login', `Found username field: ${sel}`); break; }
        } catch {}
    }

    for (const sel of passwordSelectors) {
        try {
            passwordEl = await page.$(sel);
            if (passwordEl) { log('login', `Found password field: ${sel}`); break; }
        } catch {}
    }

    for (const sel of submitSelectors) {
        try {
            submitEl = await page.$(sel);
            if (submitEl) { log('login', `Found submit button: ${sel}`); break; }
        } catch {}
    }

    if (!usernameEl || !passwordEl) {
        log('login', 'Could not find login form fields. Page might need different approach.');
        await takeScreenshot(page, '02-no-login-form');
        return false;
    }

    // Fill credentials
    log('login', `Filling RUC: ${ruc.substring(0, 4)}...`);
    await usernameEl.click({ clickCount: 3 }); // Select all
    await usernameEl.type(ruc, { delay: 50 });
    await delay(500);

    log('login', 'Filling password...');
    await passwordEl.click({ clickCount: 3 });
    await passwordEl.type(password, { delay: 50 });
    await delay(500);

    await takeScreenshot(page, '02-login-filled');

    // Click submit
    if (submitEl) {
        log('login', 'Clicking submit button...');
        await submitEl.click();
    } else {
        log('login', 'No submit button found, pressing Enter...');
        await passwordEl.press('Enter');
    }

    // Wait for navigation after login
    log('login', 'Waiting for redirect after login...');
    try {
        await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
        log('login', `Redirected to: ${page.url()}`);
    } catch (e) {
        log('login', `Navigation timeout, current URL: ${page.url()}`);
    }

    await delay(2000);
    await takeScreenshot(page, '03-after-login');

    // Check if login was successful
    const afterUrl = page.url();
    if (afterUrl.includes('/auth/') || afterUrl.includes('/login')) {
        const errorText = await page.evaluate(() => {
            const errorEl = document.querySelector('.alert-error, #input-error, .kc-feedback-text, .error-message, .alert');
            return errorEl ? errorEl.textContent.trim() : null;
        });

        if (errorText) {
            log('login', `LOGIN FAILED: ${errorText}`);
            return false;
        }
    }

    log('login', 'Login appears successful!');
    return true;
}

async function navigateToComprobantes(page, type) {
    const url = type === 'ventas' ? SRI_URLS.ventas : SRI_URLS.compras;
    const label = type === 'ventas' ? 'Comprobantes Emitidos' : 'Comprobantes Recibidos';

    log(type, `Navigating to ${label}...`);
    await page.goto(url, { waitUntil: 'networkidle2', timeout: 30000 });

    await delay(2000);
    await takeScreenshot(page, `04-${type}-page`);

    log(type, `Current URL: ${page.url()}`);

    // Inspect page structure
    const pageInfo = await page.evaluate(() => {
        const info = {
            title: document.title,
            forms: [],
            selects: [],
            tables: [],
            captcha: null,
            buttons: [],
            iframes: [],
        };

        // Find all forms
        document.querySelectorAll('form').forEach(form => {
            info.forms.push({
                id: form.id,
                action: form.action,
                method: form.method,
            });
        });

        // Find all select elements (dropdowns for year/month/day)
        document.querySelectorAll('select').forEach(select => {
            const options = Array.from(select.options).map(o => ({
                value: o.value,
                text: o.text.trim(),
            }));
            info.selects.push({
                id: select.id,
                name: select.name,
                optionCount: options.length,
                options: options.slice(0, 10), // First 10 options
            });
        });

        // Find all tables
        document.querySelectorAll('table').forEach(table => {
            info.tables.push({
                id: table.id,
                className: table.className,
                rows: table.rows.length,
            });
        });

        // Find reCAPTCHA
        const recaptchaDiv = document.querySelector('.g-recaptcha, [data-sitekey]');
        if (recaptchaDiv) {
            info.captcha = {
                sitekey: recaptchaDiv.getAttribute('data-sitekey'),
                className: recaptchaDiv.className,
            };
        }

        // Find reCAPTCHA iframe
        const captchaIframe = document.querySelector('iframe[src*="recaptcha"]');
        if (captchaIframe) {
            info.captcha = info.captcha || {};
            info.captcha.iframeSrc = captchaIframe.src;
        }

        // Find buttons
        document.querySelectorAll('button, input[type="submit"], input[type="button"], a.ui-button').forEach(btn => {
            info.buttons.push({
                id: btn.id,
                type: btn.type || btn.tagName,
                text: btn.textContent?.trim().substring(0, 50) || btn.value,
                className: btn.className?.substring(0, 80),
            });
        });

        // Find iframes
        document.querySelectorAll('iframe').forEach(iframe => {
            info.iframes.push({
                id: iframe.id,
                src: iframe.src?.substring(0, 100),
            });
        });

        return info;
    });

    log(type, `Page title: ${pageInfo.title}`);
    log(type, `Forms found: ${pageInfo.forms.length}`);
    pageInfo.forms.forEach(f => log(type, `  Form: id=${f.id}, action=${f.action}`));

    log(type, `Selects found: ${pageInfo.selects.length}`);
    pageInfo.selects.forEach(s => {
        log(type, `  Select: id=${s.id}, name=${s.name}, options=${s.optionCount}`);
        s.options.forEach(o => log(type, `    - ${o.value}: ${o.text}`));
    });

    log(type, `Tables found: ${pageInfo.tables.length}`);
    pageInfo.tables.forEach(t => log(type, `  Table: id=${t.id}, class=${t.className}, rows=${t.rows}`));

    log(type, `Buttons found: ${pageInfo.buttons.length}`);
    pageInfo.buttons.forEach(b => log(type, `  Button: id=${b.id}, text="${b.text}"`));

    if (pageInfo.captcha) {
        log(type, `reCAPTCHA found! Sitekey: ${pageInfo.captcha.sitekey}`);
    } else {
        log(type, 'No reCAPTCHA detected on initial load');
    }

    if (pageInfo.iframes.length > 0) {
        log(type, `Iframes found: ${pageInfo.iframes.length}`);
        pageInfo.iframes.forEach(i => log(type, `  Iframe: id=${i.id}, src=${i.src}`));
    }

    return pageInfo;
}

async function inspectAfterFilters(page, type) {
    // Try to find and interact with year/month selects
    log(type, 'Attempting to inspect filter dropdowns...');

    const filterInfo = await page.evaluate(() => {
        const result = {
            yearSelect: null,
            monthSelect: null,
            daySelect: null,
            searchButton: null,
            downloadButton: null,
        };

        // Common IDs from the C# reference
        const possibleYearIds = [
            'frmPrincipal:ano',
            'frmPrincipal:year',
            'ano',
        ];
        const possibleMonthIds = [
            'frmPrincipal:mes',
            'frmPrincipal:month',
            'mes',
        ];
        const possibleDayIds = [
            'frmPrincipal:dia',
            'frmPrincipal:day',
            'dia',
        ];

        for (const id of possibleYearIds) {
            const el = document.getElementById(id);
            if (el) {
                result.yearSelect = { id, tagName: el.tagName };
                break;
            }
        }

        for (const id of possibleMonthIds) {
            const el = document.getElementById(id);
            if (el) {
                result.monthSelect = { id, tagName: el.tagName };
                break;
            }
        }

        for (const id of possibleDayIds) {
            const el = document.getElementById(id);
            if (el) {
                result.daySelect = { id, tagName: el.tagName };
                break;
            }
        }

        // Look for search/consultar button
        const allButtons = document.querySelectorAll('button, input[type="submit"]');
        for (const btn of allButtons) {
            const text = (btn.textContent || btn.value || '').toLowerCase();
            if (text.includes('buscar') || text.includes('consultar')) {
                result.searchButton = {
                    id: btn.id,
                    text: btn.textContent?.trim() || btn.value,
                };
            }
            if (text.includes('descargar') || text.includes('exportar') || text.includes('download')) {
                result.downloadButton = {
                    id: btn.id,
                    text: btn.textContent?.trim() || btn.value,
                };
            }
        }

        return result;
    });

    log(type, `Year select: ${JSON.stringify(filterInfo.yearSelect)}`);
    log(type, `Month select: ${JSON.stringify(filterInfo.monthSelect)}`);
    log(type, `Day select: ${JSON.stringify(filterInfo.daySelect)}`);
    log(type, `Search button: ${JSON.stringify(filterInfo.searchButton)}`);
    log(type, `Download button: ${JSON.stringify(filterInfo.downloadButton)}`);

    return filterInfo;
}

async function main() {
    const args = parseArgs();

    if (!args.ruc || !args.password) {
        console.error('Usage: node test-navigation.mjs --ruc=YOUR_RUC --password=YOUR_PASSWORD [--headless] [--type=compras|ventas]');
        process.exit(1);
    }

    // Create screenshots directory
    const { mkdirSync } = await import('fs');
    try { mkdirSync('screenshots', { recursive: true }); } catch {}

    const headless = args.headless === true;
    const type = args.type || 'both'; // 'compras', 'ventas', or 'both'

    log('init', `Starting SRI navigation test (headless=${headless}, type=${type})`);

    const browser = await puppeteer.launch({
        headless: headless ? 'new' : false,
        defaultViewport: { width: 1366, height: 768 },
        args: [
            '--no-sandbox',
            '--disable-setuid-sandbox',
            '--disable-dev-shm-usage',
        ],
        slowMo: headless ? 0 : 50, // Slow down for visibility in non-headless
    });

    const page = await browser.newPage();

    // Set a realistic user agent
    await page.setUserAgent(
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36'
    );

    try {
        // Step 1: Login
        const loggedIn = await login(page, args.ruc, args.password);

        if (!loggedIn) {
            log('error', 'Login failed. Check credentials.');
            await takeScreenshot(page, 'error-login-failed');

            if (!headless) {
                log('info', 'Browser will stay open for 30s for inspection...');
                await delay(30000);
            }

            await browser.close();
            process.exit(1);
        }

        // Step 2: Navigate to comprobantes
        if (type === 'compras' || type === 'both') {
            const comprasInfo = await navigateToComprobantes(page, 'compras');
            await inspectAfterFilters(page, 'compras');

            if (!headless) {
                log('info', 'Pausing 15s for manual inspection of compras page...');
                await delay(15000);
            }
        }

        if (type === 'ventas' || type === 'both') {
            const ventasInfo = await navigateToComprobantes(page, 'ventas');
            await inspectAfterFilters(page, 'ventas');

            if (!headless) {
                log('info', 'Pausing 15s for manual inspection of ventas page...');
                await delay(15000);
            }
        }

        await takeScreenshot(page, '99-final');
        log('done', 'Navigation test completed successfully!');
        log('done', 'Check the screenshots/ directory for captured images.');

    } catch (error) {
        log('error', `Unexpected error: ${error.message}`);
        await takeScreenshot(page, 'error-unexpected');
        console.error(error);
    } finally {
        if (!headless) {
            log('info', 'Browser will stay open for 10s before closing...');
            await delay(10000);
        }
        await browser.close();
    }
}

main();
