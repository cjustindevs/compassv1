@isset($session)
<dialog id="referralConsentDialog" style="border:0;border-radius:16px;padding:24px;max-width:440px">
    <h2 style="font-size:20px;font-weight:700">Professional support</h2>
    <p id="referralConsentText" style="margin:16px 0"></p>
    <p id="referralConsentError" role="alert"></p>
    <button type="button" id="referralAccept" style="padding:10px;background:#047b40;color:white;border-radius:8px">Accept referral</button>
    <button type="button" id="referralDecline" style="padding:10px">Decline</button>
    <button type="button" id="referralDismiss" style="padding:10px">Continue</button>
</dialog>
<script>
(() => {
    const dialog = document.getElementById('referralConsentDialog');
    const isSeeker = @json(auth()->user()->role === 'seeker');
    let referral = null, seen = null;
    async function poll() {
        try {
            const response = await fetch(@json(route('session.referral-prompt', $session)), {headers: {'Accept': 'application/json'}});
            if (!response.ok) return;
            referral = (await response.json()).referral;
            if (!referral) return;
            const key = referral.id + ':' + referral.status;
            if (key === seen || (isSeeker && referral.status !== 'pending_consent')) return;
            seen = key;
            document.getElementById('referralConsentText').textContent = isSeeker
                ? 'An adviser approved your referral. You can review consent and securely provide contact details for the assigned professional.'
                : (referral.status === 'pending_consent' ? 'Your referral request is waiting for the seeker’s decision. You can continue chatting.' : 'Referral status: ' + referral.status.replaceAll('_', ' ') + '.');
            document.getElementById('referralAccept').hidden = !isSeeker;
            document.getElementById('referralDecline').hidden = !isSeeker;
            document.getElementById('referralDismiss').hidden = isSeeker;
            if (!dialog.open) dialog.showModal();
        } catch (_) { /* Retry when the connection returns. */ }
    }
    async function decide(consent) {
        const buttons = dialog.querySelectorAll('button');
        buttons.forEach(button => button.disabled = true);
        try {
            const response = await fetch(referral.consent_url, {
                method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
                body: JSON.stringify({consent_given: consent})
            });
            if (!response.ok) throw new Error('Your decision could not be saved. Please try again.');
            dialog.close();
        } catch (error) { document.getElementById('referralConsentError').textContent = error.message; }
        finally { buttons.forEach(button => button.disabled = false); }
    }
    document.getElementById('referralAccept').onclick = () => { location.href = referral.identity_url; };
    document.getElementById('referralDecline').onclick = () => decide(false);
    document.getElementById('referralDismiss').onclick = () => dialog.close();
    poll();
    setInterval(poll, 5000);
})();
</script>
@endisset
