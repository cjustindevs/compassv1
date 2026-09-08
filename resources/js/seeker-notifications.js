/**
 * Real-time notifications for the Seeker workspace.
 *
 * Listens on the private `seeker.{userId}` channel for CaseAccepted and
 * CaseDeclined events, shows a toast, and keeps the UI in sync with the
 * session lifecycle (queue → accepted → chat).
 */
document.addEventListener('DOMContentLoaded', function () {
    const userId = document.querySelector('meta[name="user-id"]')?.content;

    if (!userId || !window.Echo) {
        return;
    }

    window.Echo.private(`seeker.${userId}`)
        .listen('CaseAccepted', (event) => {
            showToast(
                '🎉 Session Accepted!',
                event.message || 'A helper accepted your request. You can now start chatting.',
                event.link || '/session/chat',
                'Open Chat'
            );

            // On the matching page, move straight to the active chat.
            if (window.location.pathname.includes('/request/matching')) {
                setTimeout(() => {
                    window.location.href = safePath(event.link, '/session/chat');
                }, 2500);
            }
        })
        .listen('CaseDeclined', (event) => {
            showToast(
                'Helper Unavailable',
                event.message || 'A helper was unable to take your request. We are looking for another helper.',
                event.link || '/request/matching',
                'View Status'
            );

            // On the matching page, show the "searching again" state right away.
            if (window.location.pathname.includes('/request/matching')) {
                renderMatchingState('searching', event);

                // Refresh the matching page so the queue state is up to date.
                setTimeout(() => {
                    window.location.reload();
                }, 2500);
            }
        })
        .listen('NewHelperAssigned', (event) => {
            showToast(
                'New Helper Assigned',
                `${event.helper_name || 'A new helper'} has been assigned to your request.`,
                event.link || '/request/matching',
                'View Status'
            );

            // On the matching page, swap in the "new helper assigned" state.
            if (window.location.pathname.includes('/request/matching')) {
                renderMatchingState('assigned', event);

                // Reload so the server-rendered helper card takes over.
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            }
        })
        .listen('SessionEnded', (event) => {
            showToast(
                'Session Ended',
                event.message || 'Your session has been ended.',
                event.seeker_redirect || '/session/evaluation',
                'Leave Feedback'
            );

            // On the chat/voice page, move the seeker to the evaluation page.
            if (window.location.pathname.includes('/session/')) {
                setTimeout(() => {
                    window.location.href = safePath(event.seeker_redirect, '/session/evaluation');
                }, 2500);
            }
        });

    // Fallback: if the websocket is down, poll the unread count so the
    // notification badge stays roughly in sync.
    const echoConnector = window.Echo.connector;
    setInterval(() => {
        const state = echoConnector?.pusher?.connection?.state;
        if (state && state !== 'connected') {
            refreshUnreadCount();
        }
    }, 30000);

    function refreshUnreadCount() {
        fetch('/notifications/unread-count', {
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
        const existing = document.getElementById('seekerToast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.id = 'seekerToast';
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
            <div style="width:40px;height:40px;border-radius:50%;background:${getThemeColor('--bg-active', '#EAF8F0')};display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;">💬</div>
            <div style="flex:1;min-width:0;">
                <p style="margin:0;font-weight:700;font-size:14px;color:${getThemeColor('--text-primary', '#163B2D')};">${escapeHtml(title)}</p>
                <p style="margin:4px 0 0;font-size:13px;color:${getThemeColor('--text-secondary', '#6B7280')};line-height:1.45;">${escapeHtml(message)}</p>
                ${safePath(link, '') ? `<a href="${escapeHtml(safePath(link, ''))}" style="display:inline-block;margin-top:10px;font-size:13px;font-weight:600;color:#04A052;text-decoration:none;">${escapeHtml(linkLabel || 'View')} →</a>` : ''}
            </div>
            <button type="button" style="background:none;border:none;color:${getThemeColor('--text-muted', '#9CA3AF')};font-size:14px;cursor:pointer;padding:2px;" aria-label="Dismiss">✕</button>
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

    /**
     * Live-update the matching page while a helper is being re-matched.
     * Falls back gracefully if the container isn't on the page.
     */
    function renderMatchingState(state, event) {
        const container = document.getElementById('matchingLiveState');
        if (!container) return;

        container.classList.remove('hidden');
        container.style.display = 'block';

        if (state === 'searching') {
            container.innerHTML = `
                <div class="text-center py-6">
                    <div class="text-5xl mb-4">🔄</div>
                    <h2 class="text-2xl font-bold text-gray-800">Helper declined. Looking for another helper...</h2>
                    <p class="text-gray-500 mt-2 max-w-md mx-auto">
                        We are searching for another available helper for your request.
                    </p>
                    <div class="mt-4 flex justify-center">
                        <div class="animate-pulse flex space-x-2">
                            <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                            <div class="w-3 h-3 bg-yellow-500 rounded-full animation-delay-200"></div>
                            <div class="w-3 h-3 bg-yellow-500 rounded-full animation-delay-400"></div>
                        </div>
                    </div>
                </div>
            `;
        } else if (state === 'assigned') {
            const name = escapeHtml(event.helper_name || 'A peer helper');
            const level = event.competency_level
                ? `<span class="text-xs text-green-600 font-medium">Level ${escapeHtml(String(event.competency_level))} Peer Helper</span>`
                : '';
            container.innerHTML = `
                <div class="text-center py-6">
                    <div class="text-5xl mb-4">✅</div>
                    <h2 class="text-2xl font-bold text-gray-800">New Helper Assigned!</h2>
                    <p class="text-gray-500 mt-2 max-w-md mx-auto">
                        ${name} has been assigned to your request. Waiting for them to accept...
                    </p>
                    <div class="mt-4 inline-block px-4 py-2 bg-green-50 rounded-xl border border-green-200">
                        <span class="font-semibold text-gray-800">${name}</span> ${level}
                    </div>
                    <div class="mt-4 flex justify-center">
                        <div class="animate-pulse flex space-x-2">
                            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
                            <div class="w-3 h-3 bg-green-500 rounded-full animation-delay-200"></div>
                            <div class="w-3 h-3 bg-green-500 rounded-full animation-delay-400"></div>
                        </div>
                    </div>
                </div>
            `;
        }
    }
});
