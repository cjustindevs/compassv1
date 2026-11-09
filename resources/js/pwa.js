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
    // Only offer installation when the browser actually supports it right now.
    if (!window.deferredPrompt || document.getElementById('installBanner')) return;
    const banner = document.createElement('div');
    banner.id = 'installBanner';
    banner.className = 'compass-install-banner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', 'Install COMPASS');
    banner.innerHTML = `
        <div class="compass-install-text"><strong>Install COMPASS</strong><p>Open it like an app from your home screen.</p></div>
        <div class="compass-install-actions">
            <button type="button" data-install-dismiss>Not now</button>
            <button type="button" class="is-primary" data-install-accept>Install</button>
        </div>`;
    document.body.appendChild(banner);

    const close = () => banner.remove();
    banner.querySelector('[data-install-accept]').addEventListener('click', () => {
        const prompt = window.deferredPrompt;
        if (!prompt) return close();
        prompt.prompt();
        prompt.userChoice.finally(() => { window.deferredPrompt = null; close(); });
    });
    banner.querySelector('[data-install-dismiss]').addEventListener('click', () => {
        close();
        try { localStorage.setItem('pwaBannerDismissed', 'true'); } catch (e) { /* storage optional */ }
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

let bannerDismissed = false;
try { bannerDismissed = localStorage.getItem('pwaBannerDismissed') === 'true'; } catch (e) { /* storage optional */ }

if (!bannerDismissed && !isStandalone && isLandingPage) {
    // Wait for the browser's install event, then let the page settle first.
    window.addEventListener('beforeinstallprompt', () => setTimeout(showInstallBanner, 2500), { once: true });
}
