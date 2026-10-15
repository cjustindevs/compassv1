const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function popup() {
    class Node {
        constructor() { this.handlers = {}; this.textContent = ''; this.open = false; }
        addEventListener(name, fn) { this.handlers[name] = fn; }
        querySelector(selector) { return nodes[selector]; }
        showModal() { this.open = true; }
        close() { this.open = false; }
    }
    const nodes = Object.fromEntries(['#staffEmergencyTitle', 'p', '[data-open]', '[data-dismiss]', '[data-error]'].map(key => [key, new Node()]));
    const dialog = new Node(), intervals = [], requests = [], events = {}, assigned = [];
    const notice = { reference: 'encrypted-reference', title: 'Urgent emergency referral assigned', message: 'An Adviser raised an emergency referral to you.', url: '/professional/referral/8' };
    let otherDialog = false, dismissOk = true;
    let script = fs.readFileSync('resources/views/partials/emergency-notice.blade.php', 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
    script = script.replace("@json(route('emergency-notice.next'))", JSON.stringify('/emergency-notice'))
        .replace("@json(route('emergency-notice.dismiss'))", JSON.stringify('/emergency-notice/dismiss'))
        .replace('@json(csrf_token())', JSON.stringify('test-token'))
        .replace("@json(auth()->user()->role === 'professional' ? 'Review assigned referral' : 'Open emergency queue')", JSON.stringify('Review assigned referral'));
    assert.equal(script.includes('@json'), false);
    const document = { hidden: false, getElementById: () => dialog, querySelector: () => otherDialog ? {} : null,
        addEventListener: (name, fn) => { events[name] = fn; } };
    const navigator = { onLine: true };
    vm.runInNewContext(script, { document, navigator,
        window: { addEventListener: (name, fn) => { events[name] = fn; } },
        location: { assign: url => assigned.push(url) },
        setInterval: (fn, ms) => intervals.push({ fn, ms }),
        fetch: async (url, options) => { requests.push({ url, options }); return { ok: options.method === 'POST' ? dismissOk : true, json: async () => ({ notice }) }; },
    });
    events.DOMContentLoaded();
    return { nodes, dialog, intervals, requests, assigned, events, document, navigator,
        block: value => { otherDialog = value; }, dismissOk: value => { dismissOk = value; },
        flush: () => new Promise(resolve => setImmediate(resolve)), poll: () => intervals[0].fn(),
    };
}

test('Professional emergency popup shows the correct message and opens its assigned referral', async () => {
    const r = popup(); await r.flush();
    assert.equal(r.dialog.open, true);
    assert.equal(r.nodes['#staffEmergencyTitle'].textContent, 'Urgent emergency referral assigned');
    assert.equal(r.nodes['[data-open]'].textContent, 'Review assigned referral');
    assert.equal(r.intervals[0].ms, 2000);
    assert.equal(r.requests[0].options.cache, 'no-store');
    await r.nodes['[data-open]'].onclick();
    assert.equal(r.dialog.open, false);
    assert.deepEqual(r.assigned, ['/professional/referral/8']);
    assert.deepEqual(JSON.parse(r.requests[1].options.body), { reference: 'encrypted-reference' });
});

test('a popup dismissal failure preserves the dialog and does not navigate', async () => {
    const r = popup(); await r.flush(); r.dismissOk(false);
    await r.nodes['[data-open]'].onclick();
    assert.equal(r.dialog.open, true);
    assert.equal(r.assigned.length, 0);
    assert.match(r.nodes['[data-error]'].textContent, /Unable to save/);
});

test('the emergency popup waits for other dialogs and pauses on hidden or offline pages', async () => {
    const r = popup(); await r.flush(); await r.nodes['[data-dismiss]'].onclick();
    const count = r.requests.length;
    r.block(true); await r.poll(); assert.equal(r.requests.length, count);
    r.block(false); r.document.hidden = true; await r.poll(); assert.equal(r.requests.length, count);
    r.document.hidden = false; r.navigator.onLine = false; await r.poll(); assert.equal(r.requests.length, count);
    r.navigator.onLine = true; await r.events.online(); await r.flush();
    assert.equal(r.dialog.open, true);
    assert.equal(r.assigned.length, 0);
});
