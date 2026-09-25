@php
    $identityDialogId = 'identity-dialog-' . $referral->id;
    $requiredIdentityFields = ['real_name', 'phone_number'];
@endphp
<style>
    #{{ $identityDialogId }} {
        position: fixed;
        inset: 0;
        margin: auto;
        border: 0;
        padding: 0;
        width: min(560px, calc(100vw - 24px));
        max-height: 88dvh;
        color: #163b2d;
        background: #fff;
        border-radius: 20px;
        box-shadow: 0 24px 80px rgba(0, 0, 0, .28);
        font-family: Inter, sans-serif;
        overflow: hidden;
    }
    #{{ $identityDialogId }}::backdrop { background: rgba(15, 35, 25, .5); backdrop-filter: blur(3px); }
    #{{ $identityDialogId }} .rv-modal__head {
        display: flex; align-items: flex-start; justify-content: space-between; gap: 14px;
        padding: 20px 22px 16px; border-bottom: 1px solid #eef2ef;
        background: linear-gradient(180deg, #f4fbf7, #fff);
    }
    #{{ $identityDialogId }} .rv-modal__eyebrow {
        display: inline-flex; align-items: center; gap: 6px; margin: 0 0 6px;
        font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #027039;
    }
    #{{ $identityDialogId }} h2 { margin: 0; font-size: 19px; font-weight: 700; line-height: 1.3; }
    #{{ $identityDialogId }} .rv-modal__sub { margin: 5px 0 0; font-size: 13px; line-height: 1.6; color: #64748b; }
    #{{ $identityDialogId }} .rv-icon-btn {
        flex-shrink: 0; width: 34px; height: 34px; display: grid; place-items: center;
        border: 1px solid #e5e7eb; border-radius: 10px; background: #fff; color: #64748b; cursor: pointer;
        transition: background .15s ease, color .15s ease;
    }
    #{{ $identityDialogId }} .rv-icon-btn:hover { background: #f1f5f3; color: #163b2d; }
    #{{ $identityDialogId }} .rv-modal__body { padding: 18px 22px 20px; max-height: 58dvh; overflow-y: auto; }
    #{{ $identityDialogId }} .rv-steps { display: flex; align-items: center; gap: 8px; margin: 0 0 16px; padding: 0; list-style: none; }
    #{{ $identityDialogId }} .rv-steps li { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #94a3b8; }
    #{{ $identityDialogId }} .rv-steps li::before {
        content: ''; width: 20px; height: 20px; flex-shrink: 0; border-radius: 50%;
        background: #e2e8f0; color: #fff; font-size: 11px; font-weight: 700;
        display: grid; place-items: center;
    }
    #{{ $identityDialogId }} .rv-steps li[data-done] { color: #027039; }
    #{{ $identityDialogId }} .rv-steps li[data-done]::before { content: '\f00c'; background: #04a052; }
    #{{ $identityDialogId }} .rv-steps li[data-current] { color: #163b2d; font-weight: 600; }
    #{{ $identityDialogId }} .rv-steps li[data-current]::before { content: '\f111'; background: #04a052; }
    #{{ $identityDialogId }} .rv-steps li:not(:last-child)::after { content: ''; width: 18px; height: 1px; background: #e2e8f0; }
    #{{ $identityDialogId }} .rv-card {
        border: 1px solid #e5e7eb; border-radius: 14px; padding: 14px 16px; margin: 0 0 14px; background: #fbfdfc;
    }
    #{{ $identityDialogId }} .rv-card h3 {
        display: flex; align-items: center; gap: 7px; margin: 0 0 8px;
        font-size: 13px; font-weight: 700; color: #163b2d;
    }
    #{{ $identityDialogId }} .rv-card h3 i { color: #04a052; }
    #{{ $identityDialogId }} .rv-consent-terms__list { margin: 0; padding-left: 18px; }
    #{{ $identityDialogId }} .rv-consent-terms__list li { font-size: 13px; line-height: 1.7; color: #475569; margin-bottom: 5px; }
    #{{ $identityDialogId }} .rv-consent-terms__list li:last-child { margin-bottom: 0; }
    #{{ $identityDialogId }} .rv-consent-terms__list strong { color: #163b2d; }
    #{{ $identityDialogId }} .rv-facts { display: grid; gap: 8px; margin: 0; }
    #{{ $identityDialogId }} .rv-facts div { display: flex; gap: 9px; font-size: 13px; line-height: 1.6; color: #475569; }
    #{{ $identityDialogId }} .rv-facts i { color: #04a052; margin-top: 3px; flex-shrink: 0; }
    #{{ $identityDialogId }} fieldset { border: 0; padding: 0; margin: 0 0 14px; }
    #{{ $identityDialogId }} legend { padding: 0; font-size: 13px; font-weight: 700; color: #163b2d; margin-bottom: 4px; }
    #{{ $identityDialogId }} .rv-hint { margin: 0 0 12px; font-size: 12px; line-height: 1.6; color: #64748b; }
    #{{ $identityDialogId }} .rv-grid { display: grid; gap: 12px; }
    #{{ $identityDialogId }} .rv-field label { display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 5px; }
    #{{ $identityDialogId }} .rv-req { color: #b91c1c; }
    #{{ $identityDialogId }} .rv-opt { color: #94a3b8; font-weight: 500; }
    #{{ $identityDialogId }} .rv-field input {
        width: 100%; box-sizing: border-box; padding: 10px 12px; font-size: 14px; font-family: inherit; color: #163b2d;
        background: #fff; border: 1px solid #d1d5db; border-radius: 10px; transition: border-color .15s ease, box-shadow .15s ease;
    }
    #{{ $identityDialogId }} .rv-field input:focus { outline: none; border-color: #04a052; box-shadow: 0 0 0 3px rgba(4, 160, 82, .18); }
    #{{ $identityDialogId }} .rv-field input[aria-invalid="true"] { border-color: #dc2626; }
    #{{ $identityDialogId }} .rv-field .rv-error { display: none; margin: 5px 0 0; font-size: 12px; color: #b91c1c; }
    #{{ $identityDialogId }} .rv-field .rv-error[data-shown] { display: block; }
    #{{ $identityDialogId }} .rv-consent {
        display: flex; gap: 11px; align-items: flex-start; padding: 14px 16px; cursor: pointer;
        background: #eaf8f0; border: 1px solid #d0f0d8; border-radius: 14px;
        font-size: 13px; line-height: 1.65; color: #163b2d;
    }
    #{{ $identityDialogId }} .rv-consent input { margin: 2px 0 0; width: 17px; height: 17px; flex-shrink: 0; accent-color: #04a052; }
    #{{ $identityDialogId }} .rv-alert { margin: 12px 0 0; font-size: 13px; line-height: 1.6; color: #b91c1c; }
    #{{ $identityDialogId }} .rv-alert:empty { display: none; }
    #{{ $identityDialogId }} .rv-done { display: none; text-align: center; padding: 8px 0 4px; }
    #{{ $identityDialogId }} .rv-done[data-shown] { display: block; }
    #{{ $identityDialogId }} .rv-done i { font-size: 34px; color: #04a052; margin-bottom: 12px; }
    #{{ $identityDialogId }} .rv-done h3 { margin: 0 0 6px; font-size: 17px; font-weight: 700; }
    #{{ $identityDialogId }} .rv-done p { margin: 0; font-size: 13px; line-height: 1.7; color: #64748b; }
    #{{ $identityDialogId }} .rv-modal__foot {
        display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap;
        padding: 14px 22px; border-top: 1px solid #eef2ef; background: #f8fbf9;
    }
    #{{ $identityDialogId }} .rv-btn {
        display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px;
        font-family: inherit; font-size: 13px; font-weight: 600; border-radius: 10px; cursor: pointer;
        border: 1px solid #d1e5d9; background: #fff; color: #027039; transition: background .15s ease, border-color .15s ease;
    }
    #{{ $identityDialogId }} .rv-btn:hover { background: #f1f5f3; }
    #{{ $identityDialogId }} .rv-btn.primary { background: #04a052; border-color: #04a052; color: #fff; }
    #{{ $identityDialogId }} .rv-btn.primary:hover { background: #038a45; }
    #{{ $identityDialogId }} .rv-btn:disabled { opacity: .55; cursor: not-allowed; }
    #{{ $identityDialogId }} :focus-visible { outline: 3px solid #86efac; outline-offset: 2px; }
    @media (max-width: 480px) {
        #{{ $identityDialogId }} { width: calc(100vw - 16px); max-height: 92dvh; }
        #{{ $identityDialogId }} .rv-modal__body { max-height: 56dvh; }
        #{{ $identityDialogId }} .rv-modal__foot .rv-btn { flex: 1 1 auto; justify-content: center; }
    }
