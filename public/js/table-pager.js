// Client-side search, filters, date range and rows-per-page for a server-rendered table.
// Markup: a [data-table-pager] wrapper holding
//   input[data-pager-search], select[data-pager-filter="<key>"], input[data-pager-from|data-pager-to] (YYYY-MM-DD),
//   select[data-pager-size], button[data-pager-prev|data-pager-next], [data-pager-status], [data-pager-empty],
//   and rows tr[data-pager-row] with data-search, data-<key> and data-date attributes.
(function () {
    const SIZES = [10, 15, 25, 50];

    // rows: [{search, attrs: {key: value}, date}] -> which row indexes to show on the requested page.
    function selectRows(rows, opts) {
        opts = opts || {};
        const q = String(opts.q || '').trim().toLowerCase();
        const filters = opts.filters || {};
        const matched = [];
        rows.forEach((row, index) => {
            if (q && !String(row.search || '').includes(q)) return;
            for (const key of Object.keys(filters)) {
                const want = filters[key];
                if (want && want !== 'all' && (row.attrs || {})[key] !== want) return;
            }
            if (opts.from && (!row.date || row.date < opts.from)) return;
            if (opts.to && (!row.date || row.date > opts.to)) return;
            matched.push(index);
        });
        const perPage = SIZES.includes(Number(opts.perPage)) ? Number(opts.perPage) : SIZES[0];
        const pages = Math.max(1, Math.ceil(matched.length / perPage));
        const page = Math.min(Math.max(1, Number(opts.page) || 1), pages);
        return { matched: matched.length, page, pages, perPage, visible: matched.slice((page - 1) * perPage, page * perPage) };
    }

    function bind(root) {
        const trs = Array.from(root.querySelectorAll('tr[data-pager-row]'));
        const filterEls = Array.from(root.querySelectorAll('[data-pager-filter]'));
        const rows = trs.map((tr) => ({
            search: (tr.dataset.search || '').toLowerCase(),
            attrs: Object.fromEntries(filterEls.map((el) => [el.dataset.pagerFilter, tr.getAttribute('data-' + el.dataset.pagerFilter) || ''])),
            date: tr.dataset.date || '',
        }));
        const $ = (sel) => root.querySelector(sel);
        let page = 1;

        function render() {
            const result = selectRows(rows, {
                q: $('[data-pager-search]')?.value,
                filters: Object.fromEntries(filterEls.map((el) => [el.dataset.pagerFilter, el.value])),
                from: $('[data-pager-from]')?.value, to: $('[data-pager-to]')?.value,
                perPage: $('[data-pager-size]')?.value, page,
            });
            page = result.page;
            const shown = new Set(result.visible);
            trs.forEach((tr, index) => { tr.hidden = !shown.has(index); });
            const empty = $('[data-pager-empty]');
            if (empty) empty.hidden = result.matched !== 0;
            const status = $('[data-pager-status]');
            if (status) {
                const first = result.matched ? (result.page - 1) * result.perPage + 1 : 0;
                status.textContent = `${first}–${first ? first + result.visible.length - 1 : 0} of ${result.matched} · Page ${result.page} of ${result.pages}`;
            }
            const prev = $('[data-pager-prev]'); if (prev) prev.disabled = result.page <= 1;
            const next = $('[data-pager-next]'); if (next) next.disabled = result.page >= result.pages;
        }

        root.addEventListener('input', (event) => { if (event.target.matches('[data-pager-search],[data-pager-from],[data-pager-to]')) { page = 1; render(); } });
        root.addEventListener('change', (event) => { if (event.target.matches('[data-pager-filter],[data-pager-size],[data-pager-from],[data-pager-to]')) { page = 1; render(); } });
        root.addEventListener('click', (event) => {
            if (event.target.closest('[data-pager-prev]')) { page -= 1; render(); }
            if (event.target.closest('[data-pager-next]')) { page += 1; render(); }
            if (event.target.closest('[data-pager-reset]')) {
                root.querySelectorAll('[data-pager-search],[data-pager-from],[data-pager-to]').forEach((el) => { el.value = ''; });
                filterEls.forEach((el) => { el.value = 'all'; });
                page = 1; render();
            }
        });
        render();
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { selectRows };
    if (typeof document !== 'undefined') {
        const start = () => document.querySelectorAll('[data-table-pager]').forEach(bind);
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
    }
})();
