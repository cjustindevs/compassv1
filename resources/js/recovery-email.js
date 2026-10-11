const recovery = document.querySelector('[data-recovery-email]');

if (recovery) {
    const status = recovery.querySelector('[data-recovery-status]');
    const verify = recovery.querySelector('[data-recovery-verify]');
    recovery.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            if (button.disabled || !form.reportValidity()) return;
            button.disabled = true;
            status.textContent = 'Please wait…';
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: new FormData(form),
                });
                const data = await response.json();
                status.textContent = Object.values(data.errors || {}).flat()[0]
                    || data.message || 'Unable to complete verification. Please try again.';
                if (response.ok && form.matches('[data-recovery-send]')) {
                    form.querySelector('[name="current_password"]').value = '';
                    verify.hidden = false;
                    verify.querySelector('input[name="otp"]').focus();
                } else if (response.ok) {
                    recovery.querySelector('[data-recovery-linked]').textContent = 'Verified — ready for password-reset links.';
                    verify.reset();
                    verify.hidden = true;
                }
            } catch {
                status.textContent = 'Could not complete verification. Refresh the page if your sign-in expired, then try again.';
            } finally {
                button.disabled = false;
            }
        });
    });
}
