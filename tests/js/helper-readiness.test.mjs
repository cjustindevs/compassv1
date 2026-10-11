import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

const script = readFileSync(new URL('../../resources/views/helper/readiness.blade.php', import.meta.url), 'utf8')
    .match(/<script>([\s\S]*?)<\/script>/)[1];

function readinessPage({ ready = false, valid = true, exercise = 'skipped' } = {}) {
    let initialize;
    const elements = new Map();
    for (const id of ['exercise-modal','openExerciseBtn','closeExerciseBtn','skipExerciseBtn','begin-lesson-btn',
        'continue-btn','breath-circle','breath-text','round-count','exercise_completed','readinessForm','breathStatusTag']) {
        elements.set(id, { handlers: {}, style: {}, value: id === 'exercise_completed' ? exercise : '',
            classList: { active: false, add(name) { if (name === 'active') this.active = true; }, remove(name) { if (name === 'active') this.active = false; } },
            addEventListener(event, handler) { this.handlers[event] = handler; },
            reportValidity: () => valid,
        });
    }
    if (ready) { elements.delete('openExerciseBtn'); elements.delete('readinessForm'); elements.delete('exercise_completed'); }
    runInNewContext(script, {
        document: { addEventListener(event, handler) { initialize = handler; }, getElementById: id => elements.get(id) ?? null },
        window: {}, clearTimeout() {}, setTimeout() {},
    });
    initialize();
    return { elements, submit() {
        let prevented = false;
        elements.get('readinessForm').handlers.submit({ preventDefault() { prevented = true; } });
        return prevented;
    } };
}

test('valid negative answers submit without a five-checkbox gate or confirmation dialog', () => {
    const page = readinessPage();
    assert.equal(page.submit(), false);
    assert.equal(page.elements.get('exercise-modal').classList.active, false);
});

test('missing answers remain invalid and an undecided exercise opens the existing complete-or-skip modal', () => {
    assert.equal(readinessPage({ valid: false }).submit(), true);
    const page = readinessPage({ exercise: '' });
    assert.equal(page.submit(), true);
    assert.equal(page.elements.get('exercise-modal').classList.active, true);
    page.elements.get('skipExerciseBtn').handlers.click();
    assert.equal(page.elements.get('exercise_completed').value, 'skipped');
    assert.equal(page.submit(), false);
});

test('already-ready screen initializes without binding missing form or exercise controls', () => {
    assert.doesNotThrow(() => readinessPage({ ready: true }));
});
