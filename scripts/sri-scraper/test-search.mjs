/**
 * Test: Login + search with captcha for ONE voucher type.
 * Captures page state before/after rcBuscar to diagnose why search fails.
 *
 * Run: echo '{"ruc":"...","password":"...","apiKey":"..."}' | node test-search.mjs
 */

import puppeteer from 'puppeteer-extra';
import StealthPlugin from 'puppeteer-extra-plugin-stealth';
puppeteer.use(StealthPlugin());

const TWOCAPTCHA_IN = 'https://2captcha.com/in.php';
const TWOCAPTCHA_RES = 'https://2captcha.com/res.php';

function delay(ms) { return new Promise(r => setTimeout(r, ms)); }

async function readStdin() {
    const chunks = [];
    for await (const chunk of process.stdin) chunks.push(chunk);
    return JSON.parse(Buffer.concat(chunks).toString());
}

async function solveCaptcha(apiKey, sitekey) {
    console.log(`[captcha] Sending to 2Captcha (sitekey=${sitekey.substring(0, 15)}...)...`);
    const inRes = await fetch(TWOCAPTCHA_IN, {
        method: 'POST',
        body: new URLSearchParams({
            key: apiKey, method: 'userrecaptcha',
            googlekey: sitekey, pageurl: 'https://srienlinea.sri.gob.ec',
            json: '1',
        }),
    });
    const inData = await inRes.json();
    if (inData.status !== 1) { console.log(`[captcha] Submit failed: ${inData.request}`); return null; }

    console.log(`[captcha] ID=${inData.request}, waiting...`);
    await delay(15000);

    for (let i = 0; i < 20; i++) {
        const r = await fetch(`${TWOCAPTCHA_RES}?${new URLSearchParams({
            key: apiKey, action: 'get', id: inData.request, json: '1',
        })}`);
        const d = await r.json();
        if (d.status === 1) { console.log(`[captcha] Solved! (${d.request.length} chars)`); return d.request; }
        if (d.request !== 'CAPCHA_NOT_READY') { console.log(`[captcha] Error: ${d.request}`); return null; }
        await delay(5000);
    }
    return null;
}

