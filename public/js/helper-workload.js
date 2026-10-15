(() => {
    const root = document.querySelector('[data-helper-workload]');
    if (!root) return;
    let busy = false, parameters = new URLSearchParams(location.search), generation = 0;
    const element = (tag, text) => { const node = document.createElement(tag); node.textContent = text; return node; };
    async function refresh() {
        if (busy || document.hidden || !navigator.onLine) return;
        busy = true; const requested = generation;
        try {
            const response = await fetch(root.dataset.helperWorkload + '?' + parameters, {headers:{Accept:'application/json'},cache:'no-store'});
            if (!response.ok || requested !== generation) return;
            const data = await response.json();
            if (requested !== generation) return;
            const body = root.querySelector('[data-workload-body]'); body.replaceChildren();
            for (const helper of data.helpers) {
                const row = element('tr', ''); row.dataset.helperId = helper.id;
                for (const value of [helper.name,helper.status,`${helper.used} / ${helper.limit}`,`${helper.assignments ? 'Assigned / reserved' : 'None'} (${helper.assignments} / 1)`,helper.remaining]) row.append(element('td',value));
                row.children[1].title = helper.reason || ''; body.append(row);
            }
            if (!data.helpers.length) { const row=element('tr',''),cell=element('td','No Helpers match these filters.'); cell.colSpan=5;row.append(cell);body.append(row); }
            root.querySelector('[data-workload-pages]').innerHTML = data.pagination;
            root.querySelector('[data-workload-summary]').textContent = data.summary;
        } catch { /* Preserve current filters and duty-form input during an outage. */ }
        finally { busy=false; if (requested !== generation) refresh(); }
    }
    root.querySelector('[data-workload-filter]').addEventListener('submit',event=>{
        event.preventDefault(); parameters=new URLSearchParams(new FormData(event.currentTarget));parameters.delete('workload_page');generation++;refresh();
    });
    root.querySelector('[data-workload-pages]').addEventListener('click',event=>{
        const link=event.target.closest('a');if(!link)return;event.preventDefault();parameters=new URL(link.href).searchParams;generation++;refresh();
    });
    refresh();setInterval(refresh,15000);window.addEventListener('online',refresh);document.addEventListener('visibilitychange',refresh);
})();
