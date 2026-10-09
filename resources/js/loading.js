import { uiIcon } from './ui-icon';
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
    button.setAttribute('aria-busy', 'true');
    button.innerHTML = '' + uiIcon('spinner', 'ui-spin') + ' ' + text;
}

export function resetButton(button) {
    if (!button) return;
    button.disabled = false;
    button.removeAttribute('aria-busy');
    if (button.dataset.originalHtml !== undefined) {
        button.innerHTML = button.dataset.originalHtml;
    }
}

window.setButtonLoading = setButtonLoading;
window.resetButton = resetButton;
