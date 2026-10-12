import '../css/dashboard-analytics.css';
import { chartOptions } from './dashboard-chart-options.mjs';

// Keep exact-value tables usable even when chart code cannot load.
async function initializeCharts() {
    const cards = [...document.querySelectorAll('[data-analytics-chart]')].filter(card => card.querySelector('[data-chart-data]'));
    if (!cards.length) return;
    try {
        const { default: Chart } = await import('chart.js/auto');
        const charts = [];
        const render = () => {
            charts.splice(0).forEach(chart => chart.destroy());
            cards.forEach(card => {
                const state = card.querySelector('[data-chart-state]');
                const frame = card.querySelector('[data-chart-frame]');
                try {
                    const style = getComputedStyle(document.documentElement);
                    const text = style.getPropertyValue('--admin-muted').trim() || '#64748b';
                    const grid = style.getPropertyValue('--admin-border').trim() || '#e5e7eb';
                    const colors = ['#04a052', '#3b82f6', '#8b5cf6'];
                    frame.hidden = false;
                    charts.push(new Chart(frame.querySelector('canvas'), chartOptions(JSON.parse(card.querySelector('[data-chart-data]').textContent), colors, text, grid, matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.classList.contains('admin-reduced-motion'))));
                    state.hidden = true;
                } catch {
                    frame.hidden = true;
                    state.hidden = false;
                    state.textContent = 'Chart unavailable. Open the exact-value table below.';
                }
            });
        };
        render();
        new MutationObserver(render).observe(document.documentElement, { attributes: true, attributeFilter: ['data-admin-theme', 'data-admin-accent', 'class'] });
    } catch {
        cards.forEach(card => { card.querySelector('[data-chart-state]').textContent = 'Chart unavailable. Open the exact-value table below.'; });
    }
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeCharts, { once: true });
    else initializeCharts();
}
