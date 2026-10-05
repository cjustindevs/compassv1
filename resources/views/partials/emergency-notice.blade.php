@if(auth()->check() && auth()->user()->is_active && in_array(auth()->user()->role,['adviser','moderator']))
<dialog id="staffEmergencyNotice" aria-labelledby="staffEmergencyTitle" style="position:fixed;inset:0;margin:auto;width:min(460px,calc(100vw - 32px));padding:24px;border:1px solid #e7c7c1;border-radius:16px;font-family:Inter,system-ui,sans-serif;max-height:85dvh;overflow:auto">
<h2 id="staffEmergencyTitle" style="font-size:20px;font-weight:700;color:#9c2921">Emergency requires attention</h2><p style="margin:16px 0">Open your emergency queue to review the case and coordinate support. This alert remains in your notification center.</p><p data-error role="alert"></p><div style="display:flex;gap:12px;flex-wrap:wrap"><button type="button" data-open style="background:#078749;color:white;padding:10px 16px;border-radius:8px">Open emergency queue</button><button type="button" data-dismiss style="padding:10px 16px;border:1px solid #cbd8d0;border-radius:8px">Dismiss</button></div></dialog>
<style>#staffEmergencyNotice::backdrop{background:rgba(20,30,25,.5)}#staffEmergencyNotice button:focus-visible{outline:2px solid #078749;outline-offset:3px}</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const dialog=document.getElementById('staffEmergencyNotice');let notice=null,busy=false;
 async function poll(){if(busy||dialog.open||document.querySelector('dialog[open]'))return;busy=true;try{const r=await fetch(@json(route('emergency-notice.next')),{headers:{Accept:'application/json'}});if(r.ok){notice=(await r.json()).notice;if(notice)dialog.showModal();}}finally{busy=false;}}
 async function dismiss(open){if(busy)return;busy=true;try{const r=await fetch(@json(route('emergency-notice.dismiss')),{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':@json(csrf_token())},body:JSON.stringify({reference:notice.reference})});if(!r.ok)throw Error();dialog.close();if(open)location.assign(notice.url);}catch(e){dialog.querySelector('[data-error]').textContent='Unable to save this action. Please try again.';}finally{busy=false;}}
 dialog.querySelector('[data-dismiss]').onclick=()=>dismiss(false);dialog.querySelector('[data-open]').onclick=()=>dismiss(true);dialog.addEventListener('cancel',e=>{e.preventDefault();dismiss(false);});poll().catch(()=>{});setInterval(()=>poll().catch(()=>{}),15000);
});
</script>
@endif
