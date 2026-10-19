(() => {
    const config=document.querySelector('[data-queue-helper-options]');if(!config)return;
    let busy=false;
    async function refresh(){
        if(busy||document.hidden||!navigator.onLine)return;busy=true;
        try{
            const forms=[...document.querySelectorAll('.assign-form[data-queue-id],.reassign-form[data-queue-id]')].filter(form=>form.dataset.submitting!=='1');
            const ids=[...new Set(forms.map(form=>form.dataset.queueId))];if(!ids.length)return;
            const query=new URLSearchParams();ids.forEach(id=>query.append('queue_ids[]',id));
            const response=await fetch(config.dataset.queueHelperOptions+'?'+query,{headers:{Accept:'application/json'},cache:'no-store'});if(!response.ok)return;
            const data=await response.json();
            for(const form of forms){
                const select=form.querySelector('select[name="helper_id"]'),note=form.querySelector('[data-helper-reason]');if(!select||document.activeElement===select)continue;
                const request=data.requests.find(row=>String(row.id)===form.dataset.queueId);
                const choices=request?.helpers||[],selected=select.value;
                select.replaceChildren(new Option(choices.length?'Select Helper...':'No eligible Helper',''));
                choices.forEach(helper=>select.append(new Option(helper.label,String(helper.id))));
                select.value=choices.some(row=>String(row.id)===selected)?selected:'';
                const button=form.querySelector('button[type="submit"]');if(button)button.disabled=!choices.length;
                if(note)note.textContent=request?.reason||'This request changed or was assigned. Refresh the queue to see its current status.';
                const list=form.querySelector('[data-unavailable-list]');
                if(list?.replaceChildren){list.replaceChildren();for(const row of request?.unavailable||[]){const item=document.createElement('li');item.textContent=row.name+' — '+row.reason+(row.readiness_passed?' Readiness passed; another requirement blocks assignment.':'');list.append(item);}}
            }
        }catch{}finally{busy=false;}
    }
    refresh();setInterval(refresh,5000);window.addEventListener('online',refresh);document.addEventListener('visibilitychange',refresh);
})();
