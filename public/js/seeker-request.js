document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('form[data-request-form]').forEach(form => {
        const snapshot = () => JSON.stringify([...new FormData(form)].filter(([key]) => !['_token', '_method'].includes(key)));
        let saved = snapshot();
        let leaving = false;
        let busy = false;
        const submitButtons = [...form.querySelectorAll('[type="submit"]')];
        const submitStates = submitButtons.map(button => button.disabled);
        const status = form.querySelector('[data-draft-status]');
        const show = message => { if (status) status.textContent = message; };
        // A browser Back navigation may restore disabled controls from its cache.
        window.addEventListener('pageshow', () => {
            leaving = false;
            if (!busy) submitButtons.forEach((button, index) => button.disabled = submitStates[index]);
        });
        window.addEventListener('beforeunload', event => {
            if (!leaving && snapshot() !== saved) { event.preventDefault(); event.returnValue = ''; }
        });
        document.addEventListener('click', event => {
            if (event.defaultPrevented) return;
            const link = event.target.closest('a[href]');
            if (!link || link.target === '_blank' || link.hasAttribute('download') || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            const url = new URL(link.href, window.location.href);
            if (!['http:', 'https:'].includes(url.protocol) || (url.pathname === window.location.pathname && url.search === window.location.search && url.hash)) return;
            if (snapshot() !== saved && !window.confirm('You have unsaved changes. Leave this page without saving?')) {
                event.preventDefault();
            } else leaving = true;
        });
        form.addEventListener('submit', event => {
            if (busy) { event.preventDefault(); return; }
            // Only a valid submit can suppress the unsaved-change warning.
            if (!form.checkValidity()) return;
            leaving = true;
            form.querySelectorAll('[type="submit"]').forEach(button => { button.disabled = true; });
        });
        async function draftAction(discard) {
            if (busy) return;
            if (discard && !window.confirm('Discard the saved draft? This does not cancel or change a recorded request.')) return;
            busy = true;
            const sent = snapshot();
            const buttons = [...form.querySelectorAll('[data-save-draft],[data-discard-draft],[type="submit"]')];
            const states = buttons.map(button => button.disabled);
            let discardDisabled = null;
            buttons.forEach(button => button.disabled = true);
            try {
                const response = await fetch(discard ? form.dataset.discardUrl : form.dataset.draftUrl, {
                    method: discard ? 'DELETE' : 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                    body: discard ? undefined : new FormData(form),
                });
                if (!response.headers.get('Content-Type')?.includes('application/json')) {
                    throw new Error('Your session may have expired. Your entries are still on this page; sign in again before saving.');
                }
                const data = await response.json();
                if (!response.ok) {
                    const errors = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
                    throw new Error(errors || 'Draft could not be saved. Your entries are still on this page.');
                }
                if (discard) {
                    // Leave unsaved inputs intact; reloading clears only the restored draft.
                    show(data.message + ' Your current entries remain unsaved on this page.');
                    saved = '';
                    discardDisabled = true;
                } else {
                    saved = sent;
                    show(data.message + (snapshot() !== sent ? ' Newer changes are not saved yet.' : ' Expires ' + new Date(data.expires_at).toLocaleString('en-PH', {timeZone:'Asia/Manila',dateStyle:'medium',timeStyle:'short'}) + ' PHT.'));
                    discardDisabled = false;
                }
            } catch (error) {
                show(error.message || 'Connection failed. Your entries are still on this page.');
            } finally {
                busy = false;
                buttons.forEach((button, index) => button.disabled = states[index]);
                if (discardDisabled !== null) form.querySelector('[data-discard-draft]').disabled = discardDisabled;
            }
        }
        form.querySelector('[data-save-draft]')?.addEventListener('click', () => draftAction(false));
        form.querySelector('[data-discard-draft]')?.addEventListener('click', () => draftAction(true));
    });
    document.querySelectorAll('textarea[maxlength][data-character-count]').forEach(input => {
        const counter = document.getElementById(input.dataset.characterCount);
        const update = () => { if (counter) counter.textContent = input.value.length + ' / ' + input.maxLength; };
        input.addEventListener('input', update); update();
    });
    const language = document.getElementById('preferred_language');
    const summary = document.getElementById('review-language');
    if (language && summary) {
        const update = () => summary.textContent = language.value ? language.options[language.selectedIndex].text : 'Choose a language below';
        language.addEventListener('change', update); update();
    }
});
