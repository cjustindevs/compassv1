(() => {
    const configNode = document.getElementById('moderatorEmergencyConfig');
    if (!configNode) return;
    const config = JSON.parse(configNode.content);
    let signature = config.signature, busy = false;
    async function poll() {
        if (busy || document.hidden || !navigator.onLine) return;
        // Preserve an unrelated form or confirmation dialog.
        if ([...document.querySelectorAll('dialog[open]')].some(node => node.id !== 'staffEmergencyNotice')) return;
        busy = true;
        try {
            const options = {headers: {Accept: 'application/json'}, cache: 'no-store'};
            const stats = await fetch(config.stats, options);
            if (!stats.ok) return;
            const data = await stats.json();
            if (config.kind === 'dashboard') {
                const values = {statSessions: data.active_sessions, statQueue: data.queue_waiting, statHelpers: data.helpers_available,
                    statEmergency: data.emergency_open, liveCount: data.active_sessions, queueCount: data.queue_waiting, emergencyCount: data.emergency_open};
                for (const [id, value] of Object.entries(values)) {
                    const node = document.getElementById(id);
                    if (node && value !== undefined) node.textContent = value;
                }
                for (const [id, value] of Object.entries({queueBadge: data.queue_waiting, sessionBadge: data.active_sessions, emergencyBadge: data.emergency_open})) {
                    const badge = document.getElementById(id);
                    if (badge && value !== undefined) { badge.textContent = value; badge.hidden = !value; badge.style.display = value ? 'inline-flex' : 'none'; }
                }
                document.querySelectorAll('[data-active-emergency-count]').forEach(node => { node.textContent = data.emergency_open; });
                const overview = document.getElementById('dashboard-analytics');
                if (overview && data.overview_signature !== signature && typeof data.overview_html === 'string' && !document.activeElement?.closest('#dashboard-analytics')) {
                    overview.innerHTML = data.overview_html;
                    signature = data.overview_signature;
                }
                return;
            }
            if (data.signature === signature) return;
            const response = await fetch(location.href, options);
            if (!response.ok) return;
            const updated = await response.json();
            // Render only the server's authorized operational fragment, without a page reload.
            if (document.activeElement?.closest('#moderatorEmergencyContent')) return;
            const content = document.getElementById('moderatorEmergencyContent');
            if (!content || typeof updated.html !== 'string') return;
            content.outerHTML = updated.html;
            signature = updated.signature;
        } catch (_) { /* Manual Refresh remains available during an outage. */ }
        finally { busy = false; }
    }
    poll(); setInterval(poll, 2000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    window.addEventListener('online', poll);
})();
