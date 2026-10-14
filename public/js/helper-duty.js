(() => {
    const root=document.querySelector('[data-duty-summary]'); if(!root)return;
    let pending=false;
    const text=(tag,value)=>{const node=document.createElement(tag);node.textContent=value;return node;};
    function render(target,duties,empty,today){
        target.replaceChildren();
        if(!duties.length){target.append(text('p',empty));return;}
        for(const duty of duties){const card=text('article','');card.className='helper-duty-item';card.append(text('strong',today?duty.status:duty.date),text('p',(today?duty.date+' · ':'')+duty.time));if(duty.notes)card.append(text('small',duty.notes));target.append(card);}
    }
    async function refresh(){
        if(pending||document.hidden||!navigator.onLine)return;pending=true;
        try{const response=await fetch(root.dataset.dutyUrl,{headers:{Accept:'application/json'},cache:'no-store'});if(!response.ok)return;
            const {duties}=await response.json();if(!duties)return;
            render(root.querySelector('[data-duty-today]'),duties.today,'No duty scheduled today. Contact your Moderator.',true);
            render(root.querySelector('[data-duty-upcoming]'),duties.upcoming,'No upcoming duty scheduled.',false);
            root.querySelector('[data-duty-note]').textContent=duties.testing?'Testing: today’s duty applies at any clock time. Passed readiness is still required.':'Complete readiness before your shift. Availability depends on your duty, readiness, and current assignment.';
        }catch{}finally{pending=false;}
    }
    refresh();setInterval(refresh,15000);document.addEventListener('visibilitychange',refresh);window.addEventListener('online',refresh);
})();
