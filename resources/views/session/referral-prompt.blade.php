@isset($session)
<div id="chatEmergencyBanner" hidden role="status" style="padding:12px 16px;margin:12px 0;border:1px solid #efccd0;border-left:4px solid #ca293c;border-radius:10px;background:#fff5f5;color:#872333;font-size:13px">
    <strong id="chatEmergencyTitle">Emergency reported</strong><p id="chatEmergencyStatus" style="margin:5px 0"></p><a href="{{ route('emergency') }}" style="display:inline-block;padding:6px 10px;border:1px solid #e3b7c0;border-radius:8px;color:inherit;text-decoration:none">Emergency resources</a>
</div>
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
    #referralConsentTerms h3 :is(i, .compass-icon) { color: #04a052; }
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
            <p class="rv-modal__eyebrow">Professional support</p>
            <h2 id="referralConsentHeading">Referral consent</h2>
        </div>
    </div>

    <div class="rv-modal__body">
        <p id="referralConsentText">Please review what this means for your privacy before deciding.</p>

        <div id="referralConsentTerms">
            <h3>What you are agreeing to</h3>
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
<section id="professionalSupportCard" hidden style="padding:16px;border:1px solid #d7e8dd;border-radius:14px;background:#f3fbf6;margin:12px 0"><strong>Your Professional Support</strong><p id="professionalSupportStatus" style="margin:8px 0"></p><button type="button" id="chatSupportOpen" style="padding:10px;border-radius:8px;background:#048948;color:white">View support details</button></section>
<button type="button" id="chatReferralOpen" hidden style="padding:8px 12px;border:1px solid #bad8c4;border-radius:8px;margin:4px">Review referral</button>
<button type="button" id="chatIdentityOpen" hidden style="padding:8px 12px;border:1px solid #bad8c4;border-radius:8px;margin:4px">Provide contact details</button>
<style>#professionalSupportCard{flex-shrink:0;font:13px/1.5 Inter,system-ui,sans-serif!important;margin:8px 12px!important;padding:12px 14px!important}#professionalSupportCard p{margin:5px 0 8px!important}#professionalSupportCard button{font:600 12px Inter,system-ui,sans-serif;min-height:36px;margin:3px;padding:8px 12px!important}#chatIdentityDialog::backdrop{background:rgba(15,35,25,.48)}#chatIdentityDialog>button{border:1px solid #cbdcd1;background:white;color:#17613b;border-radius:8px;padding:7px 12px;cursor:pointer}#chatIdentityDialog iframe{display:block;box-sizing:border-box;padding:0}#chatIdentityDialog[data-support] iframe{padding-top:42px}#chatIdentityDialog{width:min(620px,calc(100vw - 24px))!important;height:min(720px,90dvh)!important;max-height:90dvh;overflow:hidden}#chatIdentityDialog>button[hidden]{display:none!important}</style>
<dialog id="chatIdentityDialog" aria-label="Referral details and identity disclosure" style="width:min(900px,96vw);height:min(620px,82dvh);padding:0;border:0;border-radius:18px;margin:auto"><button type="button" id="chatEmbeddedClose" hidden onclick="this.closest('dialog').close()" style="position:absolute;right:14px;top:8px;z-index:2">Close</button><iframe title="Identity disclosure" referrerpolicy="no-referrer" style="border:0;width:100%;height:100%"></iframe></dialog>
<meta id="chatReferralConfig" content="{{ json_encode(['isSeeker' => auth()->user()->role === 'seeker', 'stateUrl' => route('session.referral-prompt', $session), 'csrf' => csrf_token(), 'sessionId' => $session->id]) }}">
<script src="{{ asset('js/chat-referral.js') }}?v={{ filemtime(public_path('js/chat-referral.js')) }}" defer></script>
@endisset
