// Phone-friendly tables: copies each column header onto its cells (data-label) so the CSS in
// compass-components.css can show every row as a small card on narrow screens instead of squeezing
// columns until words break into single letters. Admin pages, charts' data tables and tables that
// already stack (x-ui.table :stack) are left alone; add data-no-stack to opt a table out.
(function () {
    const SKIP = '.ui-table--stack, [data-admin-app], [data-analytics-chart], .co-data, .analytics-chart-card, [data-no-stack]';

    // headers: column titles (after colspans are expanded); spans: colSpan of each cell in one row.
    // Returns one entry per cell: {label} | {full: true} for a row-wide cell | {actions: true} for an untitled column.
    function planLabels(headers, spans) {
        let col = 0;
        return spans.map((span) => {
            const entry = span >= headers.length ? { full: true } : (headers[col] ? { label: headers[col] } : { actions: true });
            col += span;
            return entry;
        });
    }

    function headerTexts(table) {
        const row = table.tHead && table.tHead.rows[table.tHead.rows.length - 1];
        if (!row) return null;
        const labels = [];
        for (const th of row.cells) {
            const text = th.textContent.replace(/\s+/g, ' ').trim();
            for (let i = 0; i < (th.colSpan || 1); i++) labels.push(text);
        }
        return labels;
    }

    function prepare(table) {
        if (table.closest(SKIP) || document.documentElement.classList.contains('compass-ui-admin')) return;
        const headers = headerTexts(table);
        if (!headers || headers.length < 2) return;
        table.classList.add('compass-stack-table');
        for (const tbody of table.tBodies) {
            for (const tr of tbody.rows) {
                const cells = [...tr.cells];
                planLabels(headers, cells.map((td) => td.colSpan || 1)).forEach((entry, i) => {
                    const td = cells[i];
                    if (entry.full) td.classList.add('compass-stack-full');
                    else if (entry.actions) td.classList.add('compass-stack-actions');
                    else if (!td.hasAttribute('data-label')) td.setAttribute('data-label', entry.label);
                });
            }
        }
    }

    // On phones a cell whose content does not fit half a card (an avatar with a name, a long phrase)
    // gets the full card width instead of running into its neighbour.
    function widenCrowdedCells() {
        if (!window.matchMedia || !window.matchMedia('(max-width: 640px)').matches) return;
        document.querySelectorAll('table.compass-stack-table td[data-label]:not(.compass-stack-wide)').forEach((td) => {
            if (td.scrollWidth > td.clientWidth + 1) td.classList.add('compass-stack-wide');
        });
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { planLabels };
    if (typeof document === 'undefined') return;
    const run = () => { document.querySelectorAll('table').forEach(prepare); requestAnimationFrame(widenCrowdedCells); };
    window.addEventListener('resize', () => requestAnimationFrame(widenCrowdedCells));
    const start = () => {
        run();
        // Rows added later (live lists, filters) get labels too.
        let queued = false;
        new MutationObserver(() => { if (!queued) { queued = true; setTimeout(() => { queued = false; run(); }, 120); } })
            .observe(document.body, { childList: true, subtree: true });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
