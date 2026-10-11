import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import test from 'node:test';

const source = readFileSync(new URL('../../resources/js/adviser-notifications.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/, '');

function workspace(storage = new Map()) {
    const nodes = new Map(), listeners = new Map(), intervals = [];
    let feed = { count: 1, screening_count: 1, review_cursor: 1, reviews: [{ id: 1, title: 'Screening review required', message: '<script>unsafe</script>', link: '/adviser/screenings#screening-49' }] };
    let modal = false, appended = 0;
    function element() {
        const item = { style: {}, attributes: {}, textContent: '', html: '',
            setAttribute(key, value) { this.attributes[key] = value; },
            addEventListener() {},
            querySelector() { return { addEventListener() {} }; },
            appendChild(child) { nodes.set(child.id, child); },
            remove() { nodes.delete(this.id); },
        };
        Object.defineProperty(item, 'innerHTML', {
            get() { return this.html || String(this.textContent).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;'); },
            set(value) { this.html = value; },
        });
        return item;
    }
    const document = { hidden: false, documentElement: {},
        querySelector(selector) {
            if (selector === '[data-adviser-user-id]') return { dataset: { adviserUserId: '42' } };
            if (selector === 'dialog[open]') return modal ? {} : null;
            if (selector.startsWith('[data-adviser-badge=')) return element();
            return null;
        },
        getElementById(id) { return nodes.get(id); }, createElement: element,
        addEventListener(name, callback) { listeners.set(name, callback); },
        body: { appendChild(item) { appended++; nodes.set(item.id, item); } },
    };
    runInNewContext(source, { document, window: {}, navigator: { onLine: true },
        sessionStorage: { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value) },
        fetch: async () => ({ ok: true, json: async () => feed }),
        getComputedStyle: () => ({ getPropertyValue: () => '' }), uiIcon: () => '',
        setInterval(callback) { intervals.push(callback); },
    });
    return { nodes, document, storage, get appended() { return appended; },
        set feed(value) { feed = value; }, set modal(value) { modal = value; },
        async start() { listeners.get('DOMContentLoaded')(); await this.poll(); },
        async poll() { await intervals[0]?.(); await Promise.resolve(); await Promise.resolve(); },
    };
}

test('review popup works without Echo, escapes content, creates badges, and does not repeat across navigation', async () => {
    const page = workspace(); await page.start();
    assert.equal(page.appended, 1);
    assert.match(page.nodes.get('adviserToast').innerHTML, /&lt;script&gt;unsafe&lt;\/script&gt;/);
    assert.match(page.nodes.get('adviserToast').innerHTML, /Review screening/);
    assert.equal(page.nodes.get('pendingReviewsBadge').textContent, 1);
    await page.poll(); assert.equal(page.appended, 1);
    const nextPage = workspace(page.storage); await nextPage.start(); assert.equal(nextPage.appended, 0);
    page.feed = { count: 2, screening_count: 2, review_cursor: 2, reviews: [{ id: 2, title: 'New review', message: 'Review required', link: '/adviser/screenings#screening-50' }] };
    await page.poll(); assert.equal(page.appended, 2);
});

test('review popup defers to open dialogs and pauses on hidden pages without losing alerts', async () => {
    const page = workspace(); page.modal = true; await page.start(); assert.equal(page.appended, 0);
    page.modal = false; page.document.hidden = true; await page.poll(); assert.equal(page.appended, 0);
    page.document.hidden = false; await page.poll(); assert.equal(page.appended, 1);
    page.feed = { count: 0, screening_count: 0, review_cursor: 1, reviews: [] };
    await page.poll(); assert.equal(page.nodes.get('notifBadge').style.display, 'none');
    assert.equal(page.nodes.get('pendingReviewsBadge').style.display, 'none');
});
