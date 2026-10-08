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
            const {sidebar, ready, status: readinessStatus, valid_until} = await response.json();
            if (!sidebar) return;
            panel.querySelector('#sessionCount').textContent = sidebar.totalSessions;
            panel.querySelector('#compScore').textContent = sidebar.competencyScore === null ? 'No data' : sidebar.competencyScore + '%';
            const status = panel.querySelector('#availStatus');
            status.textContent = sidebar.availabilityLabel;
            status.style.color = sidebar.availabilityStatus === 'available' ? 'var(--green-500)' : 'var(--yellow-500)';
            document.querySelectorAll('[data-dashboard-availability]').forEach(el=>{el.textContent=sidebar.availabilityLabel;el.style.color=sidebar.availabilityStatus==='available' ? 'var(--green-500)' : 'var(--yellow-500)';});
            document.querySelectorAll('[data-dashboard-availability-reason]').forEach(el=>el.textContent=sidebar.availabilityReason);
            document.querySelectorAll('[data-dashboard-readiness]').forEach(el=>{el.textContent=ready ? 'Ready' : (readinessStatus==='not_ready' ? 'Not ready' : 'Readiness required');el.classList.toggle('hf-status-warning',!ready);});
            document.querySelectorAll('[data-dashboard-readiness-until]').forEach(el=>el.textContent=valid_until && ready ? 'Valid until '+new Intl.DateTimeFormat('en-PH',{timeZone:'Asia/Manila',dateStyle:'medium',timeStyle:'short'}).format(new Date(valid_until))+' PHT' : 'Complete a current readiness check before accepting cases.');
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
