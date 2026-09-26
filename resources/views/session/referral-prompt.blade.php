@isset($session)
<style>
    #referralConsentDialog {
        position: fixed;
        inset: 0;
        margin: auto;
        border: 0;
        padding: 0;
        width: min(460px, calc(100vw - 24px));
        max-height: 88dvh;
        color: #163b2d;
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 24px 80px rgba(0, 0, 0, .28);
        font-family: Inter, sans-serif;
        overflow: hidden;
    }
    #referralConsentDialog::backdrop { background: rgba(15, 35, 25, .5); backdrop-filter: blur(3px); }
    #referralConsentDialog:not([open]) { display: none; }
    #referralConsentDialog [hidden] { display: none !important; }
    #referralConsentDialog .rv-modal__head {
        display: flex; align-items: flex-start; justify-content: space-between; gap: 14px;
        padding: 20px 22px 16px; border-bottom: 1px solid #eef2ef;
        background: linear-gradient(180deg, #f4fbf7, #fff);
    }
    #referralConsentDialog .rv-modal__eyebrow {
        display: inline-flex; align-items: center; gap: 6px; margin: 0 0 6px;
        font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #027039;
    }
    #referralConsentDialog h2 { margin: 0; font-size: 19px; font-weight: 700; line-height: 1.3; }
    #referralConsentDialog .rv-modal__body { padding: 18px 22px 20px; max-height: 56dvh; overflow-y: auto; }
    #referralConsentText { margin: 0 0 14px; font-size: 14px; line-height: 1.65; color: #374151; }
    #referralConsentTerms {
        padding: 14px 16px; margin: 0;
        background: #fbfdfc; border: 1px solid #e5e7eb; border-radius: 14px;
    }
    #referralConsentTerms h3 {
        display: flex; align-items: center; gap: 7px; margin: 0 0 8px;
        font-size: 13px; font-weight: 700; color: #163b2d;
    }
    #referralConsentTerms h3 i { color: #04a052; }
    #referralConsentTerms .rv-consent-terms__list { margin: 0; padding-left: 18px; }
    #referralConsentTerms .rv-consent-terms__list li { font-size: 13px; line-height: 1.7; color: #475569; margin-bottom: 5px; }
    #referralConsentTerms .rv-consent-terms__list li:last-child { margin-bottom: 0; }
    #referralConsentTerms .rv-consent-terms__list strong { color: #163b2d; }
    #referralConsentTerms .rv-consent-terms__note {
        margin: 10px 0 0; padding: 10px 12px; border-radius: 10px;
        background: #eaf8f0; border: 1px solid #d0f0d8;
        font-size: 12px; line-height: 1.65; color: #027039;
    }
    #referralConsentCheckRow {
        display: flex; align-items: flex-start; gap: 10px; margin: 12px 0 0;
        padding: 12px 14px; cursor: pointer;
        background: #eaf8f0; border: 1px solid #d0f0d8; border-radius: 12px;
        font-size: 13px; line-height: 1.65; color: #163b2d;
    }
    #referralConsentCheckRow input { margin: 2px 0 0; width: 17px; height: 17px; flex-shrink: 0; accent-color: #04a052; }
    #referralConsentError { margin: 12px 0 0; font-size: 13px; line-height: 1.6; color: #b91c1c; }
    #referralConsentError:empty { display: none; }
    #referralConsentDialog .rv-modal__foot {
        display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap;
        padding: 14px 22px; border-top: 1px solid #eef2ef; background: #f8fbf9;
    }
    #referralConsentDialog .rv-btn {
        display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px;
        font-family: inherit; font-size: 13px; font-weight: 600; border-radius: 10px; cursor: pointer;
        border: 1px solid #d1e5d9; background: #fff; color: #027039; transition: background .15s ease, border-color .15s ease;
    }
    #referralConsentDialog .rv-btn:hover { background: #f1f5f3; }
    #referralConsentDialog .rv-btn.primary { background: #04a052; border-color: #04a052; color: #fff; }
    #referralConsentDialog .rv-btn.primary:hover { background: #038a45; }
    #referralConsentDialog .rv-btn:disabled { opacity: .55; cursor: not-allowed; }
    #referralConsentDialog :focus-visible { outline: 3px solid #86efac; outline-offset: 2px; }
    @media (max-width: 480px) {
        #referralConsentDialog { width: calc(100vw - 16px); max-height: 92dvh; }
        #referralConsentDialog .rv-modal__body { max-height: 54dvh; }
        #referralConsentDialog .rv-modal__foot .rv-btn { flex: 1 1 auto; justify-content: center; }
    }
</style>
<dialog id="referralConsentDialog" aria-labelledby="referralConsentHeading" aria-describedby="referralConsentText">
    <div class="rv-modal__head">
        <div>
            <p class="rv-modal__eyebrow"><i class="fas fa-user-doctor" aria-hidden="true"></i>Professional support</p>
            <h2 id="referralConsentHeading">Referral consent</h2>
        </div>
    </div>

    <div class="rv-modal__body">
        <p id="referralConsentText">Please review what this means for your privacy before deciding.</p>

        <div id="referralConsentTerms">
            <h3><i class="fas fa-file-signature" aria-hidden="true"></i>What you are agreeing to</h3>
            @include('partials.referral-consent-terms')
            <p class="rv-consent-terms__note">Your contact details are collected in a later, separate step and only if you still want a professional to reach you.</p>

            <label id="referralConsentCheckRow">
                <input type="checkbox" id="referralConsentCheck" autocomplete="off">
                <span>I understand and I consent to a referral for professional support.</span>
            </label>
        </div>

        <p id="referralConsentError" role="alert"></p>
    </div>

    <div class="rv-modal__foot">
        <button type="button" id="referralDecline" class="rv-btn">Decline</button>
        <button type="button" id="referralAccept" class="rv-btn primary" disabled>Accept referral</button>
        <button type="button" id="referralDismiss" class="rv-btn" hidden>Continue</button>
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
                ['pending_adviser', 'consent_requested'].includes(referral.status) ? 'Your recommendation is awaiting Adviser review.'
                : referral.status === 'pending_consent' ? 'The Adviser approved your recommendation. The seeker is deciding whether to proceed.'
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
                    check.focus();
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
    dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const box = dialog.getBoundingClientRect();
        const outside = event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom;
        if (outside) dialog.close();
    });

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
