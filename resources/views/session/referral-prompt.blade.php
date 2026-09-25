@isset($session)
<style>
#referralConsentDialog {
    border: 0;
    border-radius: 16px;
    padding: 24px;
    width: min(480px, calc(100vw - 24px));
    max-width: 480px;
    position: fixed;
    inset: 0;
    margin: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,.25);
}
#referralConsentDialog::backdrop { background: rgba(15, 23, 42, .45); backdrop-filter: blur(2px); }
#referralConsentDialog:not([open]) { display: none; }
@media (max-width: 480px) { #referralConsentDialog { max-height: 86dvh; } }
</style>
<dialog id="referralConsentDialog">
    <h2 style="font-size:20px;font-weight:700;color:#1f2937">Professional support</h2>
    <p style="font-size:13px;color:#6b7280;margin:4px 0 14px">Please review what this means for your privacy before deciding.</p>
    <div id="referralConsentText" style="margin:10px 0;font-size:14px;line-height:1.6;color:#374151"></div>

    <div id="referralConsentTerms" style="background:#F8FBF9;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px;margin:12px 0;font-size:13px;line-height:1.6;color:#374151">
        <p style="font-weight:600;margin-bottom:6px">Referral consent</p>
        <ul style="margin:0;padding-left:18px">
            <li>Your helper recommended connecting you with a psychology professional for follow-up care.</li>
            <li><strong>Contact details are voluntary</strong> and are shared only so a professional can schedule your session. No identity is released without your consent.</li>
            <li>Only the adviser and assigned professional can access the referral information needed to support you.</li>
            <li>Personal data is processed under the Data Privacy Act of 2012 (RA 10173) and COMPASS privacy notice.</li>
            <li>If your safety is at risk, COMPASS may contact support services in line with its duty-to-protect policy, even if you decline.</li>
        </ul>
        <label id="referralConsentCheckRow" style="display:flex;align-items:flex-start;gap:8px;margin-top:12px;cursor:pointer">
            <input type="checkbox" id="referralConsentCheck" style="margin-top:3px" autocomplete="off">
            <span>I understand and I consent to a referral for professional support.</span>
        </label>
    </div>

    <p id="referralConsentError" role="alert" style="color:#dc2626;font-size:13px"></p>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px">
        <button type="button" id="referralDecline" style="padding:10px 16px;background:#f3f4f6;color:#374151;border:none;border-radius:8px;cursor:pointer;font-weight:600">Decline</button>
        <button type="button" id="referralAccept" style="padding:10px 16px;background:#047b40;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;disabled:disabled" disabled>Accept referral</button>
        <button type="button" id="referralDismiss" style="padding:10px 16px;background:none;color:#6b7280;border:none;cursor:pointer">Continue</button>
    </div>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('referralConsentDialog');
    if (!dialog) return;
    const isSeeker = @json(auth()->user()->role === 'seeker');
    const check = document.getElementById('referralConsentCheck');
    const acceptBtn = document.getElementById('referralAccept');
    const declineBtn = document.getElementById('referralDecline');
    const dismissBtn = document.getElementById('referralDismiss');
    const terms = document.getElementById('referralConsentTerms');
    const errorEl = document.getElementById('referralConsentError');
    const stateUrl = @json(route('session.referral-prompt', $session));
    let referral = null, seen = null, deciding = false;

    function enabledButtons() {
        acceptBtn.disabled = deciding || (isSeeker && !check?.checked);
        declineBtn.disabled = deciding;
    }

    function handleState(data) {
        referral = data?.referral || null;
        if (!referral) return;
        const key = referral.id + ':' + referral.status + ':' + (data.consent?.decision ?? 'none');
        if (key === seen) return;
        seen = key;
        errorEl.textContent = '';
        if (!isSeeker) {
            document.getElementById('referralConsentText').textContent =
                referral.status === 'consent_requested' && !referral.help_seeker_consent ? 'Your referral request is waiting for the seeker\u2019s decision.'
                : referral.status === 'consent_requested' ? 'The seeker accepted the referral consent. You can now submit the referral details.'
                : 'Referral status: ' + referral.status.replaceAll('_', ' ') + '.';
            terms.hidden = true;
            acceptBtn.hidden = true;
            declineBtn.hidden = true;
            dismissBtn.hidden = false;
            if (!dialog.open) dialog.showModal();
            return;
        }

        const alreadyDecided = data.consent && ['accepted', 'declined', 'withdrawn'].includes(data.consent.decision);
        const isConsentRequest = referral.status === 'pending_consent';

        document.getElementById('referralConsentText').textContent =
            referral.status === 'pending_consent'
                ? 'An adviser approved your referral. You can review consent and securely provide contact details for the assigned professional.'
                : isConsentRequest
                    ? 'Your helper recommended connecting you with a professional. Please review the referral consent below.'
                    : 'Referral status: ' + referral.status.replaceAll('_', ' ') + '.';

        if (alreadyDecided || !isConsentRequest) {
            terms.hidden = true;
            acceptBtn.hidden = true;
            declineBtn.hidden = true;
            dismissBtn.hidden = false;
            if (!dialog.open) dialog.showModal();
            return;
        }

        terms.hidden = false;
        check.checked = false;
        acceptBtn.hidden = false;
        declineBtn.hidden = false;
        dismissBtn.hidden = true;
        enabledButtons();
        if (!dialog.open) dialog.showModal();
    }

    async function poll() {
        try {
            const response = await fetch(stateUrl, {headers: {'Accept': 'application/json'}});
            if (!response.ok) return;
            handleState(await response.json());
        } catch (_) { /* Retry when the connection returns. */ }
    }

    async function decide(accepted) {
        if (deciding) return;
        deciding = true;
        enabledButtons();
        try {
            const body = {accepted: accepted};
            if (accepted && isSeeker && !acceptBtn.hidden) {
                if (!check?.checked) {
                    errorEl.textContent = 'Please tick the consent checkbox to accept.';
                    deciding = false; enabledButtons();
                    return;
                }
            }
            const response = await fetch(referral.consent_url, {
                method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
                body: JSON.stringify(body)
            });
            if (!response.ok) throw new Error('Your decision could not be saved. Please try again.');
            const saved = await response.json();
            dialog.close();
            if (saved.next_url) window.location.assign(saved.next_url);
        } catch (error) {
            errorEl.textContent = error.message;
        } finally {
            deciding = false;
        }
    }

    check?.addEventListener('change', enabledButtons);
    acceptBtn.addEventListener('click', () => decide(true));
    declineBtn.addEventListener('click', () => decide(false));
    dismissBtn.addEventListener('click', () => dialog.close());

    poll();
    setInterval(poll, 5000);

    if (window.Echo) {
        const channel = window.Echo.private('session.' + @json($session->id));
        channel.listen('.ReferralConsentRequested', () => poll());
        channel.listen('.ReferralConsentUpdated', () => poll());
    }
})();
</script>
@endisset