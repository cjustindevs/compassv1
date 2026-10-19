(() => {
    if (window.CompassDialogs) return;
    let opener = null;
    function dirty(dialog) { return dialog.querySelector('form[data-dirty="1"]'); }
    function canClose(dialog) { return !dirty(dialog) || window.confirm('Discard your unsaved entries?'); }
    function close(dialog, saved = false) {
        if (!dialog || (!saved && !canClose(dialog))) return false;
        dialog.querySelectorAll('form').forEach(form => delete form.dataset.dirty);
        dialog.close(); return true;
    }
    function open(dialog, trigger) {
        if (!dialog || dialog.open) return !!dialog;
        const current = [...document.querySelectorAll('dialog[open]')].filter(node => node !== dialog);
        if (current.some(node => !canClose(node))) return false;
        current.forEach(node => close(node, true));
        opener = trigger || document.activeElement;
        dialog._workflowOpener = opener;
        dialog.showModal();
        dialog.querySelector('[autofocus],input:not([type=hidden]),select,textarea,[data-dialog-close],button')?.focus();
        return true;
    }
    window.CompassDialogs = {open, close};
    document.addEventListener('input', event => {
        const form = event.target.closest('dialog form');
        if (form && !form.dataset.identitySaved) form.dataset.dirty = '1';
    });
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-dialog-open]');
        if (trigger) { event.preventDefault(); open(document.getElementById(trigger.dataset.dialogOpen), trigger); return; }
        const dismiss = event.target.closest('[data-dialog-close]');
        if (dismiss) { event.preventDefault(); close(dismiss.closest('dialog')); }
    });
    document.querySelectorAll('dialog').forEach(dialog => {
        dialog.addEventListener('cancel', event => { if (!canClose(dialog)) event.preventDefault(); });
        dialog.addEventListener('close', () => dialog._workflowOpener?.focus());
        dialog.addEventListener('click', event => {
            if (!dialog.classList.contains('workflow-dialog') || event.target !== dialog) return;
            const r = dialog.getBoundingClientRect();
            if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) close(dialog);
        });
        dialog.querySelectorAll('form').forEach(form => {
            // Existing async forms own their submission and error recovery.
            if (!dialog.classList.contains('workflow-dialog')) return;
            form.addEventListener('submit', event => {
                if (event.defaultPrevented) return;
                if (!form.reportValidity() || form.dataset.submitting) { event.preventDefault(); return; }
                form.dataset.submitting = '1'; delete form.dataset.dirty;
                const button = event.submitter || form.querySelector('[type=submit]');
                if (button) { button.disabled = true; button.textContent = 'Saving…'; }
            });
        });
    });
    window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); });
})();
