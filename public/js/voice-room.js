(() => {
    const panel = document.getElementById('voiceMessages');
    const toggle = document.querySelector('[data-messages-toggle]');
    if (!panel || !toggle) return;
    const count = document.querySelector('[data-message-count]');
    let unread = 0;
    function messages(open) {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (open) {
            unread = 0; if (count) count.hidden = true;
            const list = document.getElementById('chatMessages'); list.scrollTop = list.scrollHeight;
            document.getElementById('messageInput').focus();
        } else toggle.focus();
    }
    toggle.addEventListener('click', () => messages(panel.hidden));
    document.querySelector('[data-messages-close]').addEventListener('click', () => messages(false));
    panel.addEventListener('keydown', event => { if (event.key === 'Escape') messages(false); });
    // Opening messages changes visibility only; it never recreates the audio client.
    let initialMessages = true;
    const messageObserver = new MutationObserver(records => {
        if (initialMessages) {
            if (document.getElementById('chatMessages').dataset.historyLoaded) initialMessages = false;
            return;
        }
        if (!panel.hidden || !count) return;
        const nodes = records.flatMap(record => [...record.addedNodes]).filter(node => node.matches?.('.message'));
        for (const node of nodes) if (node.dataset.senderId !== document.getElementById('currentUserId').value) unread++;
        if (unread) { count.textContent = String(unread); count.hidden = false; }
    });
    messageObserver.observe(document.getElementById('chatMessages'), {childList:true});
    const form = document.querySelector('[data-voice-checkin]');
    form?.addEventListener('submit', async event => {
        event.preventDefault();
        const status = form.querySelector('[data-checkin-status]'), button = form.querySelector('button');
        if (button.disabled) return;
        button.disabled = true; status.textContent = '';
        try {
            const response = await fetch(form.action, {method:'POST', credentials:'same-origin',
                headers:{Accept:'application/json', 'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body:new FormData(form)});
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || data.message || 'Unable to save your answer.');
            status.textContent = 'Thank you. Your answer was saved.';
        } catch (error) { status.textContent = error.message; button.disabled = false; }
    });
})();
