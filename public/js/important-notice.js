(() => {
    const meta = document.querySelector('meta[name="compass-notices"]');
    if (!meta) return;
    if (window.CompassImportantNotices) return;
    const config = JSON.parse(meta.content), key = `compass-important:${config.user}`;
    const presentation = window.CompassPopupPresentation;
    const popupKey = notice => presentation?.key(notice);
    let queue = [], current = null, saving = false, claiming = false;
    try { queue = JSON.parse(sessionStorage.getItem(key) || '[]'); } catch {}
    const save = () => { try { sessionStorage.setItem(key, JSON.stringify(queue.map(({claimed, ...notice}) => notice))); } catch {} };
    const node = (tag, text) => { const el = document.createElement(tag); if (text) el.textContent = text; return el; };
    const dialog = node('dialog'); dialog.id = 'compassImportantNotice'; dialog.className = 'compass-important-notice';
    dialog.setAttribute('aria-labelledby', 'compassImportantTitle');
    const title = node('h2'); title.id = 'compassImportantTitle';
    const message = node('p'), error = node('p'); error.setAttribute('role', 'alert');
    const actions = node('div'), open = node('button', 'Open'), acknowledge = node('button', 'Later');
    open.type = acknowledge.type = 'button'; actions.append(open, acknowledge); dialog.append(title, message, error, actions); document.body.append(dialog);
    const safe = path => typeof path === 'string' && /^\/(?!\/)/.test(path) && !/[\\\u0000-\u0020]/.test(path) ? path : config.center;
    async function show() {
        if (dialog.open || saving || claiming || document.hidden || !queue.length || document.querySelector('dialog[open]') || document.querySelector('.modal-overlay.active')) return;
        while (queue.length && !queue[0].claimed && presentation?.has(popupKey(queue[0]))) { queue.shift(); save(); }
        if (!queue.length) return;
        const candidate = queue[0];
        claiming = true;
        const allowed = candidate.claimed || (presentation?.claimRemote ? await presentation.claimRemote(candidate) : !presentation || presentation.claim(popupKey(candidate)));
        claiming = false;
        if (!allowed) { queue = queue.filter(item => item.id !== candidate.id); save(); return; }
        if (!queue.some(item => item.id === candidate.id)) return;
        candidate.claimed = true;
        if (document.hidden || document.querySelector('dialog[open]') || document.querySelector('.modal-overlay.active')) {
            // The delivery belongs to this page; wait for the sensitive form to close.
            candidate.claimed = true; return;
        }
        current = candidate; title.textContent = current.title; message.textContent = current.message; error.textContent = ''; dialog.showModal();
    }
    async function finish(navigate) {
        if (!current || saving) return;
        saving = true;
        try {
            if (navigate) {
                const response = await fetch(`/notifications/${current.id}/read`, {method: 'POST', headers: {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''}});
                if (!response.ok) throw Error();
            }
            const url = safe(current.link); queue = queue.filter(item => item.id !== current.id); save(); current = null; dialog.close();
            if (navigate) location.assign(url);
        } catch { error.textContent = 'Unable to open this notification. Please try again.'; }
        finally { saving = false; }
    }
    window.CompassImportantNotices = {
        suspend() { if (dialog.open) { queue = queue.filter(item => item.id !== current?.id); save(); current = null; dialog.close(); } },
        reconcileUnread(ids) { queue = queue.filter(item => ids.includes(item.id)); save(); if (current && !queue.some(item => item.id === current.id)) { current = null; dialog.close(); } },
        enqueue(notice) { if (presentation?.has(popupKey(notice))) return; if (!queue.some(item => item.id === notice.id)) { queue.push(notice); save(); } show(); },
        reconcile(activeIds) { queue = queue.filter(item => item.type !== 'emergency' || activeIds.includes(item.id)); save(); if (current && !queue.some(item => item.id === current.id)) { current = null; dialog.close(); } },
    };
    open.onclick = () => finish(true); acknowledge.onclick = () => finish(false);
    dialog.addEventListener('cancel', event => { event.preventDefault(); finish(false); });
    setInterval(show, 1000); show();
})();
