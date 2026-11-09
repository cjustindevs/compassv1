import '../css/dashboard-analytics.css';
import { chartConfig } from './compass-chart-options.mjs';

// Renders every [data-analytics-chart] card. The exact-values table is server-rendered,
// so the data stays readable if Chart.js cannot load.
const instances = new Map();
let ChartLib = null;
let loading = null;

function theme() {
    const root = document.documentElement;
    const dark = root.dataset.adminTheme === 'dark' || root.classList.contains('dark');
    const css = getComputedStyle(root);
    const pick = (...names) => names.map((n) => css.getPropertyValue(n).trim()).find(Boolean) || undefined;
    return {
        dark,
        ink: pick('--admin-text', '--compass-text'),
        muted: pick('--admin-muted', '--compass-muted'),
        grid: dark ? pick('--admin-border') : undefined,
        surface: pick('--admin-card'),
    };
}

function reducedMotion() {
    return matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('admin-reduced-motion');
}

function fail(card) {
    const state = card.querySelector('[data-chart-state]');
    const frame = card.querySelector('[data-chart-frame]');
    if (frame) frame.hidden = true;
    if (state) { state.hidden = false; state.textContent = 'Chart unavailable. Open "View exact values" below.'; }
}

function draw(card, palette) {
    const source = card.querySelector('[data-chart-data]');
    const frame = card.querySelector('[data-chart-frame]');
    if (!source || !frame) return;
    try {
        instances.get(card)?.destroy();
        frame.hidden = false;
        const chart = new ChartLib(frame.querySelector('canvas'), chartConfig(JSON.parse(source.textContent), palette, reducedMotion()));
        instances.set(card, chart);
        const state = card.querySelector('[data-chart-state]');
        if (state) state.hidden = true;
    } catch {
        fail(card);
    }
}

async function init(root = document) {
    const cards = [...root.querySelectorAll('[data-analytics-chart]')].filter((card) => card.querySelector('[data-chart-data]') && !instances.has(card));
    if (!cards.length) return;
    try {
        loading ||= import('chart.js/auto').then((m) => { ChartLib = m.default; });
        await loading;
    } catch {
        cards.forEach(fail);
        return;
    }
    const palette = theme();
    cards.forEach((card) => draw(card, palette));
}

function prune() {
    for (const [card, chart] of instances) {
        if (!card.isConnected) { chart.destroy(); instances.delete(card); }
    }
}

function redrawAll() {
    if (!ChartLib) return;
    prune();
    const palette = theme();
    [...instances.keys()].forEach((card) => draw(card, palette));
}

if (typeof document !== 'undefined') {
    window.CompassCharts = { init, redraw: redrawAll };
    const start = () => {
        init();
        // Theme / accent / motion toggles repaint; injected fragments (AJAX refresh) initialize.
        new MutationObserver(redrawAll).observe(document.documentElement, { attributes: true, attributeFilter: ['data-admin-theme', 'data-admin-accent', 'class'] });
        let queued = false;
        new MutationObserver(() => {
            if (queued) return;
            queued = true;
            requestAnimationFrame(() => { queued = false; prune(); init(); });
        }).observe(document.body, { childList: true, subtree: true });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
}
