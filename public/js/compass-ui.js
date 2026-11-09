/* Presentation only. No matching/status/network logic belongs in this file. */

/* One top-right toast stack shared by flash messages, legacy page alerts and live notices,
   so toasts never cover composers, bottom navigation or each other. */
(() => {
    const stack = () => {
        let el = document.getElementById('compassToastStack');
        if (!el && document.body) {
            el = document.createElement('div');
            el.id = 'compassToastStack';
            el.className = 'compass-toast-stack';
            el.setAttribute('aria-live', 'polite');
            document.body.append(el);
        }
        if (el) place(el);
        return el;
    };
    // Sit just below the visible page header (bell, menu button), whose height varies by
    // layout and wraps on phones; fall back to the CSS default.
    const place = (el) => {
        const headers = [...document.querySelectorAll('.mobile-appbar, .top-bar, .page-header, .main-content > header, .main-content > :first-child, main > header')]
            .map((h) => h.getBoundingClientRect())
            .filter((r) => r.height > 0 && r.top < 120 && r.bottom > 0 && r.bottom < innerHeight / 3);
        const bottom = Math.max(0, ...headers.map((r) => r.bottom));
        el.style.top = bottom ? `${Math.round(bottom + 8)}px` : '';
    };
    const textOf = (el) => (el.textContent || '').replace(/\s+/g, ' ').trim();
    const duplicate = (text) => [...(stack()?.children || [])].some((child) => child.classList.contains('compass-toast') && child.dataset.text === text);
    const show = (message, type = 'success', duration = 6000) => {
        const text = String(message || '').trim();
        const host = stack();
        if (!text || !host || duplicate(text)) return null;
        const toast = document.createElement('div');
        toast.className = 'compass-toast';
        toast.dataset.type = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
        toast.dataset.text = text;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        const body = document.createElement('div');
        body.textContent = text;
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'compass-toast-close';
        close.setAttribute('aria-label', 'Dismiss');
        close.textContent = '×';
        close.addEventListener('click', () => toast.remove());
        toast.append(body, close);
        host.append(toast);
        while (host.querySelectorAll(':scope > .compass-toast').length > 3) host.querySelector(':scope > .compass-toast').remove();
        if (duration > 0 && type !== 'error') setTimeout(() => toast.remove(), duration);
        return toast;
    };
    window.CompassToast = { show, stack };

    const adopt = () => {
        // Server-rendered status toasts from partials.workflow-notice.
        document.querySelectorAll('[data-compass-toast]').forEach((el) => {
            show(el.dataset.message || textOf(el), el.dataset.type || 'success');
            el.remove();
        });
        // Legacy page alerts fixed to a screen corner (e.g. Professional pages).
        document.querySelectorAll('.flash-alert').forEach((el) => {
            if (el.closest('#compassToastStack') || getComputedStyle(el).position !== 'fixed') return;
            const text = textOf(el);
            if (!text || duplicate(text)) { el.remove(); return; }
            el.classList.add('compass-toast');
            el.dataset.text = text;
            el.dataset.type = el.classList.contains('error') ? 'error' : el.classList.contains('info') ? 'info' : 'success';
            stack()?.append(el);
        });
        // Live notification toasts share the same column.
        const live = document.querySelector('body > .compass-notice-toasts');
        if (live) stack()?.prepend(live);
    };
    const start = () => {
        adopt();
        new MutationObserver((records) => {
            if (records.some((r) => [...r.addedNodes].some((n) => n.nodeType === 1 && (n.matches?.('.compass-notice-toasts, [data-compass-toast]'))))) adopt();
        }).observe(document.body, { childList: true });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();

(() => {
    const prepare = () => {
        document.querySelectorAll('input[type="password"]').forEach(input=>{
            if(input.closest('.compass-password-field'))return;
            const wrapper=document.createElement('div');wrapper.className='compass-password-field';input.parentNode.insertBefore(wrapper,input);wrapper.append(input);
            const toggle=document.createElement('button');toggle.type='button';toggle.className='compass-password-toggle';toggle.setAttribute('aria-label','Show password');toggle.setAttribute('aria-pressed','false');
            const sprite=document.querySelector('meta[name="compass-icon-sprite"]')?.content||'/images/compass-icons.svg';
            toggle.innerHTML='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><use /></svg>';toggle.querySelector('use').setAttribute('href',sprite+'#eye');
            toggle.addEventListener('click',()=>{const show=input.type==='password';input.type=show?'text':'password';toggle.querySelector('use').setAttribute('href',sprite+(show?'#eye-off':'#eye'));toggle.setAttribute('aria-label',show?'Hide password':'Show password');toggle.setAttribute('aria-pressed',String(show));});wrapper.append(toggle);
        });
        try {
            const preferences = JSON.parse(document.querySelector('meta[name="compass-display-preferences"]')?.content || '{}');
            document.body.classList.toggle('high-contrast', preferences.high_contrast === true);
            document.body.classList.toggle('reduced-motion', preferences.reduced_motion === true);
            for (const size of ['small', 'medium', 'large']) document.body.classList.toggle('font-' + size, preferences.font_size === size);
        } catch (error) { /* Optional presentation preferences never block a workflow. */ }
        const main = document.querySelector('main, .main-content');
        if (main) {
            if (!main.id) main.id = 'compass-main-content';
            if (!main.hasAttribute('tabindex')) main.tabIndex = -1;
            const skip = document.createElement('a');
            skip.className = 'ui-skip-link';
            skip.href = '#' + main.id;
            skip.textContent = 'Skip to content';
            document.body.prepend(skip);
        }
        // Contain legacy tables that don't already have a responsive wrapper.
        document.querySelectorAll('table').forEach((table) => {
            if (table.closest('[data-admin-app], .ui-table-scroll, .table-container, .table-wrapper, .table-responsive, .overflow-x-auto, .table-wrap, .av-table-wrap, .mo-table-wrap, .rr-table-wrap')) return;
            const wrapper = document.createElement('div');
            wrapper.className = 'ui-table-scroll';
            wrapper.tabIndex = 0;
            wrapper.setAttribute('role', 'region');
            const title = table.getAttribute('aria-label') || table.querySelector('caption')?.textContent.trim() ||
                table.closest('section, .card, .av-panel, .mo-card')?.querySelector('h2, h3')?.textContent.trim() || 'Records';
            wrapper.setAttribute('aria-label', title);
            table.before(wrapper);
            wrapper.appendChild(table);
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', prepare);
    else prepare();
})();
