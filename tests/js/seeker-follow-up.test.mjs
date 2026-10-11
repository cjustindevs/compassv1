import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

const script = readFileSync(new URL('../../resources/views/request/status.blade.php', import.meta.url), 'utf8')
    .match(/<script>([\s\S]*?)<\/script>/)[1];

function waitingPage({ selected = false, focused = false, hidden = false, dialog = false, hasForm = true } = {}) {
    let tick;
    let reloads = 0;
    const form = { contains: () => focused, querySelector: () => selected ? {} : null };
    runInNewContext(script, {
        document: { visibilityState: hidden ? 'hidden' : 'visible', activeElement: {},
            querySelector: selector => selector === '[data-followup-form]' ? (hasForm ? form : null) : (dialog ? {} : null),
        },
        location: { reload: () => reloads++ },
        setInterval: callback => { tick = callback; },
    });
    return { tick, get reloads() { return reloads; } };
}

test('waiting-page refresh preserves a selected or focused follow-up response', () => {
    for (const state of [{ selected: true }, { focused: true }, { hidden: true }, { dialog: true }]) {
        const page = waitingPage(state);
        page.tick();
        assert.equal(page.reloads, 0);
    }
});

test('untouched waiting pages continue checking for a follow-up or decision', () => {
    for (const hasForm of [true, false]) {
        const page = waitingPage({ hasForm });
        page.tick();
        assert.equal(page.reloads, 1);
    }
});
