/**
 * Real-time notifications for the Adviser workspace.
 *
 * Listens on the private `adviser.{userId}` channel for EmergencyTriggered
 * and ReferralRecommended events, shows a toast, and keeps the sidebar
 * badges up to date.
 */
document.addEventListener('DOMContentLoaded', function () {
    const userId = document.querySelector('meta[name="user-id"]')?.content;

    if (!userId || !window.Echo) {
        return;
    }

    window.Echo.private(`adviser.${userId}`)
        .listen('EmergencyTriggered', (event) => {
            incrementBadge('emergencyBadge');
            incrementBadge('notifBadge');

            showToast(
                '🚨 Emergency Flagged',
                `${event.seeker_alias || 'A seeker'} · ${event.risk_level || 'Emergency'} risk · flagged by ${event.helper_name || 'a peer helper'}. ${event.description || ''}`,
                event.link || '/adviser/dashboard',
                'View Dashboard'
            );
        })
        .listen('ReferralRecommended', (event) => {
            incrementBadge('referralBadge');
            incrementBadge('notifBadge');

            showToast(
                'New Referral Recommendation',
                `${event.seeker_alias || 'A seeker'} · ${event.priority_level || 'Low'} priority · recommended by ${event.helper_name || 'a peer helper'}. ${event.reason || ''}`,
                event.link || '/adviser/referrals',
                'Review Referrals'
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
        fetch('/adviser/notifications/unread-count', {
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

    function showToast(title, message, link, linkLabel) {
        const existing = document.getElementById('adviserToast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'adviserToast';
        toast.style.cssText = [
            'position:fixed',
            'bottom:24px',
            'right:24px',
            'z-index:9999',
            'max-width:380px',
            'width:100%',
            'background:#ffffff',
            'border-radius:16px',
            'box-shadow:0 12px 40px rgba(0,0,0,0.18)',
            'border-left:5px solid #04A052',
            'padding:16px 18px',
            'display:flex',
            'gap:12px',
            'align-items:flex-start',
            'transition:opacity .3s ease, transform .3s ease',
        ].join(';');

        toast.innerHTML = `
            <div style="width:40px;height:40px;border-radius:50%;background:#EAF8F0;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">📢</div>
            <div style="flex:1;min-width:0;">
                <p style="margin:0;font-weight:700;font-size:14px;color:#163B2D;">${escapeHtml(title)}</p>
                <p style="margin:4px 0 0;font-size:13px;color:#6B7280;line-height:1.45;">${escapeHtml(message)}</p>
                ${link ? `<a href="${escapeHtml(link)}" style="display:inline-block;margin-top:10px;font-size:13px;font-weight:600;color:#04A052;text-decoration:none;">${escapeHtml(linkLabel || 'View')} →</a>` : ''}
            </div>
            <button type="button" style="background:none;border:none;color:#9CA3AF;font-size:14px;cursor:pointer;padding:2px;" aria-label="Dismiss">✕</button>
        `;

        toast.querySelector('button').addEventListener('click', () => dismiss(toast));
        document.body.appendChild(toast);

        setTimeout(() => dismiss(toast), 8000);
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
});