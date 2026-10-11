const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {test} = require('node:test');
const vm = require('node:vm');

function setup(fetch) {
    const status = {textContent: ''};
    const linked = {textContent: 'No recovery email linked yet.'};
    const password = {value: 'OriginalPassword!1'};
    const otp = {focused: false, focus() { this.focused = true; }};
    function form(isSend) {
        const button = {disabled: false};
        return {
            action: isSend ? '/settings/recovery-email/send' : '/settings/recovery-email/verify',
            hidden: !isSend, button, resetCount: 0,
            addEventListener(event, callback) { this.submit = callback; },
            matches() { return isSend; },
            reportValidity() { return true; },
            reset() { this.resetCount++; },
            querySelector(selector) {
                if (selector.startsWith('button')) return button;
                if (selector.includes('current_password')) return password;
                if (selector.includes('otp')) return otp;
            },
        };
    }
    const send = form(true);
    const verify = form(false);
    const recovery = {
        querySelectorAll() { return [send, verify]; },
        querySelector(selector) {
            return {'[data-recovery-status]': status, '[data-recovery-verify]': verify, '[data-recovery-linked]': linked}[selector];
        },
    };
    vm.runInNewContext(readFileSync('resources/js/recovery-email.js', 'utf8'), {
        document: {querySelector() { return recovery; }}, fetch,
        FormData: class {constructor(form) {this.form = form;}},
    });
    return {send, verify, status, password, otp, linked};
}

test('sending a code uses authenticated same-origin POST and clears the confirmation password', async () => {
    let request;
    const ui = setup(async (url, options) => {
        request = {url, options};
        return {ok: true, json: async () => ({message: 'Code sent.'})};
    });
    await ui.send.submit({preventDefault() {}});
    assert.equal(request.url, '/settings/recovery-email/send');
    assert.equal(request.options.method, 'POST');
    assert.equal(request.options.credentials, 'same-origin');
    assert.equal(request.options.headers.Accept, 'application/json');
    assert.equal(ui.password.value, '');
    assert.equal(ui.verify.hidden, false);
    assert.equal(ui.otp.focused, true);
    assert.equal(ui.send.button.disabled, false);
});

test('failed validation and interrupted delivery leave the saved address unchanged and allow retry', async () => {
    const ui = setup(async () => ({ok: false, json: async () => ({errors: {otp: ['Incorrect code.']}})}));
    await ui.verify.submit({preventDefault() {}});
    assert.equal(ui.status.textContent, 'Incorrect code.');
    assert.equal(ui.linked.textContent, 'No recovery email linked yet.');
    assert.equal(ui.verify.resetCount, 0);
    assert.equal(ui.verify.button.disabled, false);
    const offline = setup(async () => {throw Error('offline');});
    await offline.send.submit({preventDefault() {}});
    assert.match(offline.status.textContent, /Could not complete verification/);
    assert.equal(offline.send.button.disabled, false);
    assert.equal(offline.verify.hidden, true);
});

test('only successful verification confirms a saved address and clears the one-time code form', async () => {
    const ui = setup(async () => ({ok: true, json: async () => ({message: 'Verified and saved.'})}));
    ui.verify.hidden = false;
    await ui.verify.submit({preventDefault() {}});
    assert.equal(ui.linked.textContent, 'Verified — ready for password-reset links.');
    assert.equal(ui.verify.resetCount, 1);
    assert.equal(ui.verify.hidden, true);
    assert.equal(ui.status.textContent, 'Verified and saved.');
    assert.equal(ui.verify.button.disabled, false);
});
