(() => {
    const dialog = document.getElementById('staffEmergencyNotice');
    if (!dialog) return;
    if (window.CompassStaffEmergencyInitialized) return;
    window.CompassStaffEmergencyInitialized = true;
    const config = JSON.parse(document.getElementById('staffEmergencyConfig').content);
    const presentation = window.CompassPopupPresentation;
    const popupKey = item => item ? presentation?.key({...item, type:'emergency'}) : null;
    let notice = null, busy = false, ownedKey = null;
    const otherDialog = () => [...document.querySelectorAll('dialog[open]')].some(node => node !== dialog && node.id !== 'compassImportantNotice') || !!document.querySelector('.modal-overlay.active');
    async function current() {
        const url = config.next + (notice ? '?current=' + encodeURIComponent(notice.reference) : '');
        const response = await fetch(url, {headers: {Accept: 'application/json'}, cache: 'no-store'});
        if (!response.ok) throw Error();
        return (await response.json()).notice;
    }
    async function render(next) {
        if (next && otherDialog()) return;
        const sameVisibleCase = notice && next && ownedKey === popupKey(next);
        const allowed = !next || sameVisibleCase || !presentation || (presentation.claimRemote ? await presentation.claimRemote({...next, type:'emergency'}) : presentation.claim(popupKey(next)));
        if (!allowed) {
            notice = null; if (dialog.open) dialog.close(); return;
        }
        notice = next;
        ownedKey = next ? popupKey(next) : null;
        if (!notice) { if (dialog.open) dialog.close(); return; }
        dialog.querySelector('#staffEmergencyTitle').textContent = notice.title;
        dialog.querySelector('p').textContent = notice.message;
        dialog.querySelector('[data-open]').textContent = config.openLabel;
        dialog.querySelector('[data-error]').textContent = '';
        if (!dialog.open && !document.hidden && !otherDialog()) { window.CompassImportantNotices?.suspend(); dialog.showModal(); }
    }
    async function retire(item) {
        const response = await fetch(config.dismiss, {method:'POST',
            headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':config.csrf},
            body:JSON.stringify({reference:item.reference})});
        if (!response.ok) throw Error();
    }
    async function poll() {
        if (busy || document.hidden || !navigator.onLine || otherDialog()) return;
        busy = true;
        try {
            const next = await current();
            const alreadyOpen = notice && next && ownedKey === popupKey(next);
            if (next && !alreadyOpen && presentation?.has(popupKey(next))) {
                // Only retire its popup; the case and unread notification stay intact.
                await retire(next); await render(null);
            } else await render(next);
        } catch (_) { /* Keep the current UI during a temporary outage. */ }
        finally { busy = false; }
    }
    async function dismiss(open) {
        if (busy || !notice) return;
        busy = true;
        try {
            const previousId = notice.id;
            const next = await current();
            if (!next || next.id !== previousId) { await render(next); return; }
            notice = next;
            const response = await fetch(config.dismiss, {method: 'POST',
                headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': config.csrf},
                body: JSON.stringify({reference: notice.reference})});
            if (!response.ok) throw Error();
            const url = notice.url;
            notice = null;
            dialog.close();
            if (open) location.assign(url);
        } catch (_) { dialog.querySelector('[data-error]').textContent = 'Unable to save this action. Please try again.'; }
        finally { busy = false; }
    }
    dialog.querySelector('[data-dismiss]').onclick = () => dismiss(false);
    dialog.querySelector('[data-open]').onclick = () => dismiss(true);
    dialog.addEventListener('cancel', event => { event.preventDefault(); dismiss(false); });
    poll(); setInterval(poll, 2000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    window.addEventListener('online', poll);
})();
