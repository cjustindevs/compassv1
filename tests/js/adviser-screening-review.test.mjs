import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

const script = readFileSync(new URL('../../resources/views/adviser/screenings.blade.php', import.meta.url), 'utf8')
    .match(/<script>([\s\S]*?)<\/script>/)[1];

function reviewForm(confirmed, storedFollowUp = false) {
    const callbacks = new Map();
    const check = { checked: true, addEventListener() {} };
    const answer = { value: 'no', selectedOptions: [{ dataset: { risk: 'low' } }], addEventListener() {} };
    const risk = { value: '' };
    let submitted = null;
    const form = { dataset: {},
        querySelector(selector) {
            if (storedFollowUp) return null;
            return selector.includes('use_clarified_safety_answer') ? check : selector.includes('clarified_safety_answer') ? answer : risk;
        },
        addEventListener(event, callback) { callbacks.set(event, callback); },
        requestSubmit(button) { submitted = button; },
    };
    let initialize;
    runInNewContext(script, {
        document: { addEventListener(event, callback) { initialize = callback; },
            querySelectorAll(selector) { return selector === '[data-screening-review-form]' ? [form] : []; },
            getElementById() { return null; },
        },
        window: { confirmAction: async () => confirmed }, location: { hash: '' }, setTimeout,
    });
    initialize();
    return { form, answer, risk, get submitted() { return submitted; },
        async submit(action) {
            const button = { name: 'review_action', value: action };
            await callbacks.get('submit')({ submitter: button, preventDefault() {} });
            return button;
        },
    };
}

test('approval confirmation preserves its outcome without manipulating saved screening fields', async () => {
    const review = reviewForm(true);
    assert.equal(review.risk.value, '');
    assert.equal(review.answer.required, undefined);
    assert.equal(review.answer.disabled, undefined);
    const button = await review.submit('approve');
    assert.equal(review.submitted, button);
    assert.equal(review.submitted.value, 'approve');
});

test('saved follow-up approval needs no editable risk or replacement-answer fields', async () => {
    const review = reviewForm(true, true);
    const button = await review.submit('approve');
    assert.equal(review.submitted, button);
    assert.equal(review.submitted.value, 'approve');
});

test('cancelled confirmation submits nothing; record-without-approval preserves its distinct outcome', async () => {
    const cancelled = reviewForm(false);
    await cancelled.submit('approve');
    assert.equal(cancelled.submitted, null);
    const recorded = reviewForm(true);
    await recorded.submit('record');
    assert.equal(recorded.submitted.value, 'record');
});
