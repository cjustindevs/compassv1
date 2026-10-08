(() => {
    const panel = document.querySelector('[data-helper-sidebar-stats]');
    if (!panel || window.compassHelperStatsStarted) return;
    window.compassHelperStatsStarted = true;
    let busy = false;
    let stopped = false;
    async function refresh() {
        if (busy || stopped || document.hidden || !navigator.onLine) return;
        busy = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const response = await fetch(panel.dataset.helperSidebarStats, {
                credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                headers: {Accept: 'application/json'}
            });
            if ([401, 403, 419].includes(response.status)) stopped = true;
            if (!response.ok) return;
            const {sidebar} = await response.json();
            if (!sidebar) return;
            panel.querySelector('#sessionCount').textContent = sidebar.totalSessions;
            panel.querySelector('#compScore').textContent = sidebar.competencyScore === null ? 'No data' : sidebar.competencyScore + '%';
            const status = panel.querySelector('#availStatus');
            status.textContent = sidebar.availabilityLabel;
            status.style.color = sidebar.availabilityStatus === 'available' ? 'var(--green-500)' : 'var(--yellow-500)';
        } catch (_) {
            // Preserve the last known values during a temporary connection failure.
        } finally { clearTimeout(timeout); busy = false; }
    }
    setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', refresh);
    window.addEventListener('focus', refresh);
    window.addEventListener('online', refresh);
    refresh();
})();
