(() => {
    const meta=document.querySelector('meta[name="compass-notices"]');
    if(!meta)return;
    const config=JSON.parse(meta.content),storageKey=`compass-notices:${config.user}`;
    let seen=null,pending=false,subscribed=false;
    try{const saved=sessionStorage.getItem(storageKey);if(saved!==null)seen=Number(saved)||0;}catch{}
    const safe=path=>typeof path==='string'&&path.startsWith('/')&&!path.startsWith('//')&&!path.includes('\\')&&!/[\u0000-\u0020]/.test(path)?path:config.center;
    const el=(tag,text)=>{const node=document.createElement(tag);if(text!==undefined)node.textContent=text;return node;};
    const root=el('div');root.className='compass-notices';
    const bell=el('button');bell.type='button';bell.className='compass-notice-bell';bell.setAttribute('aria-label','Notifications');bell.setAttribute('aria-expanded','false');bell.setAttribute('aria-controls','compass-notice-inbox');
    const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('fill','none');svg.setAttribute('stroke','currentColor');svg.setAttribute('stroke-width','1.8');svg.setAttribute('aria-hidden','true');
    const use=document.createElementNS('http://www.w3.org/2000/svg','use');use.setAttribute('href',(document.querySelector('meta[name="compass-icon-sprite"]')?.content||'/images/compass-icons.svg')+'#bell');svg.append(use);bell.append(svg);
    const count=el('span');count.className='compass-notice-count';count.hidden=true;bell.append(count);
    const inbox=el('section');inbox.id='compass-notice-inbox';inbox.className='compass-notice-dropdown';inbox.hidden=true;inbox.setAttribute('aria-label','Recent notifications');
    root.append(bell,inbox);document.body.append(root);
    const toasts=el('div');toasts.className='compass-notice-toasts';toasts.setAttribute('aria-live','polite');document.body.append(toasts);
    const close=()=>{inbox.hidden=true;bell.setAttribute('aria-expanded','false');};
    bell.addEventListener('click',()=>{inbox.hidden=!inbox.hidden;bell.setAttribute('aria-expanded',String(!inbox.hidden));if(!inbox.hidden)refresh();});
    document.addEventListener('click',event=>{if(!root.contains(event.target))close();});
    document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!inbox.hidden){close();bell.focus();}});
    function toast(notice){
        const card=el('article');card.className='compass-notice-toast'+(notice.type==='emergency'?' is-emergency':'');
        const dismiss=el('button','×');dismiss.type='button';dismiss.setAttribute('aria-label','Dismiss notification');dismiss.addEventListener('click',()=>card.remove());
        const link=el('a','Open');link.href=safe(notice.link);card.append(el('strong',notice.title),el('p',notice.message),link,dismiss);toasts.append(card);
        while(toasts.children.length>3)toasts.firstElementChild.remove();setTimeout(()=>card.remove(),notice.type==='emergency'?20000:10000);
    }
    async function refresh(){
        if(!subscribed&&window.Echo){try{window.Echo.private(`notifications.${config.user}`).listen('.NotificationsChanged',refresh);subscribed=true;}catch{}}
        if(pending||document.hidden||!navigator.onLine)return;pending=true;
        try{
            const response=await fetch(config.feed+(seen===null?'':'?after='+seen),{headers:{Accept:'application/json'},cache:'no-store'});if(!response.ok)return;
            const data=await response.json();count.textContent=data.count>99?'99+':String(data.count);count.hidden=!data.count;bell.setAttribute('aria-label',`${data.count} unread notifications`);
            document.querySelectorAll('#notifBadge,#notificationBadge').forEach(badge=>{badge.textContent=data.count;badge.style.display=data.count?'inline-flex':'none';});
            inbox.replaceChildren(el('h2','Notifications'));
            for(const notice of data.recent){const link=el('a');link.className='compass-notice-item'+(!notice.read?' is-unread':'');link.href=safe(notice.link);link.append(el('strong',notice.title),el('small',notice.message));inbox.append(link);}
            if(!data.recent.length)inbox.append(el('p','No notifications yet.'));
            const all=el('a','View all notifications');all.href=config.center;all.className='compass-notice-all';inbox.append(all);
            // Do not lose alerts while a confirmation or sensitive form dialog is open.
            if(document.querySelector('dialog[open]'))return;
            const fresh=data.fresh.filter(notice=>seen===null||notice.id>seen);
            if(fresh.length>3)toast({title:`${fresh.length} new notifications`,message:'Open Notifications to review all your updates.',link:config.center,type:fresh.some(n=>n.type==='emergency')?'emergency':'system'});
            else fresh.forEach(toast);
            seen=Math.max(seen||0,data.cursor||0);try{sessionStorage.setItem(storageKey,String(seen));}catch{}
        }catch{}finally{pending=false;}
    }
    refresh();setInterval(refresh,5000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)refresh();});window.addEventListener('online',refresh);
})();
