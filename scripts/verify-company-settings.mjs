// Reuses the guarded UUID fixture and local Chrome/CDP approach from P2-T04.
import { randomUUID } from 'node:crypto';
import { spawn, spawnSync } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const origin = process.env.TAWZEE_BROWSER_ORIGIN || 'http://127.0.0.1:8017';
if (new URL(origin).hostname !== '127.0.0.1') throw new Error('Local testing server required');
const base = path.resolve('storage/app/private/company-settings-browser');
await mkdir(base, { recursive: true });
const marker = randomUUID();
const fixture = mode => {
    const result = spawnSync('php', ['tests/Support/user-management-browser-fixture.php', mode, marker], { windowsHide: true, encoding: 'utf8' });
    if (result.status !== 0) throw new Error(`Fixture ${mode} failed: ${result.stderr}`);
};
const browser = spawn(process.env.TAWZEE_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=9237',
    `--user-data-dir=${base}/chrome`, '--disable-background-networking', 'about:blank',
], { windowsHide: true, stdio: 'ignore' });
let socket, nextId = 0;
const pending = new Map(), errors = [], results = [];
const call = (method, params = {}) => new Promise((resolve, reject) => {
    const id = ++nextId;
    const timer = setTimeout(() => { pending.delete(id); reject(new Error(`Timeout: ${method}`)); }, 20000);
    pending.set(id, { resolve, reject, timer });
    socket.send(JSON.stringify({ id, method, params }));
});
const evaluate = async expression => {
    const result = await call('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    if (result.exceptionDetails) throw new Error(JSON.stringify(result.exceptionDetails));
    return result.result.value;
};
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));
const waitFor = async expression => {
    for (let i = 0; i < 150; i++) { if (await evaluate(expression)) return; await wait(100); }
    throw new Error(`Condition timed out: ${expression}`);
};
const click = selector => evaluate(`(() => { const el = document.querySelector(${JSON.stringify(selector)}); el.focus(); el.click(); })()`);
const fill = (selector, value) => evaluate(`(() => { const el = document.querySelector(${JSON.stringify(selector)}); el.value = ${JSON.stringify(value)}; el.dispatchEvent(new Event('input', { bubbles: true })); })()`);
const key = async (key, code, keyCode) => {
    await call('Input.dispatchKeyEvent', { type: 'keyDown', key, code, windowsVirtualKeyCode: keyCode });
    await call('Input.dispatchKeyEvent', { type: 'keyUp', key, code, windowsVirtualKeyCode: keyCode });
};
const shot = async name => {
    const result = await call('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    await writeFile(`${base}/${name}.png`, Buffer.from(result.data, 'base64'));
};
const assert = (condition, message) => { if (!condition) throw new Error(message); };

try {
    fixture('setup');
    let tab;
    for (let i = 0; i < 60; i++) {
        try { await fetch('http://127.0.0.1:9237/json'); await wait(1500); tab = await (await fetch('http://127.0.0.1:9237/json/new?about:blank', { method: 'PUT' })).json(); break; }
        catch { await wait(200); }
    }
    if (!tab) throw new Error('Chrome unavailable');
    socket = new WebSocket(tab.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
    socket.onmessage = ({ data }) => {
        const message = JSON.parse(data);
        if (message.method === 'Runtime.exceptionThrown') errors.push(message.params.exceptionDetails.text);
        if (message.method === 'Runtime.consoleAPICalled' && message.params.type === 'error') errors.push('console.error');
        const request = pending.get(message.id);
        if (request) { clearTimeout(request.timer); pending.delete(message.id); message.error ? request.reject(new Error(message.error.message)) : request.resolve(message.result); }
    };
    await call('Page.enable'); await call('Runtime.enable'); await call('Network.enable');
    await call('Page.navigate', { url: `${origin}/login` });
    await waitFor('document.readyState === "complete" && !!window.Livewire && !!document.querySelector("#email")');
    await fill('#email', `users-browser-${marker}@example.test`); await fill('#password', 'Browser-test-only-password-123');
    await evaluate('document.querySelector("form").requestSubmit()');
    await waitFor('location.pathname === "/pending-setup" && !!document.querySelector("a[href$=\'/users\']")');
    await click('a[href$="/users"]');
    await waitFor('location.pathname === "/users" && !!document.querySelector("a[href$=\'/company/settings\']")');
    await call('Page.navigate', { url: `${origin}/company/settings` });
    await waitFor('location.pathname === "/company/settings" && !!document.querySelector("#save-settings") && !!window.Livewire');
    await evaluate('document.fonts.ready.then(() => true)');
    for (const width of [1440, 768, 375]) {
        await call('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: width === 375 });
        await wait(150);
        const layout = await evaluate(`({ lang: document.documentElement.lang, rtl: getComputedStyle(document.body).direction === 'rtl', overflow: document.documentElement.scrollWidth > innerWidth, labels: ['user-name', 'negative-stock'].every(id => !!document.querySelector('label[for="'+id+'"]')), activeNavigation: [...document.querySelectorAll('nav a[aria-current="page"]')].every(el => el.pathname === '/company/settings'), localFont: document.fonts.check('14px "Noto Sans Arabic"') })`);
        assert(layout.lang === 'ar' && layout.rtl && !layout.overflow && layout.labels && layout.activeNavigation && layout.localFont, `Layout failed ${width}`);
        assert(await evaluate('document.querySelector("#save-settings").matches(":disabled")'), 'Unchanged save must be disabled');
        if (width < 1024) {
            await click('#mobile-menu-button');
            await waitFor('document.querySelector("#mobile-navigation").open');
            await shot(`navigation-${width}`);
            await key('Escape', 'Escape', 27);
            await waitFor('!document.querySelector("#mobile-navigation").open');
            assert(await evaluate('document.activeElement.id === "mobile-menu-button"'), 'Drawer focus not restored');
        }
        await shot(`settings-${width}`);
        await fill('#user-name', '');
        await waitFor('!document.querySelector("#save-settings").matches(":disabled")');
        await click('#save-settings');
        await waitFor('document.querySelector("#user-name").getAttribute("aria-invalid") === "true"');
        assert(await evaluate('document.querySelector("#user-name-feedback").textContent.includes("حقل اسم الشركة مطلوب")'), 'Arabic validation absent');
        await shot(`validation-${width}`);
        const name = `شركة المدار — ${width}`;
        await fill('#user-name', name);
        await evaluate('document.querySelector("#user-name").focus()');
        await key('Tab', 'Tab', 9);
        assert(await evaluate('document.activeElement.id === "negative-stock"'), 'Toggle keyboard navigation failed');
        assert(await evaluate('document.activeElement.matches(":focus-visible") && getComputedStyle(document.activeElement.nextElementSibling).outlineWidth !== "0px"'), 'Toggle focus not visible');
        const oldState = await evaluate('document.querySelector("#negative-stock").checked');
        await key(' ', 'Space', 32);
        await waitFor(`document.querySelector('#negative-stock').checked === ${!oldState}`);
        assert(await evaluate(`document.querySelector('#negative-stock').getAttribute('aria-checked') === '${!oldState}'`), 'Toggle accessible state mismatch');
        assert(await evaluate('document.body.innerText.includes("لديك تغييرات غير محفوظة")'), 'Dirty status missing');
        await call('Network.emulateNetworkConditions', { offline: false, latency: 600, downloadThroughput: -1, uploadThroughput: -1 });
        await click('#save-settings');
        await waitFor('document.querySelector("#save-settings").disabled');
        await shot(`loading-${width}`);
        await waitFor(`[...document.querySelectorAll('[data-company-name]')].every(el => el.textContent === ${JSON.stringify(name)})`);
        await call('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
        await waitFor('document.body.innerText.includes("تم حفظ إعدادات الشركة بنجاح")');
        assert(await evaluate('document.querySelector("#save-settings").matches(":disabled")'), 'Save not disabled after commit');
        await shot(`saved-${width}`);
        await evaluate('window.__settingsBeforeReload = true');
        await call('Page.reload');
        await waitFor(`!window.__settingsBeforeReload && document.readyState === 'complete' && !!window.Livewire && document.querySelector('#user-name')?.value === ${JSON.stringify(name)}`);
        assert(await evaluate(`document.querySelector('#negative-stock').checked === ${!oldState}`), 'Setting did not persist');
        results.push({ width, ...layout, mobileNavigation: width < 1024, validation: true, toggleKeyboard: true, loadingDisabled: true, toast: true, shellNameUpdated: true, persistedAfterReload: true });
    }
    assert(errors.length === 0, `Browser errors: ${errors.join(', ')}`);
    await writeFile(`${base}/results.json`, JSON.stringify({ results, browserErrors: errors }, null, 2));
    console.log(JSON.stringify({ results, browserErrors: errors }, null, 2));
} catch (error) {
    if (socket?.readyState === WebSocket.OPEN) await shot('failure').catch(() => {});
    throw error;
} finally {
    if (socket?.readyState === WebSocket.OPEN) { try { await call('Browser.close'); } catch {} socket.close(); }
    browser.kill();
    fixture('cleanup');
}
