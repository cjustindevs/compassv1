// ─────────────────────────────────────────────────────────────
//  COMPASS — Loading state helpers
//  Imported once by app.js. Exposes window.* helpers so classic
//  <script> blocks (e.g. the queue view) can use them too.
// ─────────────────────────────────────────────────────────────

export function showLoading(message = 'Loading…') {
    hideLoading();

    const overlay = document.createElement('div');
    overlay.id = 'loadingOverlay';
    overlay.className = 'loading-overlay';
    overlay.innerHTML =
        '<div class="loading-content">' +
            '<div class="loading-spinner"></div>' +
            '<p class="loading-text">' + message + '</p>' +
        '</div>';

    document.body.appendChild(overlay);
}

export function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) overlay.remove();
}

export function setButtonLoading(button, text = 'Processing…') {
    if (!button) return;
    if (button.dataset.originalHtml === undefined) {
        button.dataset.originalHtml = button.innerHTML;
    }
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + text;
}

export function resetButton(button) {
    if (!button) return;
    button.disabled = false;
    if (button.dataset.originalHtml !== undefined) {
        button.innerHTML = button.dataset.originalHtml;
    }
}

window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.setButtonLoading = setButtonLoading;
window.resetButton = resetButton;

document.addEventListener('DOMContentLoaded', () => {
    document.documentElement.classList.add('app-ready');

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        if (link.target && link.target !== '_self') {
            return;
        }

        const href = link.getAttribute('href') || '';

        if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
            return;
        }

        const destination = new URL(href, window.location.href);

        if (destination.origin !== window.location.origin || destination.href === window.location.href) {
            return;
        }

        showLoading('Loading page...');
    }, { capture: true });
});

window.addEventListener('pageshow', hideLoading);
window.addEventListener('load', () => window.setTimeout(hideLoading, 150));
