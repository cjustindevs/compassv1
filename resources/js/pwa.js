// ── Offline Queue: queue actions while offline, replay them when back online ──
class OfflineQueue {
    constructor() {
        this.queue = [];
        localStorage.removeItem('offlineQueue');
        this.processQueue();
    }

    add(action) {
        console.warn('Offline queue disabled for protected COMPASS actions.', action?.url || 'unknown action');
    }

    processQueue() {
        if (!navigator.onLine || this.queue.length === 0) return;

        const item = this.queue[0];
        this.sendAction(item)
            .then(() => {
                this.queue.shift();
                this.processQueue();
            })
            .catch(() => {
                console.log('Retry later:', item);
            });
    }

    sendAction(action) {
        return fetch(action.url, {
            method: action.method || 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(action.data)
        });
    }
}

window.offlineQueue = new OfflineQueue();

// ── Custom install banner (first visit only) ──
export function showInstallBanner() {
    const banner = document.createElement('div');
    banner.id = 'installBanner';
    banner.style.cssText = 'position:fixed;bottom:0;left:0;right:0;background:#ffffff;box-shadow:0 -8px 40px rgba(0,0,0,0.12);border-top:4px solid #04A052;padding:16px;z-index:9997;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;';
    banner.innerHTML = `
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:48px;height:48px;border-radius:12px;background:#04A052;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:22px;flex-shrink:0;">C</div>
            <div>
                <p style="margin:0;font-weight:600;color:#163B2D;font-size:15px;">Install COMPASS</p>
                <p style="margin:2px 0 0;font-size:13px;color:#6B7280;">Get the app for a better experience</p>
            </div>
        </div>
        <div style="display:flex;gap:10px;align-items:center;">
            <button type="button" id="installBannerBtn" style="padding:10px 24px;background:#04A052;color:#fff;border:none;border-radius:50px;font-weight:600;font-size:14px;cursor:pointer;transition:background 0.2s;">Install</button>
            <button type="button" id="dismissBannerBtn" style="padding:10px 16px;background:none;color:#6B7280;border:none;font-size:14px;cursor:pointer;">Dismiss</button>
        </div>
    `;
    document.body.appendChild(banner);

    document.getElementById('installBannerBtn').addEventListener('click', () => {
        if (window.deferredPrompt) {
            window.deferredPrompt.prompt();
            window.deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('User accepted PWA install');
                } else {
                    console.log('User dismissed PWA install');
                }
                window.deferredPrompt = null;
                banner.remove();
            });
        } else {
            banner.remove();
        }
    });

    document.getElementById('dismissBannerBtn').addEventListener('click', () => {
        banner.remove();
        localStorage.setItem('pwaBannerDismissed', 'true');
    });
}

// ── Show the banner ONLY on the landing page, when not already
//    installed as a standalone PWA and when the user hasn't
//    dismissed it before. This prevents the intrusive banner
//    from appearing on every authenticated page. ──
const isStandalone =
    window.matchMedia('(display-mode: standalone)').matches ||
    window.navigator.standalone === true;

const isLandingPage =
    window.location.pathname === '/' || window.location.pathname === '/home';

const bannerDismissed = localStorage.getItem('pwaBannerDismissed') === 'true';

if (!bannerDismissed && !isStandalone && isLandingPage) {
    // Small delay so it does not pop in before the page settles.
    setTimeout(showInstallBanner, 2500);
}
