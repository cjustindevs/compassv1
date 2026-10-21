(() => {
    function initialize() {
        const workspaces = [];
        document.querySelectorAll('[data-screening-toggle]').forEach(button => {
            if (button.dataset.workspaceReady === 'true') return;
            const panel = document.getElementById(button.getAttribute('aria-controls'));
            if (!panel) return;
            button.dataset.workspaceReady = 'true';
            const card = button.closest('[data-filter-target]');
            const label = button.querySelector('[data-review-toggle-label]');
            const closeButtons = panel.querySelectorAll('[data-screening-close]');
            function setOpen(open, focus = false) {
                panel.hidden = !open;
                button.setAttribute('aria-expanded', String(open));
                label.textContent = open ? 'Close review' : 'Open review';
                card?.classList.toggle('screening-review-open', open);
                if (focus) (open ? panel : button).focus();
            }
            setOpen(panel.dataset.initialOpen === 'true' || window.location.hash === '#' + card?.id);
            button.hidden = false;
            button.addEventListener('click', () => setOpen(panel.hidden, true));
            closeButtons.forEach(close => {
                close.hidden = false;
                close.addEventListener('click', () => setOpen(false, true));
            });
            workspaces.push({card, setOpen});
        });
        window.addEventListener('hashchange', () => {
            const selected = workspaces.find(item => '#' + item.card?.id === window.location.hash);
            if (!selected) return;
            // A notification link may target a record hidden by a previous filter.
            document.querySelector('[data-filter="all"]')?.click();
            selected.setOpen(true, true);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
    else initialize();
})();
