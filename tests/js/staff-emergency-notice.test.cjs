const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function room(storage=new Map()) {
    const handlers = {}, intervals = [], requests = [], slots = {}, links = [];
    let state = {id: 4, reference: 'token4', title: 'Emergency', message: 'Open case', url: '/moderator/emergency'}, fail = false, other = false, deferred;
    const dialog = {id: 'staffEmergencyNotice', open: false, opens: 0, closes: 0,
        querySelector: name => slots[name] ??= {},
        showModal() { this.open = true; this.opens++; }, close() { this.open = false; this.closes++; },
        addEventListener: (name, fn) => { handlers[name] = fn; }};
    const document = {hidden: false, getElementById: id => id === 'staffEmergencyNotice' ? dialog : {content: JSON.stringify({next: '/emergency-notice', dismiss: '/emergency-notice/dismiss', csrf: 'test', openLabel: 'Open queue'})},
        querySelectorAll: () => [...(dialog.open ? [dialog] : []), ...(other ? [{}] : [])], addEventListener: (name, fn) => { handlers[name] = fn; }};
    document.querySelector = selector => selector==='meta[name="compass-notices"]'?{content:JSON.stringify({user:7})}:null;
    const navigator = {onLine: true};
    const context={
        document, navigator, location: {assign: url => links.push(url)}, window: {addEventListener: (name, fn) => { handlers[name] = fn; }},
        localStorage:{getItem:key=>storage.get(key)||null,setItem:(key,value)=>storage.set(key,value)},
        setInterval: (fn, ms) => intervals.push({fn, ms}), encodeURIComponent,
        fetch: async (url, options) => { requests.push({url, options}); if (deferred) return deferred; return {ok: !fail, json: async () => ({notice: state})}; },
    };
    vm.runInNewContext(fs.readFileSync('public/js/notification-presentation.js','utf8'),context);
    vm.runInNewContext(fs.readFileSync('public/js/staff-emergency-notice.js', 'utf8'), context);
    return {dialog, slots, document, navigator, requests, links, handlers, intervals, storage,
        state: next => { state = next; }, fail: value => { fail = value; }, other: value => { other = value; }, defer: value => { deferred = value; },
        poll: () => intervals[0].fn(), flush: () => new Promise(resolve => setImmediate(resolve))};
}

test('open emergency dialog keeps revalidating and closes when its case is resolved', async () => {
    const r = room(); await r.flush();
    assert.equal(r.dialog.open, true); assert.equal(r.intervals[0].ms, 2000);
    await r.poll(); assert.equal(r.dialog.opens, 1); assert.equal(r.requests.at(-1).url, '/emergency-notice?current=token4');
    r.state(null); await r.poll(); assert.equal(r.dialog.open, false);
    await r.poll(); assert.equal(r.dialog.opens, 1);
    assert.equal(r.requests.at(-1).options.cache, 'no-store');
});

test('page navigation does not reopen a displayed emergency and duplicate IDs for its case stay quiet',async()=>{
    const storage=new Map(),first=room(storage);first.state({id:4,case_key:'alert:8',reference:'token4',title:'Emergency',message:'Review',url:'/moderator/emergency'});
    await first.flush();assert.equal(first.dialog.opens,1);
    const next=room(storage);next.state({id:9,case_key:'alert:8',reference:'token9',title:'Duplicate case',message:'Review',url:'/moderator/emergency'});
    await next.flush();assert.equal(next.dialog.opens,0);
    assert.equal(next.requests.at(-1).options.method,'POST');
    next.state({id:10,case_key:'alert:10',reference:'token10',title:'New incident',message:'Review',url:'/moderator/emergency'});
    await next.poll();assert.equal(next.dialog.opens,1);
});

test('another still-active emergency replaces the resolved case without reopening the dialog', async () => {
    const r = room(); await r.flush();
    r.state({id: 5, reference: 'token5', title: 'New emergency', message: 'Another case', url: '/moderator/emergency'});
    await r.poll(); assert.equal(r.dialog.opens, 1); assert.equal(r.slots['#staffEmergencyTitle'].textContent, 'New emergency');
    await r.poll(); assert.equal(r.requests.at(-1).url, '/emergency-notice?current=token5');
});

test('Open revalidates a resolved case before dismissing or navigating', async () => {
    const r = room(); await r.flush(); r.state(null);
    await r.slots['[data-open]'].onclick();
    assert.equal(r.dialog.open, false); assert.equal(r.links.length, 0);
    assert.equal(r.requests.filter(item => item.options.method === 'POST').length, 0);
});

test('successful dismissal uses the authenticated token and does not reopen a dismissed case', async () => {
    const r = room(); await r.flush(); await r.slots['[data-dismiss]'].onclick();
    const post = r.requests.at(-1); assert.equal(post.options.method, 'POST');
    assert.equal(post.options.headers['X-CSRF-TOKEN'], 'test'); assert.equal(JSON.parse(post.options.body).reference, 'token4');
    assert.equal(r.dialog.open, false); r.state(null); await r.poll(); assert.equal(r.dialog.opens, 1);
});

test('offline, hidden and unrelated dialog states pause polling and a failed request preserves the visible case', async () => {
    const r = room(); await r.flush(); const count = r.requests.length;
    r.document.hidden = true; await r.poll(); r.document.hidden = false;
    r.navigator.onLine = false; await r.poll(); r.navigator.onLine = true;
    r.other(true); await r.poll(); r.other(false); assert.equal(r.requests.length, count);
    r.fail(true); await r.poll(); assert.equal(r.dialog.open, true);
    r.fail(false); r.state(null); await r.handlers.online(); assert.equal(r.dialog.open, false);
});
