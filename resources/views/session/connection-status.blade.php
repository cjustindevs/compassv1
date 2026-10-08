@isset($session)
<div id="connectionNotice" hidden role="status">
    <span data-connection-text></span>
    @if(auth()->user()->role === 'seeker')
        <button type="button" id="reviewConnection">View connection status</button>
    @endif
</div>
@if(auth()->user()->role === 'seeker')
<dialog id="connectionDialog" aria-labelledby="connectionHeading" aria-describedby="connectionExplanation">
    <div class="connection-heading"><h2 id="connectionHeading">Your Helper is reconnecting</h2></div>
    <p id="connectionExplanation" data-connection-text aria-live="polite"></p>
    <p class="connection-muted">You can stay on this page. Your existing conversation is saved, and your session timer continues.</p>
    <div data-connection-actions hidden>
        <button type="button" data-choice="replace" class="connection-primary">Find another Helper</button>
        <button type="button" data-choice="wait">Keep waiting</button>
    </div>
    <p data-connection-error role="alert"></p>
    <div class="connection-footer"><span class="connection-muted">You can reopen this notice from chat.</span><button type="button" id="closeConnection">Back to chat</button></div>
</dialog>
@endif
<style>
#connectionNotice{padding:12px 16px;background:#f0faf4;border:1px solid #cce6d7;border-radius:12px;margin:8px 12px;font:13px/1.5 Inter,system-ui,sans-serif}
#connectionDialog{width:min(480px,calc(100vw - 32px));max-height:calc(100dvh - 40px);overflow:auto;padding:24px;border:1px solid #dce8e0;border-radius:18px;margin:auto;background:#fff;color:#203c30;font:14px/1.6 Inter,system-ui,sans-serif}
#connectionDialog::backdrop{background:rgba(15,35,25,.45)}
#connectionDialog .connection-heading>i{display:grid;place-items:center;flex:0 0 42px;height:42px;background:#eaf7ef;border-radius:12px}#connectionDialog .connection-heading{display:flex;align-items:center;gap:12px;color:#087642}
#connectionDialog h2{font-size:19px;line-height:1.4;margin:0}
#connectionDialog p{margin:14px 0}#connectionDialog .connection-muted{color:#62736a;font-size:13px}
#connectionDialog [data-connection-actions]:not([hidden]){display:flex;flex-wrap:wrap;gap:8px}
#connectionDialog .connection-footer{border-top:1px solid #e0e9e3;padding-top:14px;display:flex;justify-content:space-between;align-items:center;gap:12px}
#connectionDialog button,#connectionNotice button{font:600 13px Inter,system-ui,sans-serif;padding:10px 14px;min-height:42px;border:1px solid #bcd8c7;border-radius:9px;background:#fff;color:#087642;cursor:pointer}
#connectionDialog .connection-primary{background:#07964e;color:white;border-color:#07964e}
#connectionDialog button:focus-visible,#connectionNotice button:focus-visible{outline:2px solid #087642;outline-offset:3px}
#connectionDialog button:disabled{opacity:.55;cursor:wait}#connectionDialog [data-connection-error]{color:#b42318}
#connectionNotice:not([hidden]){display:flex;align-items:center;justify-content:space-between;gap:14px}#connectionNotice button{flex-shrink:0}
#connectionDialog .connection-muted{background:#f6f9f7;padding:12px;border-radius:10px}#connectionDialog .connection-footer .connection-muted{background:none;padding:0;font-size:12px}
#connectionDialog [data-connection-error]:empty{display:none}
@media(max-width:540px){#connectionDialog{padding:20px}#connectionDialog [data-connection-actions]{flex-direction:column}#connectionDialog [data-connection-actions] button{width:100%}#connectionNotice:not([hidden]){align-items:stretch;flex-direction:column}#connectionDialog .connection-footer{align-items:flex-start;flex-direction:column}#connectionDialog .connection-footer button{width:100%}}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const helper=@json(auth()->user()->role==='helper'), box=document.getElementById('connectionNotice');
 const dialog=document.getElementById('connectionDialog');
 const anchor=document.querySelector('.chat-header, .room-header'); if(anchor)anchor.insertAdjacentElement('afterend',box);
 const headers={'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token())};
 let lastState=null, polling=false, submitting=false;
 const openDialog=()=>{if(dialog && !dialog.open)dialog.showModal();};
 document.getElementById('reviewConnection')?.addEventListener('click',openDialog);
 document.getElementById('closeConnection')?.addEventListener('click',()=>dialog.close());
 async function tick(){if(polling)return;polling=true;try{
 if(helper)await fetch(@json(route('reconnections.heartbeat',$session)),{method:'POST',headers});
 const response=await fetch(@json(route('reconnections.state',$session)),{headers:{Accept:'application/json'}});if(!response.ok)return;const data=await response.json();
 if(data.transferred){location.assign(helper?'/helper/cases':'/session/chat');return;}
 const active=Boolean(data.active);
 const interrupted=['interrupted','waiting','requested','offered'].includes(data.status);
 const ended=!active&&(interrupted||data.status==='reconnected');
 const recovered=data.status==='reconnected'&&active;
 box.hidden=!interrupted&&!recovered&&!ended;
 const text=ended?'This conversation has ended. If you still need support, you can start a new request.':recovered?'Your Helper is connected again. You can continue your conversation.':data.status==='offered'?'A replacement Helper is reviewing the offer. We will open the new chat when they accept.':data.status==='requested'?'Your Moderator has been notified that you would like another Helper. Please wait while they review availability.':data.status==='waiting'?'You chose to keep waiting. We will let you know when your Helper reconnects.':data.can_choose?'Your Helper has not reconnected yet. You can keep waiting or ask the Moderator to find another available Helper.':'We have lost contact with your Helper and notified the Moderator. Please allow two minutes for reconnection before choosing another Helper.';
 box.querySelector('[data-connection-text]').textContent=text;
 if(dialog){
 dialog.querySelector('[data-connection-text]').textContent=text;
 dialog.querySelector('h2').textContent=ended?'This conversation has ended':recovered?'Your Helper is back':data.status==='offered'?'Replacement offer sent':data.status==='requested'?'Replacement requested':'Your Helper is reconnecting';
 dialog.querySelector('[data-connection-actions]').hidden=!interrupted||!data.can_choose;
 dialog.querySelector('[data-choice=replace]').hidden=['requested','offered'].includes(data.status);
 dialog.querySelector('[data-choice=wait]').textContent=['requested','offered'].includes(data.status)?'Cancel replacement and keep waiting':'Keep waiting';
 const state=String(data.status)+':'+Boolean(data.can_choose)+':'+Boolean(active);
 // Open once per meaningful change, allowing dismissal without repeated polling popups.
 if(state!==lastState && !ended && (interrupted || (recovered && lastState!==null)))openDialog();
 if((ended||!interrupted)&&!recovered&&dialog.open)dialog.close();
 lastState=state;
 }
 }catch(e){}finally{polling=false;}}
 dialog?.querySelectorAll('[data-choice]').forEach(button=>button.addEventListener('click',async()=>{
 if(submitting)return;submitting=true;
 const buttons=dialog.querySelectorAll('[data-choice]');buttons.forEach(b=>b.disabled=true);
 const error=dialog.querySelector('[data-connection-error]');error.textContent='';
 try{const response=await fetch(@json(route('reconnections.choose',$session)),{method:'POST',headers,body:JSON.stringify({decision:button.dataset.choice})});
 if(!response.ok){const data=await response.json();error.textContent=Object.values(data.errors||{}).flat().join(' ')||'Please refresh and try again.';}
 await tick();
 }catch(e){error.textContent='Connection unavailable. Please try again.';}finally{submitting=false;buttons.forEach(b=>b.disabled=false);}
 }));
 tick();setInterval(tick,15000);
});
</script>
@endisset
