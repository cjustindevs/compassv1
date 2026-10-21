const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function page(initialOpen = [], hash = '', loading = false) {
    class Element {
        constructor() {
            this.hidden = false;
            this.dataset = {};
            this.attributes = {};
            this.events = {};
            this.focusCount = 0;
            this.classes = new Set();
            this.classList = {toggle: (name, active) => active ? this.classes.add(name) : this.classes.delete(name)};
        }
        getAttribute(name) { return this.attributes[name]; }
        setAttribute(name, value) { this.attributes[name] = value; }
        addEventListener(name, listener) { this.events[name] = listener; }
        click() { this.events.click?.(); }
        focus() { this.focusCount++; }
    }
    const rows = [1, 2, 3].map(id => {
        const card = new Element(), panel = new Element(), button = new Element(), close = new Element(), label = {};
        card.id = 'screening-' + id;
        panel.id = 'screening-workspace-' + id;
        panel.dataset.initialOpen = String(initialOpen.includes(id));
        panel.querySelectorAll = () => [close];
        button.attributes['aria-controls'] = panel.id;
        button.hidden = true;
        button.closest = () => card;
        button.querySelector = () => label;
        return {card, panel, button, close, label};
    });
    const documentEvents = {}, windowEvents = {}, location = {hash};
    let filterResets = 0, requests = 0;
    const all = {click: () => { filterResets++; rows.forEach(row => { row.card.hidden = false; }); }};
    const document = {
        readyState: loading ? 'loading' : 'complete',
        querySelectorAll: () => rows.map(row => row.button),
        querySelector: () => all,
        getElementById: id => rows.find(row => row.panel.id === id)?.panel,
        addEventListener: (event, listener) => { documentEvents[event] = listener; },
    };
    vm.runInNewContext(fs.readFileSync('public/js/screening-review-workspace.js', 'utf8'), {
        document,
        window: {location, addEventListener: (event, listener) => { windowEvents[event] = listener; }},
        fetch: () => { requests++; throw new Error('Opening a review must not submit a decision.'); },
    });
    return {rows, location, documentEvents, windowEvents, requests: () => requests, filterResets: () => filterResets};
}

test('open and close have explicit labels and expanded state, without saving a decision', () => {
    const p = page(), row = p.rows[0];
    assert.equal(row.panel.hidden, true);
    assert.equal(row.button.hidden, false);
    assert.equal(row.label.textContent, 'Open review');
    assert.equal(row.button.getAttribute('aria-expanded'), 'false');
    row.button.click();
    assert.equal(row.panel.hidden, false);
    assert.equal(row.label.textContent, 'Close review');
    assert.equal(row.button.getAttribute('aria-expanded'), 'true');
    assert.equal(row.card.classes.has('screening-review-open'), true);
    assert.equal(row.panel.focusCount, 1);
    assert.equal(p.rows[1].panel.hidden, true);
    row.close.click();
    assert.equal(row.panel.hidden, true);
    assert.equal(row.label.textContent, 'Open review');
    assert.equal(row.card.classes.has('screening-review-open'), false);
    assert.equal(row.button.focusCount, 1);
    assert.equal(p.requests(), 0);
});

test('a validation retry and a notification target reveal their own case', () => {
    const p = page([2], '#screening-3');
    assert.equal(p.rows[0].panel.hidden, true);
    assert.equal(p.rows[1].panel.hidden, false);
    assert.equal(p.rows[2].panel.hidden, false);
    assert.equal(p.rows[1].button.getAttribute('aria-expanded'), 'true');
    assert.equal(p.requests(), 0);
});

test('a notification hash reveals a previously filtered case and focuses its workspace', () => {
    const p = page();
    p.rows[1].card.hidden = true;
    p.location.hash = '#screening-2';
    p.windowEvents.hashchange();
    assert.equal(p.filterResets(), 1);
    assert.equal(p.rows[1].card.hidden, false);
    assert.equal(p.rows[1].panel.hidden, false);
    assert.equal(p.rows[1].panel.focusCount, 1);
    p.location.hash = '#unrelated';
    p.windowEvents.hashchange();
    assert.equal(p.filterResets(), 1);
});

test('controls initialize after the DOM is ready', () => {
    const p = page([], '', true);
    assert.equal(p.rows[0].button.hidden, true);
    assert.equal(p.rows[0].panel.hidden, false);
    p.documentEvents.DOMContentLoaded();
    assert.equal(p.rows[0].button.hidden, false);
    assert.equal(p.rows[0].panel.hidden, true);
});
