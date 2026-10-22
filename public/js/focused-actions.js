(() => {
    const initialize = () => document.querySelectorAll('[data-focus-panel]').forEach((original, index) => {
        let panel = original;
        if (panel.dataset.focusReady) return;
        if (panel.tagName === 'DETAILS') {
            const section = document.createElement('section');
            for (const attribute of [...panel.attributes]) if (attribute.name !== 'open') section.setAttribute(attribute.name, attribute.value);
            while (panel.firstChild) section.append(panel.firstChild);
            panel.replaceWith(section); panel = section;
        }
        panel.dataset.focusReady = 'true';
        const button = document.createElement('button');
        button.type = 'button'; button.className = 'focus-panel-button';
        button.textContent = panel.dataset.focusLabel || 'View details';
        const dialog = document.createElement('dialog'); dialog.className = 'focus-panel-dialog';
        dialog.id = `focus-panel-${index}`; button.setAttribute('aria-controls', dialog.id);
        const header = document.createElement('header'), title = document.createElement('h2'), close = document.createElement('button');
        title.id = `${dialog.id}-title`; title.textContent = panel.dataset.focusTitle || button.textContent;
        dialog.setAttribute('aria-labelledby', title.id);
        close.type = 'button'; close.textContent = 'Close'; close.addEventListener('click', () => dialog.close());
        header.append(title, close); dialog.append(header);
        const summary = panel.querySelector(':scope > summary');
        if (summary) summary.remove();
        panel.removeAttribute('aria-labelledby');
        while (panel.firstChild) dialog.append(panel.firstChild);
        panel.replaceChildren(button, dialog);
        button.addEventListener('click', () => { if (!dialog.open) dialog.showModal(); });
        dialog.addEventListener('close', () => button.focus());
        if (panel.dataset.focusOpen === 'true') dialog.showModal();
    });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once:true});
    else initialize();
})();
