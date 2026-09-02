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
