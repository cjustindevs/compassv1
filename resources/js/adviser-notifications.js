import { uiIcon } from './ui-icon';
/**
 * Real-time notifications for the Adviser workspace.
 *
 * Listens on the private `adviser.{userId}` channel for EmergencyTriggered
 * and ReferralRecommended events; polls existing screening notifications,
 * shows a review popup, and keeps the sidebar badges up to date.
 */
document.addEventListener('DOMContentLoaded', function () {
    const userId = document.querySelector('[data-adviser-user-id]')?.dataset.adviserUserId;

    if (!userId) {
        return;
    }

    window.Echo?.private(`adviser.${userId}`)
        .listen('EmergencyTriggered', (event) => {
            incrementBadge('emergencyBadge');
            incrementBadge('notifBadge');

            // The existing staffEmergencyNotice dialog owns emergency popups.
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

    // Screening alerts already exist in the database but have no broadcast event.
    // Poll the owned notifications even when WebSockets are unavailable.
    const storageKey = `compass-adviser-review-notice:${userId}`;
    let seen = 0;
    try { seen = Number(sessionStorage.getItem(storageKey)) || 0; } catch {}
    let polling = false;
    refreshUnreadCount();
    setInterval(refreshUnreadCount, 15000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshUnreadCount();
    });

    function incrementBadge(id) {
        const badge = document.getElementById(id);
        if (badge) {
            badge.textContent = (parseInt(badge.textContent) || 0) + 1;
            badge.style.display = 'inline-flex';
        }
    }

    async function refreshUnreadCount() {
        if (polling || document.hidden || !navigator.onLine || document.querySelector('dialog[open]')) return;
        polling = true;
        try {
            const response = await fetch('/adviser/notifications/unread-count?include_reviews=1', {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) return;
            const data = await response.json();
            updateBadge('notifBadge', data.count);
            updateBadge('pendingReviewsBadge', data.screening_count);
            const fresh = (data.reviews || []).filter(notice => Number(notice.id) > seen);
            if (document.querySelector('dialog[open]')) return;
            if (fresh.length) {
                const newest = fresh[0];
                showToast(fresh.length > 1 ? 'New screening review alerts' : newest.title,
                    fresh.length > 1 ? 'Multiple assigned screenings need your review. Open Screening reviews to see them.' : newest.message,
                    fresh.length > 1 ? '/adviser/screenings' : newest.link, 'Review screening');
            }
            seen = Math.max(seen, Number(data.review_cursor) || 0);
            try { sessionStorage.setItem(storageKey, String(seen)); } catch {}
        } catch {
            // Keep the current page usable during a network interruption.
        } finally { polling = false; }
    }

    function updateBadge(id, count) {
        let badge = document.getElementById(id);
        const parent = document.querySelector(`[data-adviser-badge="${id}"]`);
        if (!badge && Number(count) > 0 && parent) {
            badge = document.createElement('span');
            badge.id = id;
            badge.className = 'nav-badge';
            parent.appendChild(badge);
        }
        if (!badge) return;
        badge.textContent = Number(count) || 0;
        badge.style.display = Number(count) > 0 ? 'inline-flex' : 'none';
    }

    function showToast(title, message, link, linkLabel) {
        const existing = document.getElementById('adviserToast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'adviserToast';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        toast.style.cssText = [
            'position:fixed',
            'bottom:24px',
            'right:16px',
            'z-index:9999',
            'width:min(380px,calc(100vw - 32px))',
            'background:' + getThemeColor('--bg-card', '#ffffff'),
            'border-radius:10px',
            'box-shadow:0 4px 20px ' + getThemeColor('--shadow-lg', 'rgba(0,0,0,0.12)'),
            'border-left:5px solid #04A052',
            'padding:16px 18px',
            'display:flex',
            'gap:12px',
            'align-items:flex-start',
            'overflow-wrap:anywhere',
        ].join(';');

        toast.innerHTML = `

            <div style="flex:1;min-width:0;">
                <p style="margin:0;font-weight:700;font-size:14px;color:${getThemeColor('--text-primary', '#163B2D')};">${escapeHtml(title)}</p>
                <p style="margin:4px 0 0;font-size:13px;color:${getThemeColor('--text-secondary', '#6B7280')};line-height:1.45;">${escapeHtml(message)}</p>
                ${safePath(link, '') ? `<a href="${escapeHtml(safePath(link, ''))}" style="display:inline-flex;align-items:center;justify-content:center;min-height:40px;margin-top:10px;padding:8px 12px;border:1px solid #cce8d9;border-radius:8px;font-size:13px;font-weight:600;color:#027039;text-decoration:none;">${escapeHtml(linkLabel || 'View')}</a>` : ''}
            </div>
            <button type="button" style="background:none;border:none;color:${getThemeColor('--text-muted', '#9CA3AF')};font-size:14px;cursor:pointer;padding:8px;min-width:40px;min-height:40px;" aria-label="Dismiss">${uiIcon('close', '')}</button>
        `;

        toast.querySelector('button').addEventListener('click', () => dismiss(toast));
        document.body.appendChild(toast);

        // Stay visible until dismissed or opened; never change notification read state here.
    }

    function getThemeColor(varName, fallback) {
        const val = getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
        return val || fallback;
    }

    function dismiss(toast) {
        toast.remove();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML.replaceAll('"', '&quot;').replaceAll("'", '&#39;');
    }

    function safePath(url, fallback = '/') {
        if (typeof url !== 'string' || !url.startsWith('/') || url.startsWith('//') || url.includes('\\')) {
            return fallback;
        }

        return url;
    }
});
