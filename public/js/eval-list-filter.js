(function (root) {
    'use strict';
    var RANK = { emergency: 0, high: 1, moderate: 2, low: 3 };

    // Returns the ordered indices of the rows to show. Pure, so it can be tested without a DOM.
    function filterRows(rows, opts) {
        opts = opts || {};
        var q = String(opts.q || '').trim().toLowerCase();
        var risk = opts.risk || 'all';
        var sort = opts.sort || 'original';
        var out = [];
        rows.forEach(function (row, i) {
            if (q && String(row.search || '').toLowerCase().indexOf(q) === -1) return;
            if (risk === 'high' && row.risk !== 'high' && row.risk !== 'emergency') return;
            if ((risk === 'moderate' || risk === 'low') && row.risk !== risk) return;
            out.push(i);
        });
        var rank = function (i) { var r = RANK[rows[i].risk]; return r === undefined ? 4 : r; };
        var time = function (i) { return Number(rows[i].time) || 0; };
        var alias = function (i) { return String(rows[i].alias || '').toLowerCase(); };
        if (sort === 'newest') out.sort(function (a, b) { return time(b) - time(a) || a - b; });
        else if (sort === 'oldest') out.sort(function (a, b) { return time(a) - time(b) || a - b; });
        else if (sort === 'risk') out.sort(function (a, b) { return rank(a) - rank(b) || time(b) - time(a) || a - b; });
        else if (sort === 'alias') out.sort(function (a, b) { return alias(a) < alias(b) ? -1 : alias(a) > alias(b) ? 1 : a - b; });
        return out;
    }

    function init(doc) {
        var search = doc.getElementById('evalSearch');
        var riskSel = doc.getElementById('evalRisk');
        var sortSel = doc.getElementById('evalSort');
        var list = doc.querySelector('.ui-eval-list');
        if (!search || !riskSel || !sortSel || !list) return;
        var nodes = Array.prototype.slice.call(list.querySelectorAll('[data-eval-row]'));
        var data = nodes.map(function (n) {
            return { risk: n.dataset.risk, time: n.dataset.time, alias: n.dataset.alias, search: n.dataset.search };
        });
        var count = doc.getElementById('evalShowing');
        var none = doc.getElementById('evalNoMatch');

        function apply() {
            var order = filterRows(data, { q: search.value, risk: riskSel.value, sort: sortSel.value });
            var shown = {};
            order.forEach(function (i) { shown[i] = true; list.appendChild(nodes[i]); });
            nodes.forEach(function (n, i) { n.hidden = !shown[i]; });
            if (count) count.textContent = 'Showing ' + order.length + ' of ' + nodes.length + ' on this page';
            if (none) none.hidden = order.length > 0;
        }
        search.addEventListener('input', apply);
        riskSel.addEventListener('change', apply);
        sortSel.addEventListener('change', apply);
        apply();
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { filterRows: filterRows };
    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); });
        else init(document);
    }
})(typeof window !== 'undefined' ? window : this);
