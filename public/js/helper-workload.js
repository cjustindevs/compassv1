(() => {
    const root = document.querySelector('[data-helper-workload]');
    if (!root) return;
    let busy = false;
    async function refresh() {
        if (busy || document.hidden || !navigator.onLine) return;
        busy = true;
        try {
            const response = await fetch(root.dataset.helperWorkload, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok) return;
            const data = await response.json();
            for (const helper of data.helpers) {
                const row = root.querySelector(`[data-helper-id="${Number(helper.id)}"]`);
                if (!row) continue;
                row.querySelector('[data-workload-status]').textContent = helper.status;
                row.querySelector('[data-workload-assignments]').textContent = `${helper.assignments} / 1`;
                row.querySelector('[data-workload-remaining]').textContent = helper.remaining;
            }
        } catch { /* Retain the last known workload during a temporary outage. */ }
        finally { busy = false; }
    }
    refresh(); setInterval(refresh, 15000);
    window.addEventListener('online', refresh);
    document.addEventListener('visibilitychange', refresh);
})();
