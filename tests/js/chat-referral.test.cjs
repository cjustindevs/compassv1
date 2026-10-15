const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function room(isSeeker = true) {
    class Node {
        constructor() { this.handlers = {}; this.open = false; this.hidden = false; this.children = []; this.checked = false; this.opens = 0; }
        addEventListener(name, fn) { this.handlers[name] = fn; }
        append(...nodes) { this.children.push(...nodes); }
        prepend(node) { this.children.unshift(node); }
        querySelector() { return frame; }
        showModal() { this.open = true; this.opens++; }
        close() { const wasOpen = this.open; this.open = false; if (wasOpen) this.handlers.close?.(); }
        focus() { this.focused = true; }
    }
    const ids = {};
    for (const id of ['chatReferralConfig', 'referralConsentDialog', 'referralConsentCheck', 'referralAccept', 'referralDecline', 'referralDismiss', 'referralConsentTerms', 'referralConsentError', 'chatIdentityDialog', 'chatIdentityOpen', 'chatEmergencyBanner', 'chatEmergencyTitle', 'chatEmergencyStatus', 'chatReferralOpen', 'professionalSupportCard', 'chatSupportOpen', 'professionalSupportStatus', 'referralConsentText']) ids[id] = new Node();
    ids.chatReferralConfig.content = JSON.stringify({ isSeeker, stateUrl: '/session/55/referral-prompt', csrf: 'test-token', sessionId: 55 });
    const frame = { src: '', contentWindow: {} }, intervals = [], requests = [], windowEvents = {}, documentEvents = {};
    let state = { referral: null, emergency: null }, postOk = true, otherDialog = false, defer = null;
    const document = {
        hidden: false, getElementById: id => ids[id], createElement: () => new Node(),
        querySelector: selector => selector === 'dialog[open]' ? (otherDialog || Object.values(ids).find(node => node.open) || null) : null,
        addEventListener: (name, fn) => { documentEvents[name] = fn; },
    };
    const navigator = { onLine: true }, location = { origin: 'https://compass.test' };
    const context = { document, navigator, location, Set,
        window: { confirm: () => true, addEventListener: (name, fn) => { windowEvents[name] = fn; } },
        setInterval: (fn, ms) => intervals.push({ fn, ms }),
        fetch: async (url, options) => {
            requests.push({ url, options });
            if (defer) return defer;
            if (options.method === 'POST') return { ok: postOk, json: async () => ({ success: postOk, next_url: '/seeker/referrals' }) };
            return { ok: true, json: async () => state };
        },
    };
    vm.runInNewContext(fs.readFileSync('public/js/chat-referral.js', 'utf8'), context);
    return { ids, frame, intervals, requests, windowEvents, documentEvents, document, navigator, location,
        state: value => { state = value; }, postOk: value => { postOk = value; },
        block: value => { otherDialog = value; }, defer: value => { defer = value; },
        poll: () => intervals[0].fn(), flush: () => new Promise(resolve => setImmediate(resolve)),
    };
}

function accepted(id = 8) {
    return { referral: { id, status: 'pending_professional', help_seeker_consent: true, has_identity: false,
        can_provide_identity: true, needs_identity_disclosure: true,
        identity_url: `/referrals/${id}/identity?embedded=1`, consent_url: `/referrals/${id}/consent-request` },
        consent: { decision: 'accepted' }, emergency: null };
}

test('accepted referral opens identity consent inside live chat, once, with an explicit reopen button', async () => {
    const r = room(); await r.flush(); r.state(accepted()); await r.poll();
    assert.equal(r.ids.chatIdentityDialog.open, true);
    assert.equal(r.frame.src, '/referrals/8/identity?embedded=1');
    assert.equal(r.ids.chatIdentityOpen.hidden, false);
    assert.equal(r.ids.referralConsentDialog.open, false);
    assert.equal(r.intervals[0].ms, 2000);
    assert.equal(r.requests[0].options.cache, 'no-store');
    await r.poll(); assert.equal(r.ids.chatIdentityDialog.opens, 1);
    r.ids.chatIdentityDialog.close(); await r.flush(); await r.poll();
    assert.equal(r.ids.chatIdentityDialog.open, false);
    r.ids.chatIdentityOpen.handlers.click(); await r.flush();
    assert.equal(r.ids.chatIdentityDialog.open, true);
    assert.equal(r.ids.chatIdentityDialog.opens, 2);
});

