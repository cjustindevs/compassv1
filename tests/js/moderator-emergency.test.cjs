const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function page(kind = null) {
    const requests = [], intervals = [], handlers = {}, content = {outerHTML: 'initial'}, document = {hidden: false, activeElement: null};
    let signature = 'one', html = '<section>updated</section>', fail = false, dialog = null;
    const nodes = {statEmergency: {textContent: '1'}, emergencyBadge: {style: {}}, 'dashboard-analytics': {innerHTML: 'initial'}};
    document.getElementById = id => id === 'moderatorEmergencyConfig' ? {content: JSON.stringify({kind, signature: 'one', stats: '/moderator/emergency/stats'})} : kind === 'dashboard' ? nodes[id] : content;
    document.querySelectorAll = selector => selector === 'dialog[open]' && dialog ? [dialog] : [];
    document.addEventListener = (name, fn) => { handlers[name] = fn; };
    const navigator = {onLine: true};
    vm.runInNewContext(fs.readFileSync('public/js/moderator-emergency.js', 'utf8'), {
        document, navigator, location: {href: '/moderator/emergency?page=2'}, window: {addEventListener: (name, fn) => { handlers[name] = fn; }},
        setInterval: (fn, ms) => intervals.push({fn, ms}),
        fetch: async (url, options) => { requests.push({url, options}); return {ok: !fail, json: async () => ({signature, html, overview_signature: signature, overview_html: html, emergency_open: signature === 'one' ? 1 : 0})}; },
    });
    return {requests, intervals, document, navigator, content, nodes, handlers, state: (next, body) => { signature = next; if (body) html = body; },
        fail: value => { fail = value; }, dialog: value => { dialog = value; }, poll: () => intervals[0].fn(), flush: () => new Promise(resolve => setImmediate(resolve))};
}

test('status signature changes refresh the operational content without reloading or losing pagination', async () => {
    const r = page(); await r.flush(); assert.equal(r.content.outerHTML, 'initial'); assert.equal(r.intervals[0].ms, 2000);
    r.state('two', '<section>No active cases</section>'); await r.poll();
    assert.equal(r.content.outerHTML, '<section>No active cases</section>');
    assert.equal(r.requests.at(-1).url, '/moderator/emergency?page=2'); assert.equal(r.requests.at(-1).options.cache, 'no-store');
    const count = r.requests.length; await r.poll(); assert.equal(r.requests.length, count + 1);
});

test('emergency popup does not block state synchronization but other dialogs and offline tabs do', async () => {
    const r = page(); await r.flush(); r.dialog({id: 'staffEmergencyNotice'}); r.state('two'); await r.poll();
    assert.equal(r.content.outerHTML, '<section>updated</section>'); const count = r.requests.length;
    r.dialog({id: 'sensitiveForm'}); await r.poll(); r.dialog(null);
    r.document.hidden = true; await r.poll(); r.document.hidden = false;
    r.navigator.onLine = false; await r.poll(); assert.equal(r.requests.length, count);
});

test('failed fragment requests preserve current content and retry on the next poll', async () => {
    const r = page(); await r.flush(); r.state('two'); r.fail(true); await r.poll(); assert.equal(r.content.outerHTML, 'initial');
    r.fail(false); await r.poll(); assert.equal(r.content.outerHTML, '<section>updated</section>');
});

test('dashboard emergency counts and charts update together without a page reload', async () => {
    const r = page('dashboard'); await r.flush();
    assert.equal(r.nodes.statEmergency.textContent, 1);
    r.dialog({id: 'staffEmergencyNotice'}); r.state('two', '<section>Resolved emergency summary</section>'); await r.poll();
    assert.equal(r.nodes.statEmergency.textContent, 0);
    assert.equal(r.nodes.emergencyBadge.hidden, true);
    assert.equal(r.nodes['dashboard-analytics'].innerHTML, '<section>Resolved emergency summary</section>');
    assert.equal(r.requests.at(-1).url, '/moderator/emergency/stats');
});
