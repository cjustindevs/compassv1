/**
 * Real-time alerts for the Moderator workspace.
 *
 * Listens on the private `moderator.{userId}` channel for ModeratorAlert
 * events, shows a toast, and keeps the sidebar badges in sync.
 */
document.addEventListener('DOMContentLoaded', function () {
    const userId = document.querySelector('meta[name="user-id"]')?.content;

    if (!userId || !window.Echo) {
        return;
    }

    window.Echo.private(`moderator.${userId}`)
        .listen('ModeratorAlert', (event) => {
            showToast(event.title || 'New alert', event.message || '', event.link || null, 'Open');

            if (event.type === 'emergency') {
                incrementBadge('emergencyBadge');
                incrementBadge('notifBadge');
                const ec = document.getElementById('emergencyCount');
                if (ec) ec.textContent = (parseInt(ec.textContent) || 0) + 1;
            }

            if (event.type === 'queue' || event.type === 'assignment') {
                incrementBadge('queueBadge');
                const qc = document.getElementById('queueCount');
                if (qc) qc.textContent = (parseInt(qc.textContent) || 0) + 1;
            }

            if (event.type === 'session') {
                refreshModeratorStats();
            }
        })
        .listen('QueueUpdated', (event) => {
            const el = (id) => document.getElementById(id);
            if (el('statWaiting') && event.waiting !== undefined) el('statWaiting').textContent = event.waiting;
            if (el('statAssigned') && event.assigned !== undefined) el('statAssigned').textContent = event.assigned;
            if (el('statAvgWait') && event.avg_wait !== undefined) el('statAvgWait').textContent = event.avg_wait;
            if (el('statUnserved')) {
                el('statUnserved').textContent = event.unserved;
                el('statUnserved').className = (parseInt(event.unserved, 10) > 0) ? 'stat-number text-red-600' : 'stat-number text-gray-800';
            }
            if (el('queueBadge')) {
                el('queueBadge').textContent = event.waiting;
                el('queueBadge').style.display = event.waiting > 0 ? 'inline-flex' : 'none';
            }
            if (el('queueCount')) el('queueCount').textContent = event.waiting;

            // The queue changed, so refresh the dashboard live counts too.
            refreshModeratorStats();
        });

    // Fallback: if the websocket is down, poll the unread-count endpoint.
    const echoConnector = window.Echo.connector;
    setInterval(() => {
        const state = echoConnector?.pusher?.connection?.state;
        if (state && state !== 'connected') {
            refreshUnreadCount();
        }
    }, 30000);

    function incrementBadge(id) {
        const badge = document.getElementById(id);
        if (badge) {
            badge.textContent = (parseInt(badge.textContent) || 0) + 1;
            badge.style.display = 'inline-flex';
        }
    }

    function refreshUnreadCount() {
        fetch('/moderator/notifications/unread-count', {
            headers: { 'Accept': 'application/json' },
        })
            .then((response) => response.json())
            .then((data) => {
                const badge = document.getElementById('notifBadge');
                if (badge) {
                    badge.textContent = data.count || 0;
                    badge.style.display = data.count > 0 ? 'inline-flex' : 'none';
                }
            })
            .catch(() => {});
    }

    // Refresh the dashboard / sessions stat cards that are on the current page.
    function refreshModeratorStats() {
        fetch('/moderator/dashboard/stats', { headers: { 'Accept': 'application/json' } })
            .then((r) => r.json())
            .then((data) => {
                const set = (id, val) => {
                    const el = document.getElementById(id);
                    if (el && val !== undefined) el.textContent = val;
                };
                const toggle = (id, val) => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.textContent = val;
                        el.style.display = val > 0 ? 'inline-flex' : 'none';
                    }
                };
                set('statSessions', data.active_sessions);
                set('statQueue', data.queue_waiting);
                set('statEmergency', data.emergency_open);
                set('statHelpers', data.helpers_available);
                set('avgWait', data.avg_wait);
                set('liveCount', data.active_sessions);
                set('queueCount', data.queue_waiting);
                set('emergencyCount', data.emergency_open);
                toggle('queueBadge', data.queue_waiting);
                toggle('sessionBadge', data.active_sessions);
                toggle('emergencyBadge', data.emergency_open);
            })
            .catch(() => {});

        if (document.getElementById('statOngoing')) {
            fetch('/moderator/sessions/stats', { headers: { 'Accept': 'application/json' } })
                .then((r) => r.json())
                .then((data) => {
                    const set = (id, val) => {
                        const el = document.getElementById(id);
                        if (el && val !== undefined) el.textContent = val;
                    };
                    set('statOngoing', data.ongoing);
                    set('statChat', data.chat);
                    set('statVoice', data.voice);
                    set('statEmergency', data.emergency_flagged);
                    set('liveCount', data.ongoing);
                })
                .catch(() => {});
        }
    }

    function showToast(title, message, link, linkLabel) {
        const existing = document.getElementById('moderatorToast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'moderatorToast';
        toast.style.cssText = [
            'position:fixed',
            'bottom:24px',
            'right:24px',
            'z-index:9999',
            'max-width:380px',
            'width:100%',
            'background:' + getThemeColor('--bg-card', '#ffffff'),
            'border-radius:16px',
            'box-shadow:0 12px 40px ' + getThemeColor('--shadow-lg', 'rgba(0,0,0,0.18)'),
            'border-left:5px solid #04A052',
            'padding:16px 18px',
            'display:flex',
            'gap:12px',
            'align-items:flex-start',
            'transition:opacity .3s ease, transform .3s ease',
        ].join(';');

        toast.innerHTML = `
            <div style="width:40px;height:40px;border-radius:50%;background:${getThemeColor('--bg-active', '#EAF8F0')};display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;"><i class="fas fa-bullhorn" aria-hidden="true"></i></div>
            <div style="flex:1;min-width:0;">
                <p style="margin:0;font-weight:700;font-size:14px;color:${getThemeColor('--text-primary', '#163B2D')};">${escapeHtml(title)}</p>
                <p style="margin:4px 0 0;font-size:13px;color:${getThemeColor('--text-secondary', '#6B7280')};line-height:1.45;">${escapeHtml(message)}</p>
                ${safePath(link, '') ? `<a href="${escapeHtml(safePath(link, ''))}" style="display:inline-block;margin-top:10px;font-size:13px;font-weight:600;color:#04A052;text-decoration:none;">${escapeHtml(linkLabel || 'View')} →</a>` : ''}
            </div>
            <button type="button" style="background:none;border:none;color:${getThemeColor('--text-muted', '#9CA3AF')};font-size:14px;cursor:pointer;padding:2px;" aria-label="Dismiss"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        `;

        toast.querySelector('button').addEventListener('click', () => dismiss(toast));
        document.body.appendChild(toast);

        setTimeout(() => dismiss(toast), 8000);
    }

    function getThemeColor(varName, fallback) {
        const val = getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
        return val || fallback;
    }

    function dismiss(toast) {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(12px)';
        setTimeout(() => toast.remove(), 300);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function safePath(url, fallback = '/') {
        if (typeof url !== 'string' || !url.startsWith('/') || url.startsWith('//')) {
            return fallback;
        }

        return url;
    }
});
