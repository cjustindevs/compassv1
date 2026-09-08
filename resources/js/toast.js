// ─────────────────────────────────────────────────────────────
//  COMPASS — Global UI helpers: toasts, confirmation modals,
//  declarative [data-confirm] wiring, and flash-message toasts.
//  Imported once by app.js so it is available on every page.
// ─────────────────────────────────────────────────────────────

/* ── Toast notifications ───────────────────────────────────── */
const TOAST_COLORS = {
    success: 'border-green-500 text-green-600',
    error:   'border-red-500 text-red-600',
    warning: 'border-yellow-500 text-yellow-600',
    info:    'border-blue-500 text-blue-600',
};

const TOAST_ICONS = {
    success: 'fa-check-circle',
    error:   'fa-times-circle',
    warning: 'fa-exclamation-triangle',
    info:    'fa-info-circle',
};

function showToast(message, type = 'info', duration = 3500) {
    if (typeof message !== 'string' || !message.length) return;

    const color = TOAST_COLORS[type] || TOAST_COLORS.info;
    const icon  = TOAST_ICONS[type] || TOAST_ICONS.info;

    const toast = document.createElement('div');
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.className =
        'fixed bottom-20 right-4 max-w-sm w-[calc(100%-2rem)] sm:w-sm z-[2000] ' +
        'bg-white rounded-xl shadow-2xl border-l-4 ' + color + ' ' +
        'transform transition-all duration-300 translate-y-2 opacity-0';

    const wrapper = document.createElement('div');
    wrapper.className = 'p-4 flex items-start gap-3';

    const iconWrap = document.createElement('div');
    iconWrap.className = 'flex-shrink-0 mt-0.5';
    const iconEl = document.createElement('i');
    iconEl.className = 'fas ' + icon + ' text-lg';
    iconWrap.appendChild(iconEl);

    const messageEl = document.createElement('div');
    messageEl.className = 'flex-1 text-sm text-gray-700 leading-snug';
    messageEl.textContent = message;

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.setAttribute('aria-label', 'Dismiss');
    closeBtn.className = 'flex-shrink-0 text-gray-400 hover:text-gray-600 transition';
    const closeIcon = document.createElement('i');
    closeIcon.className = 'fas fa-times';
    closeBtn.appendChild(closeIcon);

    wrapper.append(iconWrap, messageEl, closeBtn);
    toast.appendChild(wrapper);

    document.body.appendChild(toast);

    // Animate in
    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
    });

    const remove = () => {
        toast.classList.add('translate-y-2', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    };

    closeBtn.addEventListener('click', remove);
    if (duration > 0) {
        setTimeout(remove, duration);
    }

    return toast;
}

window.showToast = showToast;

/* ── Confirmation modal ────────────────────────────────────── */
function confirmAction(options = {}) {
    return new Promise((resolve) => {
        const modal    = document.getElementById('confirmationModal');
        const titleEl  = document.getElementById('modalTitle');
        const msgEl    = document.getElementById('modalMessage');
        const iconEl   = document.getElementById('modalIcon');
        const confirmBtn = document.getElementById('modalConfirm');
        const cancelBtn  = document.getElementById('modalCancel');

        if (!modal || !confirmBtn || !cancelBtn) {
            // Modal markup missing — fail safe to a native confirm.
            resolve(window.confirm(options.message || 'Are you sure?'));
            return;
        }

        titleEl.textContent  = options.title || 'Are you sure?';
        msgEl.textContent    = options.message || 'This action cannot be undone.';
        iconEl.innerHTML     = options.icon || '<i class="fas fa-exclamation-triangle text-yellow-500 text-5xl"></i>';
        confirmBtn.textContent = options.confirmText || 'Confirm';
        confirmBtn.className =
            'btn-modal-confirm px-6 py-2.5 text-white rounded-lg font-medium transition-colors ' +
            'focus:outline-none focus:ring-2 focus:ring-offset-2 ' +
            (options.confirmClass || 'bg-red-600 hover:bg-red-700 focus:ring-red-500');

        let settled = false;
        const close = (result) => {
            if (settled) return;
            settled = true;
            modal.classList.remove('active');
            document.removeEventListener('keydown', onKey);
            confirmBtn.removeEventListener('click', onConfirm);
            cancelBtn.removeEventListener('click', onCancel);
            modal.removeEventListener('click', onOverlay);
            resolve(result);
        };
        const onConfirm = () => close(true);
        const onCancel  = () => close(false);
        const onOverlay = (e) => { if (e.target === modal) close(false); };
        const onKey     = (e) => { if (e.key === 'Escape') close(false); };

        confirmBtn.addEventListener('click', onConfirm);
        cancelBtn.addEventListener('click', onCancel);
        modal.addEventListener('click', onOverlay);
        document.addEventListener('keydown', onKey);

        modal.classList.add('active');
    });
}