async function main() {
    const config = await readStdin();
    const { ruc, password, apiKey } = config;

    const browser = await puppeteer.launch({
        headless: 'new',
        executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined,
        defaultViewport: { width: 1366, height: 768 },
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
    });

    const page = await browser.newPage();

    try {
        // LOGIN
        console.log('[login] Navigating to SRI portal...');
        await page.goto('https://srienlinea.sri.gob.ec/sri-en-linea/contribuyente/perfil', { waitUntil: 'networkidle2', timeout: 60000 });
        await delay(2000);

        await (await page.$('#usuario')).type(ruc, { delay: 80 });
        await delay(300);
        await (await page.$('#password')).type(password, { delay: 80 });
        await delay(300);
        await (await page.$('#kc-login')).click();

        try { await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 60000 }); } catch {}
        await delay(2000);
        console.log(`[login] URL after login: ${page.url()}`);

        if (page.url().includes('/auth/')) {
            console.log('[login] FAILED - still on auth page');
            await browser.close();
            return;
        }
        console.log('[login] SUCCESS');

        // NAVIGATE TO COMPROBANTES RECIBIDOS
        console.log('[nav] Going to Comprobantes Recibidos...');
        await page.goto('https://srienlinea.sri.gob.ec/tuportal-internet/accederAplicacion.jspa?redireccion=57&idGrupo=55', { waitUntil: 'networkidle2', timeout: 60000 });
        await delay(3000);
        console.log(`[nav] URL: ${page.url()}`);

        // DIAGNOSTIC: capture page state
        const pageDiag = await page.evaluate(() => {
            return {
                url: window.location.href,
                title: document.title,
                hasRcBuscar: typeof rcBuscar === 'function',
                rcBuscarSource: typeof rcBuscar === 'function' ? rcBuscar.toString() : 'N/A',
                hasGrecaptcha: typeof grecaptcha !== 'undefined',
                hasEnterprise: typeof grecaptcha !== 'undefined' && typeof grecaptcha.enterprise !== 'undefined',
                recaptchaEls: document.getElementsByClassName('g-recaptcha').length,
                recaptchaResponse: !!document.getElementById('g-recaptcha-response'),
                formExists: !!document.getElementById('frmPrincipal'),
                tableExists: !!document.getElementById('frmPrincipal:tablaCompRecibidos_data'),
                yearSelect: !!document.getElementById('frmPrincipal:ano'),
                bodySnippet: document.body?.innerText?.substring(0, 300) || '',
            };
        });

        console.log('\n=== PAGE DIAGNOSTIC ===');
        console.log(JSON.stringify(pageDiag, null, 2));

        // EXTRACT SITEKEY
        const sitekey = await page.evaluate(() => {
            const el = document.getElementsByClassName('g-recaptcha')[0];
            if (el) return el.getAttribute('data-sitekey');
            const scripts = document.querySelectorAll('script');
            for (const s of scripts) {
                const m = s.textContent?.match(/cargarRecaptcha\s*\([^,]+,\s*['"]([A-Za-z0-9_-]{40})['"]/);
                if (m) return m[1];
                const m2 = s.textContent?.match(/sitekey['":\s]+['"]([A-Za-z0-9_-]{40})['"]/);
                if (m2) return m2[1];
            }
            return null;
        });
        console.log(`\n[sitekey] Extracted: ${sitekey || 'NOT FOUND'}`);

        if (!sitekey) {
            console.log('[ERROR] No sitekey found. Page might not have loaded correctly.');
            // Take screenshot for debugging
            await page.screenshot({ path: '/var/www/html/storage/app/private/sri-debug.png', fullPage: true });
            console.log('[debug] Screenshot saved to storage/app/private/sri-debug.png');
            await browser.close();
            return;
        }

        // SET FILTERS
        console.log('[filters] Setting year=2026, month=4, type=Factura...');
        await page.select('#frmPrincipal\\:ano', '2026');
        await delay(1500);
        await page.select('#frmPrincipal\\:mes', '4');
        await delay(1500);
        await page.select('#frmPrincipal\\:dia', '0');
        await delay(1000);
        await page.select('#frmPrincipal\\:cmbTipoComprobante', '1');
        await delay(1000);

        // SOLVE CAPTCHA
        const token = await solveCaptcha(apiKey, sitekey);
        if (!token) {
            console.log('[ERROR] Failed to solve captcha');
            await browser.close();
            return;
        }

        // CAPTURE STATE BEFORE INJECTION
        const beforeState = await page.evaluate(() => {
            const msgs = document.getElementById('formMessages:messages');
            const tbody = document.getElementById('frmPrincipal:tablaCompRecibidos_data');
            return {
                msgsText: msgs?.innerText || '',
                msgsHTML: msgs?.innerHTML?.substring(0, 200) || '',
                tableRows: tbody ? tbody.querySelectorAll('tr').length : -1,
                tableFirstRow: tbody?.querySelector('tr')?.textContent?.trim()?.substring(0, 100) || '',
                bodyText: document.body?.innerText?.substring(0, 200) || '',
            };
        });
        console.log('\n=== STATE BEFORE rcBuscar ===');
        console.log(JSON.stringify(beforeState, null, 2));

        // INJECT TOKEN (set g-recaptcha-response value)
        console.log('\n[inject] Setting g-recaptcha-response value...');
        await page.evaluate((t) => {
            document.getElementById('g-recaptcha-response').value = t;
            document.querySelectorAll('textarea[name="g-recaptcha-response"]').forEach(el => {
                el.value = t;
                el.innerHTML = t;
            });
        }, token);

        // VERIFY TOKEN WAS SET
        const tokenSet = await page.evaluate(() => {
            const el = document.getElementById('g-recaptcha-response');
            return { exists: !!el, valueLength: el?.value?.length || 0, tagName: el?.tagName || '' };
        });
        console.log(`[inject] Token set: ${JSON.stringify(tokenSet)}`);

        // CALL rcBuscar (try both ways)
        console.log('[search] Calling rcBuscar(token)...');

        // Monitor AJAX requests
        const ajaxResponses = [];
        page.on('response', (response) => {
            const url = response.url();
            if (url.includes('comprobantes') || url.includes('frmPrincipal') || url.includes('.jsf')) {
                ajaxResponses.push({
                    url: url.substring(0, 100),
                    status: response.status(),
                    contentType: response.headers()['content-type']?.substring(0, 50) || '',
                });
            }
        });

        // Call rcBuscar with token (like Bot.cs)
        const rcResult = await page.evaluate((t) => {
            try {
                rcBuscar(t);
                return { called: true, error: null };
            } catch (e) {
                return { called: false, error: e.message };
            }
        }, token);
        console.log(`[search] rcBuscar result: ${JSON.stringify(rcResult)}`);

        // Wait and monitor
        for (let i = 1; i <= 10; i++) {
            await delay(2000);

            const afterState = await page.evaluate(() => {
                const msgs = document.getElementById('formMessages:messages');
                const tbody = document.getElementById('frmPrincipal:tablaCompRecibidos_data');
                let ajaxBusy = false;
                try { ajaxBusy = !PrimeFaces.ajax.Queue.isEmpty(); } catch {}
                const blockUI = !!document.querySelector('.ui-blockui, .ui-blockui-content, .ui-widget-overlay');

                return {
                    msgsText: msgs?.innerText?.trim() || '',
                    tableRows: tbody ? tbody.querySelectorAll('tr').length : -1,
                    tableFirstRow: tbody?.querySelector('tr')?.textContent?.trim()?.substring(0, 100) || '',
                    hasEmptyMsg: !!tbody?.querySelector('.ui-datatable-empty-message'),
                    ajaxBusy,
                    blockUI,
                };
            });

            console.log(`[wait ${i * 2}s] msgs="${afterState.msgsText}" rows=${afterState.tableRows} ajax=${afterState.ajaxBusy} block=${afterState.blockUI} first="${afterState.tableFirstRow.substring(0, 50)}"`);

            if (afterState.msgsText.includes('Captcha incorrecta')) {
                console.log('\n>>> CAPTCHA REJECTED <<<');

                // Try calling rcBuscar() WITHOUT token
                console.log('[retry] Now trying rcBuscar() without token...');
                const token2 = await solveCaptcha(apiKey, sitekey);
                if (token2) {
                    await page.evaluate((t) => {
                        document.getElementById('g-recaptcha-response').value = t;
                        document.querySelectorAll('textarea[name="g-recaptcha-response"]').forEach(el => { el.value = t; });
                    }, token2);

                    console.log('[retry] Calling rcBuscar() without args...');
                    await page.evaluate(() => { rcBuscar(); });

                    await delay(5000);
                    const retryState = await page.evaluate(() => {
                        const msgs = document.getElementById('formMessages:messages');
                        return { msgsText: msgs?.innerText?.trim() || '' };
                    });
                    console.log(`[retry] Result: msgs="${retryState.msgsText}"`);
                }
                break;
            }

            if (afterState.msgsText.includes('No existen datos')) {
                console.log('\n>>> NO DATA (search worked, no records) <<<');
                break;
            }

            if (afterState.tableRows > 0 && !afterState.hasEmptyMsg && afterState.tableFirstRow.length > 10) {
                console.log('\n>>> HAS RESULTS (search worked!) <<<');
                break;
            }
        }

        console.log(`\n[ajax] Captured responses: ${JSON.stringify(ajaxResponses, null, 2)}`);

        // Take screenshot
        await page.screenshot({ path: '/var/www/html/storage/app/private/sri-debug-after.png', fullPage: true });
        console.log('[debug] Screenshot saved');

    } catch (err) {
        console.error(`[ERROR] ${err.message}`);
    } finally {
        await browser.close();
    }
}

main();
