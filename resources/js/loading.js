// ─────────────────────────────────────────────────────────────
//  COMPASS — Button loading helpers
//  Imported once by app.js. Exposes window.* helpers so classic
//  <script> blocks (e.g. the queue view) can use them too.
// ─────────────────────────────────────────────────────────────

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

window.setButtonLoading = setButtonLoading;
window.resetButton = resetButton;
