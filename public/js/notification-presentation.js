(() => {
    const meta = document.querySelector('meta[name="compass-notices"]');
    if (!meta || window.CompassPopupPresentation) return;
    const config = JSON.parse(meta.content), storageKey = `compass-popup-seen:${config.user}`;
    const memory = new Set();
    const pending = new Set();
    function read() {
        for (const name of ['localStorage', 'sessionStorage']) {
            try {
                const saved = JSON.parse(globalThis[name].getItem(storageKey) || '[]');
                if (Array.isArray(saved)) saved.filter(key => typeof key === 'string').forEach(key => memory.add(key));
            } catch { /* Private browsing can disable persistent storage. */ }
        }
    }
    function save() {
        for (const name of ['localStorage', 'sessionStorage']) {
            try { globalThis[name].setItem(storageKey, JSON.stringify([...memory])); } catch {}
        }
    }
    window.CompassPopupPresentation = {
        key(notice) { return notice.popup_key || (notice.type === 'emergency' && notice.case_key ? 'emergency:' + notice.case_key : notice.id != null ? 'notification:' + notice.id : null); },
        has(key) { read(); return key != null && memory.has(key); },
        claim(key) {
            if (key == null) return true;
            read(); if (memory.has(key)) return false;
            memory.add(key); save(); return true;
        },
        async claimRemote(notice) {
            const key = this.key(notice);
            if (this.has(key) || pending.has(key)) return false;
            const presentUrl = notice.present_url || (Number.isSafeInteger(Number(notice.id)) && Number(notice.id) > 0 ? `/notifications/${Number(notice.id)}/present` : null);
            if (!presentUrl) return this.claim(key);
            pending.add(key);
            try {
                if (!/^\/(?!\/)/.test(presentUrl) || /[\\\u0000-\u0020]/.test(presentUrl)) return false;
                const response = await fetch(presentUrl, {method:'POST', credentials:'same-origin', cache:'no-store',
                    headers:{Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''}});
                if (!response.ok) return false;
                const data = await response.json();
                this.claim(key);
                return data.present === true;
            } catch { return false; }
            finally { pending.delete(key); }
        },
    };
})();
