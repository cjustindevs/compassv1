/* Presentation only. No matching/status/network logic belongs in this file. */
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
