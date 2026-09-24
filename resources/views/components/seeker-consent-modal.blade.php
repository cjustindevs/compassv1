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
<h3>Terms of support</h3><p>COMPASS connects you with trained peer helpers under adviser supervision. Participation is voluntary. Peer support is not a diagnosis, therapy or professional treatment. You may stop a session at any time.</p>
<p>Sessions last up to 90 minutes. {{ app(\App\Services\OperatingHoursService::class)->message() }} If you need urgent help, emergency resources remain available. COMPASS cannot guarantee an immediate emergency response.</p>
<details class="see-more"><summary>See more</summary><div class="see-more-body">
<p>You must be a registered member of our community at least 18 years old (or an authorized participant) to use peer support. Keep contact details and personal identifiers out of chat unless a formal referral begins. Do not share another person's personal information, and do not use the platform for any unlawful, commercial or fraudulent purpose.</p>
<p>Peer helpers follow a code of conduct and confidentiality rules. They may pause or end a session if either of you is unsafe, or if the session is outside their scope. You can request a different helper by declining the match. Repeated misuse of the service may limit access.</p>
<p>Peer support and self-help tools are not a substitute for professional care. If you are considering harm, please contact a crisis hotline or emergency services right away.</p>
</div></details>
<h3>Privacy Notice</h3><p>Your alias is shown to your peer helper. We keep your account details, support answers, messages and feedback to provide support, supervise sessions and maintain records. Authorized staff can access records needed for their responsibilities.</p>
<p>Chat messages are stored on the server and are not end-to-end encrypted. Voice calls, recording and automatic transcription are currently unavailable. Optional contact details for referrals are kept separately in the Identity Vault.</p>
<details class="see-more"><summary>See more</summary><div class="see-more-body">
<p>When you create a request we collect: your account profile (name and university of the institution, if provided), your screening answers, the concern you selected, your session messages, your satisfaction ratings, session documentation written by your helper (not visible to you), and referral records.</p>
<p>This information is processed to match you with a supervised peer helper, operate the live session, keep a record of support for safety and institutional recordkeeping, and improve the service. Authorized personnel (program coordinators, advisers, moderators, system administrators and the operators' staff) can access only the records they need for their role, and every access is logged.</p>
<p>We do not sell your personal information. Your optional contact details for referrals are stored separately in the Identity Vault and are released only through an authorized referral or a restricted emergency procedure. Records may be retained for as long as authorized for safety and legal recordkeeping even after you withdraw consent.</p>
</div></details>
<h3>Safety and referrals</h3><p>Safety concerns may be reviewed by authorized advisers. A professional referral requires a separate decision from you. Identity information is released only through an authorized referral or a restricted emergency procedure.</p>
<details class="see-more"><summary>See more</summary><div class="see-more-body">
<p>If your screening or session indicates a safety concern, an authorized adviser may review the record and, where needed, request a professional referral. A program coordinator approves the referral and, if you agree, releases the minimum contact details from the Identity Vault to a qualified professional. The helper's account stays pseudonymous so that your helper cannot be contacted directly by a professional.</p>
<p>In an emergency, a restricted procedure may release identifying information to emergency authorities to protect life, and this is logged. You can withdraw from a pending referral at any time. Withdrawal does not erase records that were already lawfully retained for safety and institutional obligations.</p>
<p>If you need immediate help now, view the Emergency page for crisis hotlines and 911.</p>
</div></details>
<h3>Incident reporting (Appendix T)</h3><p>You can report a concern about a session, a helper, or your own conduct. Reports are reviewed under the community standards procedures and are kept confidential on a need-to-know basis.</p>
<details class="see-more"><summary>See more</summary><div class="see-more-body">
<p>To report a concern, tell a helper or adviser, or use the report contact provided by support. Provide a short description of what happened, when it happened, and who was involved. Do not include anyone else's personal information.</p>
<p>Authorized staff review the report, contact you through your account alias, and take proportionate action, which may include feedback, retraining, suspending or removing a helper, or closing a request. Retaliation against a person who reports a concern is not allowed. Reports and their resolution are logged; your identity is visible only to the staff who need it, and conversation content is reviewed only with an authorized access grant.</p>
</div></details>
<h3>Your choices</h3><p>You can review or withdraw consent in Privacy and Consent. Withdrawal stops future consent-dependent support and closes current requests. Existing records may be retained for authorized safety and institutional recordkeeping. You can still use self-help and emergency resources.</p>
<details class="see-more"><summary>See more</summary><div class="see-more-body">
<p>In Privacy and Consent you can: review the current Terms and Privacy, withdraw your support consent, manage your referral contact details in the Identity Vault, and close any open request. Consent can always be withdrawn before a session begins.</p>
<p>If you have questions about how your data is handled, contact the program operators. You may also request a copy of the records we hold about you; access is provided to authorized people on your behalf. We recommend reviewing these pages whenever you are referred, because professional referrals involve releasing your optional contact details with your separate consent.</p>
</div></details>
@if($needsSupportConsent)
<h3>Consent to participate</h3>
@foreach(['agree_privacy'=>'I have read and accept the Privacy Notice.','agree_terms'=>'I understand the terms and limits of peer support.','agree_emergency'=>'I understand how safety concerns and referrals are handled.','agree_consent'=>'I voluntarily agree to participate in peer support.'] as $name=>$label)
<label class="consent-check"><input type="checkbox" name="{{ $name }}" value="1" required><span>{{ $label }}</span></label>
@endforeach
@else<p>You have accepted the current Terms and Privacy. You can manage your choices in Privacy and Consent.</p>@endif
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
