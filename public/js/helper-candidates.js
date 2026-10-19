(() => {
    const forms = [...document.querySelectorAll('form[data-helper-options]')];
    for (const form of forms) {
        let busy = false, choices = [];
        const select = form.querySelector('[name=helper_id]'), search = form.querySelector('[data-helper-search]');
        function render() {
            const selected = select.value, term = (search?.value || '').toLowerCase();
            const rows = choices.filter(row => row.label.toLowerCase().includes(term));
            select.replaceChildren(new Option(rows.length ? 'Select a Helper' : 'No eligible Helper', ''));
            rows.forEach(row => select.append(new Option(row.label, String(row.id))));
            select.value = rows.some(row => String(row.id) === selected) ? selected : '';
            const submit = form.querySelector('[type=submit]');
            if (!form.dataset.submitting) submit.disabled = !rows.length;
        }
        async function refresh() {
            if (busy || document.hidden || !navigator.onLine || form.dataset.submitting) return;
            busy = true;
            try {
                const response = await fetch(form.dataset.helperOptions, {headers:{Accept:'application/json'}, cache:'no-store'});
                if (!response.ok) return;
                const data = await response.json(); choices = data.helpers;
                if (document.activeElement !== select) render();
                form.querySelector('[data-helper-reason]').textContent = data.reason;
                const list = form.querySelector('[data-unavailable-list]'); list.replaceChildren();
                for (const row of data.unavailable || []) {
                    const item = document.createElement('li');
                    item.textContent = `${row.name} — ${row.reason}${row.readiness_passed ? ' Readiness passed; another requirement blocks assignment.' : ''}`;
                    list.append(item);
                }
            } catch {} finally { busy = false; }
        }
        search?.addEventListener('input', render);
        select.addEventListener('blur', render);
        refresh(); setInterval(refresh, 5000);
        document.addEventListener('visibilitychange', refresh); window.addEventListener('online', refresh);
    }
})();
