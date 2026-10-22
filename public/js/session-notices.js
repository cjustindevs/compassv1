(() => {
    const config = document.querySelector('[data-session-notices]');
    if (!config) return;
    const pending = new Set(), claimed = new Set();
    const session = config.dataset.sessionId;
    async function claim(event, scope = '') {
        const key = `${event}:${scope}`;
        if (document.visibilityState === 'hidden' || pending.has(key) || claimed.has(key)) return false;
        pending.add(key);
        try {
            const response = await fetch(config.dataset.claimUrl, {method:'POST', credentials:'same-origin', cache:'no-store',
                headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''},
                body:JSON.stringify({event})});
            if (!response.ok) return false;
            const result = await response.json();
            claimed.add(key);
            return result.show === true;
        } catch { return false; }
        finally { pending.delete(key); }
    }
    window.CompassSessionNotices = {claim};
    window.addEventListener('compass-session-state', async event => {
        const state = event.detail;
        if (String(state?.session_id) !== session || state.ended || !Number.isFinite(state.remaining_seconds)
            || state.remaining_seconds <= 0 || state.remaining_seconds > 300) return;
        if (!(await claim('ending_soon'))) return;
        const toast = document.createElement('aside');
        toast.className = 'session-warning-toast';
        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');
        const copy = document.createElement('div'), title = document.createElement('strong'), text = document.createElement('p');
        title.textContent = '5 minutes remaining'; text.textContent = 'Please begin wrapping up.';
        copy.append(title, text);
        const close = document.createElement('button'); close.type = 'button'; close.textContent = '×';
        close.setAttribute('aria-label', 'Dismiss session reminder'); close.addEventListener('click', () => toast.remove());
        toast.append(copy, close); document.body.append(toast);
        setTimeout(() => toast.remove(), 10000);
    });
})();
