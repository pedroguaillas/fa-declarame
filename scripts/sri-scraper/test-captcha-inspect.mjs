/**
 * Quick script to inspect how the SRI page handles reCAPTCHA.
 * Logs the grecaptcha object, callbacks, form submission handlers, etc.
 */

import puppeteer from 'puppeteer';

const SRI_URLS = {
    portal: 'https://srienlinea.sri.gob.ec/sri-en-linea/contribuyente/perfil',
    compras: 'https://srienlinea.sri.gob.ec/tuportal-internet/accederAplicacion.jspa?redireccion=57&idGrupo=55',
};

function log(msg) {
    console.log(`[${new Date().toISOString().substring(11, 19)}] ${msg}`);
}

function delay(ms) { return new Promise(r => setTimeout(r, ms)); }

async function main() {
    const args = {};
    process.argv.slice(2).forEach(arg => {
        const [k, v] = arg.replace('--', '').split('=');
        args[k] = v ?? true;
    });

    const browser = await puppeteer.launch({
        headless: false,
        defaultViewport: { width: 1366, height: 768 },
        args: ['--no-sandbox'],
        slowMo: 30,
    });

    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36');

    // Login
    log('Logging in...');
    await page.goto(SRI_URLS.portal, { waitUntil: 'networkidle2', timeout: 60000 });
    await delay(1500);
    await page.type('#usuario', args.ruc, { delay: 80 });
    await delay(300);
    await page.type('#password', args.password, { delay: 80 });
    await delay(300);
    await page.click('#kc-login');
    try { await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 }); } catch {}
    await delay(1500);
    log('Logged in!');

    // Navigate to comprobantes
    log('Navigating to comprobantes...');
    await page.goto(SRI_URLS.compras, { waitUntil: 'networkidle2', timeout: 60000 });
    await delay(3000);

    // Inspect reCAPTCHA structure
    const captchaInfo = await page.evaluate(() => {
        const info = {};

        // Check grecaptcha object
        info.hasGrecaptcha = typeof window.grecaptcha !== 'undefined';
        info.hasEnterprise = typeof window.grecaptcha?.enterprise !== 'undefined';

        // Find all recaptcha-related elements
        info.recaptchaElements = [];
        document.querySelectorAll('[class*="recaptcha"], [id*="recaptcha"], [data-sitekey]').forEach(el => {
            info.recaptchaElements.push({
                tag: el.tagName,
                id: el.id,
                className: el.className?.substring(0, 100),
                sitekey: el.getAttribute('data-sitekey'),
                size: el.getAttribute('data-size'),
                callback: el.getAttribute('data-callback'),
                action: el.getAttribute('data-action'),
            });
        });

        // Find g-recaptcha-response textareas
        info.responseFields = [];
        document.querySelectorAll('textarea[name="g-recaptcha-response"], #g-recaptcha-response').forEach(el => {
            info.responseFields.push({
                id: el.id,
                name: el.name,
                hasValue: el.value?.length > 0,
                valueLength: el.value?.length || 0,
            });
        });

        // Check the Consultar button's onclick behavior
        const btnBuscar = document.getElementById('frmPrincipal:btnBuscar');
        if (btnBuscar) {
            info.btnBuscar = {
                onclick: btnBuscar.getAttribute('onclick'),
                type: btnBuscar.type,
                tagName: btnBuscar.tagName,
            };
        }

        // Look for any scripts that reference recaptcha
        info.recaptchaScripts = [];
        document.querySelectorAll('script').forEach(s => {
            const text = s.textContent || '';
            if (text.includes('recaptcha') || text.includes('grecaptcha')) {
                info.recaptchaScripts.push(text.substring(0, 300));
            }
            const src = s.src || '';
            if (src.includes('recaptcha')) {
                info.recaptchaScripts.push(`SRC: ${src}`);
            }
        });

        // Check for hidden inputs related to captcha
        info.hiddenInputs = [];
        document.querySelectorAll('input[type="hidden"]').forEach(el => {
            const name = el.name || el.id || '';
            if (name.toLowerCase().includes('captcha') || name.toLowerCase().includes('recaptcha') || name.toLowerCase().includes('token')) {
                info.hiddenInputs.push({ id: el.id, name: el.name, hasValue: !!el.value, valueLen: el.value?.length });
            }
        });

        // Try to find the grecaptcha render config
        if (window.grecaptcha?.enterprise) {
            try {
                // Check widget count
                info.widgetInfo = 'enterprise object exists';
            } catch {}
        }

        return info;
    });

    log('=== reCAPTCHA Analysis ===');
    log(`grecaptcha exists: ${captchaInfo.hasGrecaptcha}`);
    log(`grecaptcha.enterprise exists: ${captchaInfo.hasEnterprise}`);
    log(`reCAPTCHA elements: ${JSON.stringify(captchaInfo.recaptchaElements, null, 2)}`);
    log(`Response fields: ${JSON.stringify(captchaInfo.responseFields, null, 2)}`);
    log(`Consultar button: ${JSON.stringify(captchaInfo.btnBuscar, null, 2)}`);
    log(`Hidden inputs: ${JSON.stringify(captchaInfo.hiddenInputs, null, 2)}`);
    log(`reCAPTCHA scripts: ${captchaInfo.recaptchaScripts.length}`);
    captchaInfo.recaptchaScripts.forEach((s, i) => log(`  Script ${i}: ${s}`));

    // Now try to execute the captcha and see what happens
    log('\n=== Attempting grecaptcha.enterprise.execute ===');
    const executeResult = await page.evaluate(async () => {
        try {
            if (!window.grecaptcha?.enterprise?.execute) {
                return { error: 'grecaptcha.enterprise.execute not found' };
            }

            // Find sitekey
            const el = document.querySelector('[data-sitekey]');
            const sitekey = el?.getAttribute('data-sitekey');
            if (!sitekey) return { error: 'No sitekey found' };

            // Try execute
            const token = await window.grecaptcha.enterprise.execute(sitekey, { action: 'buscar' });
            return { success: true, tokenLength: token?.length, tokenPreview: token?.substring(0, 30) };
        } catch (e) {
            return { error: e.message };
        }
    });

    log(`Execute result: ${JSON.stringify(executeResult)}`);

    if (executeResult.success) {
        log('Token obtained! Now setting it and trying Consultar...');

        // Set the token in the response field
        await page.evaluate((token) => {
            document.querySelectorAll('textarea[name="g-recaptcha-response"]').forEach(el => {
                el.value = token;
            });
            const hiddenInput = document.querySelector('input[name="g-recaptcha-response"]');
            if (hiddenInput) hiddenInput.value = token;
        }, executeResult.tokenPreview); // Note: we'd need the full token

        // For now just log success
        log('Would click Consultar here with the token set');
    }

    log('\nKeeping browser open for 30s for manual inspection...');
    await delay(30000);
    await browser.close();
}

main();
