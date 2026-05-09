/**
 * Test script: verify 2Captcha can solve the SRI reCAPTCHA Enterprise.
 * Run: echo '{"apiKey":"YOUR_KEY"}' | node test-captcha-api.mjs
 */

const TWOCAPTCHA_IN = 'https://2captcha.com/in.php';
const TWOCAPTCHA_RES = 'https://2captcha.com/res.php';
const SITEKEY = '6LdukTQsAAAAAIcciM4GZq4ibeyplUhmWvlScuQE';
const PAGEURL = 'https://srienlinea.sri.gob.ec';

function delay(ms) { return new Promise(r => setTimeout(r, ms)); }

async function readStdin() {
    const chunks = [];
    for await (const chunk of process.stdin) chunks.push(chunk);
    return JSON.parse(Buffer.concat(chunks).toString());
}

async function testSolve(apiKey, label, extraParams = {}) {
    console.log(`\n=== Test: ${label} ===`);
    const start = Date.now();

    const params = {
        key: apiKey,
        method: 'userrecaptcha',
        googlekey: SITEKEY,
        pageurl: PAGEURL,
        json: '1',
        ...extraParams,
    };

    console.log('Params:', JSON.stringify(params, null, 2));

    const inRes = await fetch(TWOCAPTCHA_IN, {
        method: 'POST',
        body: new URLSearchParams(params),
    });
    const inData = await inRes.json();
    console.log('Submit response:', JSON.stringify(inData));

    if (inData.status !== 1) {
        console.log(`FAILED to submit: ${inData.request}`);
        return null;
    }

    const captchaId = inData.request;
    console.log(`Submitted, ID=${captchaId}. Waiting 20s...`);
    await delay(20000);

    for (let i = 0; i < 12; i++) {
        const resRes = await fetch(`${TWOCAPTCHA_RES}?${new URLSearchParams({
            key: apiKey, action: 'get', id: captchaId, json: '1',
        })}`);
        const resData = await resRes.json();

        if (resData.status === 1) {
            const elapsed = ((Date.now() - start) / 1000).toFixed(1);
            console.log(`SOLVED in ${elapsed}s! Token length: ${resData.request.length}`);
            console.log(`Token (first 50): ${resData.request.substring(0, 50)}...`);
            return resData.request;
        }

        if (resData.request !== 'CAPCHA_NOT_READY') {
            console.log(`ERROR: ${resData.request}`);
            return null;
        }

        console.log(`Not ready yet... (${(i + 1) * 5}s)`);
        await delay(5000);
    }

    console.log('TIMEOUT - no solution after 80s');
    return null;
}

async function main() {
    const { apiKey } = await readStdin();

    // Test 1: Enterprise (correct for SRI)
    const t1 = await testSolve(apiKey, 'Enterprise v2', { enterprise: '1' });

    // Test 2: Standard v2 (what Bot.cs used)
    const t2 = await testSolve(apiKey, 'Standard v2 (Bot.cs style)', {});

    console.log('\n=== Summary ===');
    console.log(`Enterprise: ${t1 ? `OK (${t1.length} chars)` : 'FAILED'}`);
    console.log(`Standard v2: ${t2 ? `OK (${t2.length} chars)` : 'FAILED'}`);
}

main().catch(e => console.error(e));