window.confirmAction = confirmAction;

/* ── Declarative [data-confirm] wiring ───────────────────────
   Add data-confirm="Title" (and optional data-confirm-message,
   data-confirm-text, data-confirm-class) to any <form>, <a> or
   <button>. The action only proceeds after the user confirms.
   ──────────────────────────────────────────────────────────── */
function initConfirmDelegation() {
    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('form[data-confirm]');
        if (!form || form.dataset.confirmed === '1') return;
        e.preventDefault();

        const ok = await confirmAction({
            title:    form.dataset.confirm || 'Are you sure?',
            message: form.dataset.confirmMessage || 'This action cannot be undone.',
            confirmText: form.dataset.confirmText || 'Confirm',
            confirmClass: form.dataset.confirmClass || 'bg-red-600 hover:bg-red-700 focus:ring-red-500',
            icon:     form.dataset.confirmIcon || '<i class="fas fa-exclamation-triangle text-yellow-500 text-5xl"></i>',
        });

        if (ok) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    }, true);

    document.addEventListener('click', async (e) => {
        const trigger = e.target.closest('[data-confirm]:not(form)');
        if (!trigger) return;
        // Let real links/buttons behave only after confirmation.
        e.preventDefault();

        const ok = await confirmAction({
            title:    trigger.dataset.confirm || 'Are you sure?',
            message: trigger.dataset.confirmMessage || 'This action cannot be undone.',
            confirmText: trigger.dataset.confirmText || 'Confirm',
            confirmClass: trigger.dataset.confirmClass || 'bg-red-600 hover:bg-red-700 focus:ring-red-500',
            icon:     trigger.dataset.confirmIcon || '<i class="fas fa-exclamation-triangle text-yellow-500 text-5xl"></i>',
        });

        if (!ok) return;

        if (trigger.tagName === 'A' && trigger.href) {
            window.location.href = trigger.href;
        } else if (trigger.dataset.confirmSubmit) {
            const target = document.querySelector(trigger.dataset.confirmSubmit);
            if (target) {
                target.dataset.confirmed = '1';
                target.submit();
            }
        } else {
            trigger.click();
        }
    }, true);
}

/* ── Flash message → toast conversion ────────────────────────
   Any element marked with [data-flash] (e.g. the server-rendered
   alert blocks) is turned into a toast and removed from the DOM.
   Also honours a window.__flash = [{type,message}] payload.
   ──────────────────────────────────────────────────────────── */
function convertFlashAlerts() {
    document.querySelectorAll('[data-flash]').forEach((el) => {
        let type = 'info';
        if (el.classList.contains('alert-success')) type = 'success';
        else if (el.classList.contains('alert-error')) type = 'error';
        else if (el.classList.contains('alert-warning')) type = 'warning';
        else if (el.classList.contains('alert-info')) type = 'info';

        const msg = (el.dataset.flashMessage || el.textContent || '').trim();
        if (msg) showToast(msg, type);
        el.remove();
    });

    if (Array.isArray(window.__flash)) {
        window.__flash.forEach((f) => showToast(f.message, f.type || 'info'));
        window.__flash = [];
    }
}

/* ── Optional loading-state helper for async buttons ───────── */
function initLoadingButtons() {
    document.addEventListener('submit', (e) => {
        const btn = e.target.querySelector('[data-loading]');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            const label = btn.innerHTML;
            btn.dataset.loadingLabel = label;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + (btn.dataset.loadingText || 'Please wait…');
            // Re-enable if the page doesn't navigate away (validation error, etc.)
            setTimeout(() => {
                if (document.body.contains(btn)) {
                    btn.disabled = false;
                    btn.innerHTML = btn.dataset.loadingLabel;
                }
            }, 4000);
        }
    }, true);
}

/* ── Initialise once the DOM is ready ──────────────────────── */
document.addEventListener('DOMContentLoaded', function () {
    initConfirmDelegation();
    initLoadingButtons();
    convertFlashAlerts();
});
