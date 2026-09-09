/**
 * Real-time notifications for the Helper workspace.
 *
 * Listens on the private `helper.{userId}` channel for NewCaseAssigned
 * events, shows a toast, and keeps the sidebar badges up to date.
 */
document.addEventListener('DOMContentLoaded', function () {
    const userId = document.querySelector('meta[name="user-id"]')?.content;

    if (!userId || !window.Echo) {
        return;
    }

    window.Echo.private(`helper.${userId}`)
        .listen('NewCaseAssigned', (event) => {
            incrementBadge('caseBadge');
            incrementBadge('notifBadge');

            const pendingCount = document.getElementById('pendingCount');
            if (pendingCount) {
                pendingCount.textContent = (parseInt(pendingCount.textContent) || 0) + 1;
            }

            showToast(
                'New Case Assigned',
                `A new case ${event.case_id || 'R-' + event.session_id} from ${event.seeker_alias || 'a seeker'} has been assigned to you.`,
                event.link || '/helper/cases'
            );
        })
        .listen('SessionEnded', (event) => {
            showToast(
                'Session Ended',
                event.message || 'A session has ended. Please complete your session notes.',
                event.helper_redirect || `/helper/session/${event.session_id}/notes`
            );

            // On the chat/voice page, move the helper to the session notes.
            if (window.location.pathname.includes('/helper/session/') &&
                (window.location.pathname.includes('/chat') || window.location.pathname.includes('/voice'))) {
                setTimeout(() => {
                    window.location.href = safePath(event.helper_redirect, `/helper/session/${event.session_id}/notes`);
                }, 2500);
            }
        })
        .listen('ReferralApproved', (event) => {
            incrementBadge('notifBadge');

            showToast(
                'Referral Approved',
                event.message || 'Your referral has been approved by the adviser.',
                event.link || '/helper/cases'
            );
        })
        .listen('EvaluationCompleted', (event) => {
            incrementBadge('notifBadge');

            showToast(
                'Evaluation Ready',
                event.message || 'Your competency evaluation is ready.',
                event.link || '/helper/competency'
            );
        });

    // Fallback: if the websocket is down, keep the unread notification
    // badge roughly in sync by polling the existing endpoint.
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
        fetch('/helper/notifications/unread-count', {
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

    function showToast(title, message, link) {
        const existing = document.getElementById('helperToast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'helperToast';
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
            <div style="width:40px;height:40px;border-radius:50%;background:${getThemeColor('--bg-active', '#EAF8F0')};display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;"><i class="fas fa-clipboard-list" aria-hidden="true"></i></div>
            <div style="flex:1;min-width:0;">
                <p style="margin:0;font-weight:700;font-size:14px;color:${getThemeColor('--text-primary', '#163B2D')};">${escapeHtml(title)}</p>
                <p style="margin:4px 0 0;font-size:13px;color:${getThemeColor('--text-secondary', '#6B7280')};line-height:1.45;">${escapeHtml(message)}</p>
                ${safePath(link, '') ? `<a href="${escapeHtml(safePath(link, ''))}" style="display:inline-block;margin-top:10px;font-size:13px;font-weight:600;color:#04A052;text-decoration:none;">View Cases →</a>` : ''}
            </div>
            <button type="button" style="background:none;border:none;color:${getThemeColor('--text-muted', '#9CA3AF')};font-size:14px;cursor:pointer;padding:2px;" aria-label="Dismiss"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        `;

        toast.querySelector('button').addEventListener('click', () => dismiss(toast));
        document.body.appendChild(toast);

        setTimeout(() => dismiss(toast), 6000);
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