</style>

<dialog id="{{ $identityDialogId }}" aria-labelledby="identity-title-{{ $referral->id }}" aria-describedby="identity-sub-{{ $referral->id }}">
    <div class="rv-modal__head">
        <div>
            <p class="rv-modal__eyebrow"><i class="fas fa-shield-halved" aria-hidden="true"></i>Referral #{{ $referral->id }}</p>
            <h2 id="identity-title-{{ $referral->id }}">Identity Disclosure Consent</h2>
            <p class="rv-modal__sub" id="identity-sub-{{ $referral->id }}">A separate, final step. It adds contact details to the referral you already approved &mdash; it does not replace it.</p>
        </div>
        <button type="button" class="rv-icon-btn" data-identity-close aria-label="Close identity disclosure consent">
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
    </div>

    <form data-identity-form action="{{ route('identity.store',$referral, false) }}" autocomplete="off" novalidate>
        <div class="rv-modal__body">
            <ol class="rv-steps" aria-label="Referral progress">
                <li data-done>Adviser approval</li>
                <li data-done>Referral consent</li>
                <li data-current>Identity disclosure</li>
                <li>Professional release</li>
            </ol>

            <div class="rv-card">
                <h3><i class="fas fa-file-signature" aria-hidden="true"></i>The referral consent you already accepted</h3>
                @include('partials.referral-consent-terms')
            </div>

            <div class="rv-card">
                <h3><i class="fas fa-vault" aria-hidden="true"></i>What this step adds</h3>
                <div class="rv-facts">
                    <div><i class="fas fa-lock" aria-hidden="true"></i><span>Details are encrypted in the separate <strong>Identity Vault</strong>, never in the operational database, and are never shown in the chat window.</span></div>
                    <div><i class="fas fa-user-check" aria-hidden="true"></i><span>Only your <strong>assigned adviser</strong> can authorize release, and only to the professional assigned to this referral.</span></div>
                    <div><i class="fas fa-user-shield" aria-hidden="true"></i><span>Your helper and moderators can never view it. Submitting replaces any earlier submission, and a replacement needs a fresh release.</span></div>
                    <div><i class="fas fa-hourglass-half" aria-hidden="true"></i><span>Stored for up to {{ config('identity_vault.retention_days') }} days, then deleted. Withdrawing referral consent stops all future professional access.</span></div>
                    <div><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><span>If your safety is at risk, a restricted emergency procedure may still release information to protect life, and this is logged.</span></div>
                </div>
            </div>

            <fieldset data-identity-fields>
                <legend>Your contact details</legend>
                <p class="rv-hint">Use details a professional can actually reach you on. Everything you enter here is optional except the two marked required.</p>
                <div class="rv-grid">
                    @foreach(\App\Services\IdentityVaultService::FIELDS as $field)
                        @php($isRequired = in_array($field, $requiredIdentityFields, true))
                        <div class="rv-field" data-field="{{ $field }}">
                            <label for="identity-{{ $referral->id }}-{{ $field }}">
                                {{ ucwords(str_replace('_',' ',$field)) }}
                                @if($isRequired)<span class="rv-req" title="Required">*</span>@else<span class="rv-opt">(optional)</span>@endif
                            </label>
                            <input id="identity-{{ $referral->id }}-{{ $field }}"
                                   name="{{ $field }}"
                                   type="{{ $field === 'email' ? 'email' : 'text' }}"
                                   maxlength="500"
                                   @required($isRequired)
                                   @if($isRequired)aria-required="true"@endif
                                   autocomplete="{{ $field === 'email' ? 'email' : ($field === 'real_name' ? 'name' : 'tel') }}">
                            <p class="rv-error" data-error-for="{{ $field }}"></p>
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <label class="rv-consent">
                <input type="checkbox" name="identity_disclosure" value="1" required>
                <span>I have read the referral consent above, and I voluntarily consent to store these contact details and authorize their disclosure to the professional assigned to this referral.</span>
            </label>

            <p class="rv-alert" role="alert" data-identity-result></p>

            <div class="rv-done" data-identity-done>
                <i class="fas fa-circle-check" aria-hidden="true"></i>
                <h3>Details stored securely</h3>
                <p data-identity-done-text></p>
            </div>
        </div>

        <div class="rv-modal__foot">
            <button type="button" class="rv-btn" data-identity-close data-identity-dismiss>Not now</button>
            <button type="submit" class="rv-btn primary" data-identity-submit disabled>
                <i class="fas fa-lock" aria-hidden="true"></i>Submit details securely
            </button>
        </div>
    </form>
