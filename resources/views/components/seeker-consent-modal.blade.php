@once
@auth
@if(auth()->user()->role === 'seeker' && auth()->user()->helpSeeker)
@php
    $consentService=app(\App\Services\ConsentService::class);
    $needsSupportConsent=!$consentService->valid(auth()->user()->helpSeeker,'privacy_policy') || !$consentService->valid(auth()->user()->helpSeeker,'informed_consent');
@endphp
<style>
#seekerConsentDialog {position:fixed;inset:0;margin:auto;border:0;border-radius:20px;padding:0;width:min(720px,calc(100% - 32px));max-height:88dvh;color:#163b2d;background:white;box-shadow:0 24px 80px #0003;font-family:Inter,sans-serif;}
#seekerConsentDialog::backdrop {background:rgba(15,35,25,.5);backdrop-filter:blur(3px)}
#seekerConsentDialog .consent-header {padding:22px 24px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;gap:16px;align-items:center;}
#seekerConsentDialog h2 {font-size:20px;font-weight:700;margin:0;}
#seekerConsentDialog h3 {font-size:14px;font-weight:700;margin:18px 0 6px;}
#seekerConsentDialog p,#seekerConsentDialog li {font-size:13px;line-height:1.7;color:#64748b;}
#seekerConsentDialog .consent-body {padding:0 24px 20px;max-height:58dvh;overflow:auto;}
#seekerConsentDialog .consent-footer {display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;padding:16px 24px;border-top:1px solid #e5e7eb;background:#f8fbf9;}
#seekerConsentDialog .consent-action {border:1px solid #d1e5d9;background:white;color:#027039;border-radius:10px;padding:10px 16px;font-size:13px;font-weight:600;cursor:pointer;}
#seekerConsentDialog .consent-action.primary {background:#04a052;color:white;border-color:#04a052;}
#seekerConsentDialog .consent-check {display:flex;gap:10px;align-items:flex-start;padding:10px 0;font-size:13px;color:#374151;}
#seekerConsentDialog input {margin-top:3px;accent-color:#04a052;flex-shrink:0;}
#seekerConsentDialog button:focus-visible {outline:3px solid #86efac;outline-offset:3px;}
#seekerConsentDialog details.see-more {border-top:1px solid #eef2ef;margin-top:10px;padding-top:8px;}
#seekerConsentDialog details.see-more summary {font-size:12px;font-weight:600;color:#027039;cursor:pointer;list-style:none;display:flex;align-items:center;gap:6px;user-select:none;}
#seekerConsentDialog details.see-more summary::-webkit-details-marker {display:none;}
#seekerConsentDialog details.see-more summary::after {content:'\e01a';font-family:'Font Awesome 6 Free';font-weight:900;font-size:11px;transition:transform .2s;}
#seekerConsentDialog details.see-more[open] summary::after {transform:rotate(180deg);}
#seekerConsentDialog details.see-more .see-more-body {padding:10px 4px 4px;}
</style>
<dialog id="seekerConsentDialog" aria-labelledby="seekerConsentTitle">
<div class="consent-header"><div><h2 id="seekerConsentTitle">Terms and Privacy</h2><p>Understand your choices before requesting support.</p></div><button type="button" class="consent-action" data-close-seeker-consent aria-label="Close terms and privacy"><i class="fas fa-xmark" aria-hidden="true"></i></button></div>
<form id="seekerConsentForm" action="{{ route('seeker.consent.accept') }}" method="POST">@csrf
<div class="consent-body">
@include('partials.terms-text')
<hr class="my-4 border-gray-200">
@include('partials.privacy-text')
@if($needsSupportConsent)
<h3>Consent to participate</h3>
@foreach(['agree_privacy'=>'I have read and accept the Privacy Notice.','agree_terms'=>'I understand the terms and limits of peer support.','agree_emergency'=>'I understand how safety concerns and referrals are handled.','agree_consent'=>'I voluntarily agree to participate in peer support.'] as $name=>$label)
<label class="consent-check"><input type="checkbox" name="{{ $name }}" value="1" required><span>{{ $label }}</span></label>
@endforeach
@endif
<p id="seekerConsentError" role="alert" style="color:#b91c1c"></p>
</div>
<div class="consent-footer"><button type="button" class="consent-action" data-close-seeker-consent>{{ $needsSupportConsent ? 'Not now' : 'Close' }}</button>@if($needsSupportConsent)<button type="submit" class="consent-action primary">Agree and continue</button>@endif</div>
</form>
</dialog>
<script>
(() => {
 const dialog=document.getElementById('seekerConsentDialog');
 const form=document.getElementById('seekerConsentForm');
 let opener;
 const open=()=>{opener=document.activeElement;if(!dialog.open)dialog.showModal();};
 document.addEventListener('click',event=>{
  if(event.target.closest('[data-open-seeker-consent]')) {event.preventDefault();open();}
  if(event.target.closest('[data-close-seeker-consent]')) dialog.close();
 });
 dialog.addEventListener('close',()=>opener?.focus());
 dialog.addEventListener('click',event=>{if(event.target===dialog) {const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)dialog.close();}});
 form.addEventListener('submit',async event=>{
  event.preventDefault();const button=form.querySelector('[type=submit]');button.disabled=true;button.textContent='Saving?';
  try {const response=await fetch(form.action,{method:'POST',headers:{Accept:'application/json','X-CSRF-TOKEN':form.querySelector('[name=_token]').value},body:new FormData(form)});const result=await response.json();if(!response.ok)throw new Error(result.errors?Object.values(result.errors).flat().join(' '):result.message||'Unable to save. Please try again.');dialog.close();location.reload();}
  catch(error){document.getElementById('seekerConsentError').textContent=error.message;button.disabled=false;button.textContent='Agree and continue';}
 });
 @if(session('open_consent') || ($needsSupportConsent && request()->routeIs('request.screening')))
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',open);else open();
 @endif
})();
</script>
@endif
@endauth
@endonce