test('accepting referral consent opens identity popup without navigating away from chat', async () => {
    const r = room(); await r.flush();
    r.state({ ...accepted(), referral: { ...accepted().referral, status: 'pending_consent', help_seeker_consent: false, can_provide_identity: false, needs_identity_disclosure: false }, consent: null });
    await r.poll(); assert.equal(r.ids.referralConsentDialog.open, true);
    r.ids.referralConsentCheck.checked = true;
    r.state(accepted()); await r.ids.referralAccept.handlers.click(); await r.flush();
    assert.equal(r.requests.filter(request => request.options.method === 'POST').length, 1);
    assert.equal(r.ids.chatIdentityDialog.open, true);
    assert.equal(r.location.href, undefined);
});

test('approval and consent gates keep the identity popup hidden, including on the Helper side', async () => {
    const r = room(); await r.flush();
    for (const status of ['pending_adviser', 'pending_consent', 'declined', 'closed']) {
        r.ids.referralConsentDialog.close();
        r.state({ ...accepted(), referral: { ...accepted().referral, status, help_seeker_consent: false, can_provide_identity: false, needs_identity_disclosure: false, identity_url: null } });
        await r.poll(); assert.equal(r.ids.chatIdentityDialog.open, false);
        assert.equal(r.ids.chatIdentityOpen.hidden, true);
    }
    const helper = room(false); await helper.flush(); helper.state(accepted()); await helper.poll();
    assert.equal(helper.ids.chatIdentityDialog.open, false);
    assert.equal(helper.ids.chatIdentityOpen.hidden, true);
});

test('another open dialog defers the identity popup without losing the trigger', async () => {
    const r = room(); await r.flush(); r.block(true); r.state(accepted()); await r.poll();
    assert.equal(r.ids.chatIdentityDialog.open, false);
    r.block(false); await r.poll(); assert.equal(r.ids.chatIdentityDialog.open, true);
});

test('saved details stop prompting and another referral gets its own identity popup', async () => {
    const r = room(); await r.flush(); r.state(accepted()); await r.poll();
    r.state({ ...accepted(), referral: { ...accepted().referral, has_identity: true, needs_identity_disclosure: false } });
    await r.windowEvents.message({ origin: r.location.origin, source: r.frame.contentWindow, data: { type: 'identity-details-saved' } });
    await r.flush(); assert.equal(r.ids.chatIdentityOpen.hidden, true);
    r.ids.chatIdentityDialog.close(); await r.flush(); await r.poll();
    assert.equal(r.ids.chatIdentityDialog.open, false);
    r.state(accepted(9)); await r.poll();
    assert.equal(r.ids.chatIdentityDialog.open, true);
    assert.equal(r.frame.src, '/referrals/9/identity?embedded=1');
});

test('withdrawal closes the identity popup; cross-origin messages cannot close it', async () => {
    const r = room(); await r.flush(); r.state(accepted()); await r.poll();
    r.windowEvents.message({ origin: 'https://other.test', source: r.frame.contentWindow, data: { type: 'identity-modal-closed' } });
    assert.equal(r.ids.chatIdentityDialog.open, true);
    r.windowEvents.message({ origin: r.location.origin, source: {}, data: { type: 'identity-modal-closed' } });
    assert.equal(r.ids.chatIdentityDialog.open, true);
    r.state({ ...accepted(), referral: { ...accepted().referral, status: 'closed', help_seeker_consent: false, can_provide_identity: false, needs_identity_disclosure: false, identity_url: null }, consent: { decision: 'withdrawn' } });
    await r.poll(); assert.equal(r.ids.chatIdentityDialog.open, false);
    assert.equal(r.frame.src, 'about:blank');
});

test('offline and hidden tabs pause polling and resume when visible and online', async () => {
    const r = room(); await r.flush(); const count = r.requests.length;
    r.document.hidden = true; await r.poll(); assert.equal(r.requests.length, count);
    r.document.hidden = false; r.navigator.onLine = false; await r.poll(); assert.equal(r.requests.length, count);
    r.navigator.onLine = true; r.state(accepted()); await r.windowEvents.online();
    assert.equal(r.ids.chatIdentityDialog.open, true);
});

test('a failed referral decision keeps consent open and does not show identity', async () => {
    const r = room(); await r.flush();
    r.state({ ...accepted(), referral: { ...accepted().referral, status: 'pending_consent', help_seeker_consent: false, can_provide_identity: false, needs_identity_disclosure: false } });
    await r.poll(); r.ids.referralConsentCheck.checked = true; r.postOk(false);
    await r.ids.referralAccept.handlers.click(); await r.flush();
    assert.equal(r.ids.referralConsentDialog.open, true);
    assert.equal(r.ids.chatIdentityDialog.open, false);
    assert.match(r.ids.referralConsentError.textContent, /could not be saved/);
    assert.equal(r.ids.referralAccept.disabled, false);
});