</dialog>

@if((string)(session('identity_referral_id') ?? request('identity')) === (string)$referral->id)
    <script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('{{ $identityDialogId }}').showModal());</script>
@endif

@once
<script>
(() => {
    const CLOSE = '[data-identity-close]';

    document.addEventListener('click', event => {
        const opener = event.target.closest(CLOSE);
        if (opener) { opener.closest('dialog')?.close(); return; }
        const dialog = event.target.closest('dialog[id^="identity-dialog-"]');
        if (!dialog || event.target !== dialog) return;
        const box = dialog.getBoundingClientRect();
        const outside = event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom;
        if (outside) dialog.close();
    });

    document.addEventListener('change', event => {
        const form = event.target.closest('[data-identity-form]');
        if (!form || form.dataset.identitySaved) return;
        const submit = form.querySelector('[data-identity-submit]');
        if (submit) submit.disabled = !form.querySelector('[name="identity_disclosure"]')?.checked;
    });

    document.addEventListener('submit', async event => {
        const form = event.target.closest('[data-identity-form]');
        if (!form) return;
        event.preventDefault();

        const result = form.querySelector('[data-identity-result]');
        const submit = form.querySelector('[data-identity-submit]');
        const fields = form.querySelector('[data-identity-fields]');
        const done = form.querySelector('[data-identity-done]');
        const doneText = form.querySelector('[data-identity-done-text]');
        const consent = form.querySelector('[name="identity_disclosure"]');

        form.querySelectorAll('[data-error-for]').forEach(node => {
            node.textContent = '';
            delete node.dataset.shown;
        });
        form.querySelectorAll('[aria-invalid]').forEach(input => input.removeAttribute('aria-invalid'));
        result.textContent = '';

        if (!consent?.checked) {
            result.textContent = 'Please confirm the identity disclosure consent before submitting your details.';
            consent?.focus();
            return;
        }

        submit.disabled = true;
        const label = submit.innerHTML;
        submit.textContent = 'Storing securely…';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {Accept: 'application/json'},
                body: new FormData(form),
            });
            const body = await response.json();
            if (!response.ok) {
                const errors = body.errors || {};
                Object.entries(errors).forEach(([field, messages]) => {
                    const slot = form.querySelector(`[data-error-for="${field}"]`);
                    const input = form.querySelector(`[name="${field}"]`);
                    if (slot) { slot.textContent = [].concat(messages).join(' '); slot.dataset.shown = '1'; }
                    if (input) {
                        input.setAttribute('aria-invalid', 'true');
                        input.addEventListener('input', () => {
                            input.removeAttribute('aria-invalid');
                            if (slot) { slot.textContent = ''; delete slot.dataset.shown; }
                        }, {once: true});
                    }
                });
                const summary = Object.values(errors).flat().join(' ');
                if (summary) result.textContent = summary;
                else if (body.message) result.textContent = body.message;
                const firstBad = form.querySelector('[aria-invalid="true"]');
                (firstBad || consent).focus();
                submit.disabled = false;
                submit.innerHTML = label;
                return;
            }

            form.reset();
            form.dataset.identitySaved = '1';
            submit.hidden = true;
            fields.hidden = true;
            form.querySelector('.rv-consent').hidden = true;
            doneText.textContent = body.message || 'Your details were stored. Your adviser can now authorize release to the assigned professional.';
            done.dataset.shown = '1';
            const dismiss = form.querySelector('[data-identity-dismiss]');
            if (dismiss) dismiss.textContent = 'Close';
        } catch (error) {
            result.textContent = 'We could not reach the server. Your details were not saved. Please try again.';
            submit.disabled = false;
            submit.innerHTML = label;
        }
    });
})();
</script>
@endonce
