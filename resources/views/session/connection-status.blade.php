@isset($session)
@include('partials.session-notices')
<div id="connectionNotice" hidden role="status">
    <span data-connection-text></span>
    @if(auth()->user()->role === 'seeker')
        <button type="button" id="reviewConnection">View connection status</button>
    @endif
</div>
@if(auth()->user()->role === 'seeker')
<dialog id="connectionDialog" class="cm-dialog" data-tone="primary" aria-labelledby="connectionHeading" aria-describedby="connectionExplanation">
    <div class="cm-scroll">
        <div class="cm-head">
            <span class="cm-icon" aria-hidden="true"><x-ui-icon name="refresh" /></span>
            <div>
                <h2 id="connectionHeading" class="cm-title">Your Helper is reconnecting</h2>
                <p id="connectionExplanation" class="cm-text" data-connection-text aria-live="polite"></p>
                <p class="cm-text">You can stay on this page. Your existing conversation is saved, and your session timer continues. You can reopen this notice from your session.</p>
            </div>
        </div>
    </div>
    <p class="cm-error" data-connection-error role="alert"></p>
    <div class="cm-actions">
        <button type="button" class="cm-btn cm-btn--secondary" id="closeConnection">Back to session</button>
        <div class="cm-actions-group" data-connection-actions hidden>
            <button type="button" class="cm-btn cm-btn--secondary" data-choice="wait">Keep waiting</button>
            <button type="button" class="cm-btn cm-btn--primary" data-choice="replace">Find another Helper</button>
        </div>
    </div>
</dialog>
@endif
<style>
#connectionNotice{padding:12px 16px;background:#f0faf4;border:1px solid #d5e6cc;border-radius:12px;margin:8px 12px;font:13px/1.5 Inter,system-ui,sans-serif}
#connectionNotice button{font:600 13px Inter,system-ui,sans-serif;padding:10px 14px;min-height:42px;border:1px solid #c5d8bc;border-radius:9px;background:#fff;color:#326618;cursor:pointer}
#connectionNotice button:focus-visible{outline:2px solid #326618;outline-offset:3px}
#connectionNotice:not([hidden]){display:flex;align-items:center;justify-content:space-between;gap:14px}#connectionNotice button{flex-shrink:0}
@media(max-width:540px){#connectionNotice:not([hidden]){align-items:stretch;flex-direction:column}}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const helper=@json(auth()->user()->role==='helper'), box=document.getElementById('connectionNotice');
 const dialog=document.getElementById('connectionDialog');
 const anchor=document.querySelector('.chat-header, .room-header'); if(anchor)anchor.insertAdjacentElement('afterend',box);
 const headers={'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token())};
 let polling=false, submitting=false, absenceClaimed=null;
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
 const text=ended?'This conversation has ended. If you still need support, you can start a new request.':recovered?'Your Helper is connected again. You can continue your conversation.':data.status==='offered' ? 'A replacement Helper is reviewing the offer. We will open the new chat when they accept.':data.status==='requested' ? 'Your Moderator has been notified that you would like another Helper. Please wait while they review availability.':data.status==='waiting' ? 'You chose to keep waiting. We will let you know when your Helper reconnects.':data.can_choose?'Your Helper has not reconnected yet. You can keep waiting or ask the Moderator to find another available Helper.':'We have lost contact with your Helper and notified the Moderator. Please allow two minutes for reconnection before choosing another Helper.';
 box.querySelector('[data-connection-text]').textContent=text;
 if(dialog){
 dialog.querySelector('[data-connection-text]').textContent=text;
 dialog.querySelector('h2').textContent=ended?'This conversation has ended':recovered?'Your Helper is back':data.status==='offered' ? 'Replacement offer sent':data.status==='requested' ? 'Replacement requested':'Your Helper is reconnecting';
 dialog.querySelector('[data-connection-actions]').hidden=!interrupted||!data.can_choose;
 dialog.querySelector('[data-choice=replace]').hidden=['requested','offered'].includes(data.status);
 dialog.querySelector('[data-choice=wait]').textContent=['requested','offered'].includes(data.status)?'Cancel replacement and keep waiting':'Keep waiting';
 // Prolonged absence is claimed on the server once per interruption and Seeker.
 if(interrupted && active && data.show_absence_notice && absenceClaimed!==data.incident_id){
   const notices=window.CompassSessionNotices;
   if(notices && await notices.claim('helper_disconnected',data.incident_id)){absenceClaimed=data.incident_id;openDialog();}
 }
 if(interrupted && data.absent_minutes)box.querySelector('[data-connection-text]').textContent=`Helper disconnected for ${data.absent_minutes} minutes. ${text}`;
 if((ended||!interrupted)&&!recovered&&dialog.open)dialog.close();
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
