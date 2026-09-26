// Local Chrome/CDP verification, using only Node's built-ins. Start a guarded testing server first.
import { randomUUID } from 'node:crypto';
import { spawn, spawnSync } from 'node:child_process';
import { mkdir, writeFile } from 'node:fs/promises';
import path from 'node:path';

const origin = process.env.TAWZEE_BROWSER_ORIGIN || 'http://127.0.0.1:8016';
if (new URL(origin).hostname !== '127.0.0.1') throw new Error('Local testing server required');
const base = path.resolve('storage/app/private/user-management-browser');
await mkdir(base, { recursive: true });
const marker = randomUUID();
const email = `users-browser-${marker}@example.test`;
const fixture = mode => {
    const result = spawnSync('php', ['tests/Support/user-management-browser-fixture.php', mode, marker], { windowsHide: true, encoding: 'utf8' });
    if (result.status !== 0) throw new Error(`Fixture ${mode} failed: ${result.stderr}`);
};
const browser = spawn(process.env.TAWZEE_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--remote-debugging-port=9236',
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
    for (let attempt = 0; attempt < 150; attempt++) { if (await evaluate(expression)) return; await wait(100); }
    throw new Error(`Condition timed out: ${expression}`);
};
const click = selector => evaluate(`(() => { const el = document.querySelector(${JSON.stringify(selector)}); el.focus(); el.click(); })()`);
const fill = (selector, value) => evaluate(`(() => { const el = document.querySelector(${JSON.stringify(selector)}); el.value = ${JSON.stringify(value)}; el.dispatchEvent(new Event(el.tagName === 'SELECT' ? 'change' : 'input', { bubbles: true })); })()`);
const key = async (key, code, keyCode) => {
    await call('Input.dispatchKeyEvent', { type: 'keyDown', key, code, windowsVirtualKeyCode: keyCode });
    await call('Input.dispatchKeyEvent', { type: 'keyUp', key, code, windowsVirtualKeyCode: keyCode });
};
const shot = async name => {
    const result = await call('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
    await writeFile(`${base}/${name}.png`, Buffer.from(result.data, 'base64'));
};
const assert = (condition, message) => { if (!condition) throw new Error(message); };

try {
    fixture('setup');
    let tab;
    for (let attempt = 0; attempt < 60; attempt++) {
        try { await fetch('http://127.0.0.1:9236/json'); await wait(1500); tab = await (await fetch('http://127.0.0.1:9236/json/new?about:blank', { method: 'PUT' })).json(); break; }
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
    await fill('#email', email); await fill('#password', 'Browser-test-only-password-123');
    await evaluate('document.querySelector("form").requestSubmit()');
    await waitFor('location.pathname === "/pending-setup" && !!document.querySelector("a[href$=\'/users\']")');
    await click('a[href$="/users"]');
    await waitFor('location.pathname === "/users" && !!document.querySelector("#add-user") && !!window.Livewire');
    await evaluate('document.fonts.ready.then(() => true)');

    for (const width of [1440, 768, 375]) {
        await call('Emulation.setDeviceMetricsOverride', { width, height: 1000, deviceScaleFactor: 1, mobile: width === 375 });
        await wait(200);
        const layout = await evaluate(`({ lang: document.documentElement.lang, rtl: getComputedStyle(document.body).direction === 'rtl', overflow: document.documentElement.scrollWidth > innerWidth, table: getComputedStyle(document.querySelector('#users-table')).display !== 'none', cards: getComputedStyle(document.querySelector('#users-cards')).display !== 'none', localFont: document.fonts.check('14px "Noto Sans Arabic"') })`);
        assert(layout.lang === 'ar' && layout.rtl && !layout.overflow && layout.localFont, `Layout failed ${width}`);
        assert(width === 1440 ? layout.table && !layout.cards : layout.cards && !layout.table, 'Responsive list failed');
        if (width < 1024) {
            await click('#mobile-menu-button');
            await waitFor('document.querySelector("#mobile-navigation").open');
            await shot(`navigation-${width}`);
            await key('Escape', 'Escape', 27);
            await waitFor('!document.querySelector("#mobile-navigation").open');
            assert(await evaluate('document.activeElement.id === "mobile-menu-button"'), 'Drawer focus restoration failed');
        }
        await shot(`users-${width}`);
        await click('#add-user');
        await waitFor('document.querySelector("#user-form-dialog").open');
        assert(await evaluate('[...document.querySelectorAll("#user-form-dialog input, #user-form-dialog select")].every(el => !!document.querySelector(`label[for="${el.id}"]`))'), 'Form labels missing');
        for (let index = 0; index < 10; index++) await key('Tab', 'Tab', 9);
        assert(await evaluate('document.querySelector("#user-form-dialog").contains(document.activeElement)'), 'Dialog focus trap failed');
        const focused = await evaluate('({visible: document.activeElement.matches(":focus-visible"), width: getComputedStyle(document.activeElement).outlineWidth})');
        assert(focused.visible && focused.width !== '0px', 'Keyboard focus not visible');
        await evaluate('document.querySelector("#user-form-dialog form").requestSubmit()');
        await waitFor('document.querySelector("#user-name").getAttribute("aria-invalid") === "true"');
        await shot(`validation-${width}`);
        assert(await evaluate('!document.querySelector("#user-form-dialog").textContent.includes("validation.required")'), 'Broken validation translation');
        await key('Escape', 'Escape', 27);
        await waitFor('!document.querySelector("#user-form-dialog").open');
        await waitFor('document.activeElement.id === "add-user"');
        results.push({ width, ...layout, navigation: width < 1024 ? 'drawer checked' : 'sidebar checked', validation: true, keyboard: true });
    }

    await fill('#user-search', 'لا توجد نتيجة بهذا الاسم');
    await waitFor('!!document.querySelector("#users-empty")');
    await shot('empty-375');
    await click('button[wire\\:click="clearFilters"]');
    await waitFor('!document.querySelector("#users-empty")');
    await click('button[wire\\:click="nextPage"]');
    await waitFor('document.querySelector("[aria-current=page]").textContent.includes("2") || [...document.querySelectorAll("span[aria-current=page]")].some(el => el.textContent.includes("2"))');
    await click('button[wire\\:click="previousPage"]');
    await fill('#status-filter', 'inactive');
    await waitFor('document.querySelectorAll("#users-cards > li").length === 1');
    await fill('#status-filter', '');
    const roleId = await evaluate('[...document.querySelector("#role-filter").options].find(option => option.textContent === "مبيعات").value');
    await fill('#role-filter', roleId);
    await waitFor('document.querySelectorAll("#users-cards > li").length === 2');
    await fill('#role-filter', '');
    await click('#add-user');
    await waitFor('document.querySelector("#user-form-dialog").open');
    await fill('#user-name', 'زميل المتصفح');
    await fill('#user-email', `created-${marker}@example.test`);
    await fill('#user-password', 'Browser-test-only-password-123');
    await fill('#user-password_confirmation', 'Browser-test-only-password-123');
    await fill('#user-role', roleId);
    await call('Network.emulateNetworkConditions', { offline: false, latency: 600, downloadThroughput: -1, uploadThroughput: -1 });
    await evaluate('document.querySelector("#user-form-dialog form").requestSubmit()');
    await waitFor('document.querySelector("#user-form-dialog button[type=submit]").disabled');
    await shot('loading-375');
    await waitFor('!document.querySelector("#user-form-dialog").open');
    await call('Network.emulateNetworkConditions', { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
    assert(await evaluate('document.body.innerText.includes("تم حفظ بيانات المستخدم بنجاح")'), 'Success toast absent');
    await fill('#user-search', `created-${marker}@example.test`);
    await waitFor('document.querySelectorAll("#users-cards > li").length === 1 && document.querySelector("#users-cards").textContent.includes("زميل المتصفح")');
    await click('#users-cards button[wire\\:click^="openEdit"]');
    await waitFor('document.querySelector("#user-form-dialog").open && !document.querySelector("#user-password")');
    await fill('#user-name', 'زميل بعد التعديل');
    await shot('edit-375');
    await evaluate('document.querySelector("#user-form-dialog form").requestSubmit()');
    await waitFor('!document.querySelector("#user-form-dialog").open && document.querySelector("#users-cards").textContent.includes("زميل بعد التعديل")');
    await click('#users-cards button[wire\\:click^="confirmStatus"]');
    await waitFor('document.querySelector("#user-status-dialog").open');
    await shot('confirm-disable-375');
    await click('#user-status-dialog button[wire\\:click="changeStatus"]');
    await waitFor('!document.querySelector("#user-status-dialog").open && document.querySelector("#users-cards").textContent.includes("معطل")');
    await click('#users-cards button[wire\\:click^="confirmStatus"]');
    await waitFor('document.querySelector("#user-status-dialog").open');
    await click('#user-status-dialog button[wire\\:click="changeStatus"]');
    await waitFor('!document.querySelector("#user-status-dialog").open && document.querySelector("#users-cards").textContent.includes("نشط")');
    assert(await evaluate('document.documentElement.scrollWidth <= innerWidth'), 'Final overflow');
    assert(errors.length === 0, `Browser errors: ${errors.join(', ')}`);
    results.push({ search: true, roleFilter: true, statusFilter: true, pagination: true, emptyResults: true, create: true, edit: true, deactivate: true, reactivate: true, loadingDisabled: true, successToast: true });
    await writeFile(`${base}/results.json`, JSON.stringify({ results, browserErrors: errors }, null, 2));
    console.log(JSON.stringify({ results, browserErrors: errors }, null, 2));
} catch (error) {
    if (socket?.readyState === WebSocket.OPEN) { await shot('failure').catch(() => {}); }
    throw error;
} finally {
    if (socket?.readyState === WebSocket.OPEN) { try { await call('Browser.close'); } catch {} socket.close(); }
    browser.kill();
    fixture('cleanup');
}
