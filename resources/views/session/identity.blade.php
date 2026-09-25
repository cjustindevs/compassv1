<x-app-layout>
    <div class="max-w-2xl mx-auto my-8 px-4">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

            <div class="flex items-start justify-between gap-4 px-6 py-5 border-b border-gray-100 bg-gradient-to-b from-green-50 to-white">
                <div>
                    <p class="inline-flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-green-700 mb-1.5">
                        <i class="fas fa-shield-halved" aria-hidden="true"></i>Referral #{{ $referral->id }}
                    </p>
                    <h1 class="text-xl font-bold text-gray-800 leading-tight">Identity Disclosure Consent</h1>
                    <p class="text-sm text-gray-500 mt-1 leading-relaxed">
                        A separate, final step. It adds contact details to the referral your adviser already approved &mdash; it does not replace it.
                    </p>
                </div>
            </div>

            <div class="px-6 py-6 space-y-5">

                @if (!$referral->help_seeker_consent && $referral->status === 'pending_consent')
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <h2 class="text-sm font-bold text-gray-800 mb-2">Start with the referral consent</h2>
                        <p class="text-sm text-gray-600 leading-relaxed">
                            You have not agreed to this referral yet. Agreeing first is required before any contact details can be collected.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <button id="consentYes" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700">
                            <i class="fas fa-check" aria-hidden="true"></i>I consent to this referral
                        </button>
                        <button id="consentNo" class="px-5 py-2.5 rounded-xl bg-white border border-green-100 text-sm font-semibold text-green-700 hover:bg-green-50">
                            Decline referral
                        </button>
                    </div>

                @elseif ($referral->help_seeker_consent && !$referral->identity_disclosed && !in_array($referral->status, ['closed', 'declined', 'completed']))
                    <ol class="flex items-center gap-2 text-xs text-gray-400" aria-label="Referral progress">
                        <li class="flex items-center gap-2 text-green-700"><span class="w-5 h-5 grid place-items-center rounded-full bg-green-600 text-white text-[11px] font-bold"><i class="fas fa-check" aria-hidden="true"></i></span>Adviser approval</li>
                        <li class="w-4 h-px bg-gray-200"></li>
                        <li class="flex items-center gap-2 text-green-700"><span class="w-5 h-5 grid place-items-center rounded-full bg-green-600 text-white text-[11px] font-bold"><i class="fas fa-check" aria-hidden="true"></i></span>Referral consent</li>
                        <li class="w-4 h-px bg-gray-200"></li>
                        <li class="flex items-center gap-2 text-gray-800 font-semibold"><span class="w-5 h-5 grid place-items-center rounded-full bg-green-600 text-white text-[11px] font-bold"><i class="fas fa-circle-dot" aria-hidden="true"></i></span>Identity disclosure</li>
                        <li class="w-4 h-px bg-gray-200"></li>
                        <li class="flex items-center gap-2"><span class="w-5 h-5 grid place-items-center rounded-full bg-gray-200 text-white text-[11px] font-bold"><i class="fas fa-circle" aria-hidden="true"></i></span>Professional release</li>
                    </ol>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <h2 class="flex items-center gap-2 text-sm font-bold text-gray-800 mb-2">
                            <i class="fas fa-file-signature text-green-600" aria-hidden="true"></i>The referral consent you already accepted
                        </h2>
                        @include('partials.referral-consent-terms')
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <h2 class="flex items-center gap-2 text-sm font-bold text-gray-800 mb-2">
                            <i class="fas fa-vault text-green-600" aria-hidden="true"></i>What this step adds
                        </h2>
                        <div class="space-y-2 text-sm text-gray-600 leading-relaxed">
                            <p class="flex gap-2"><i class="fas fa-lock text-green-600 mt-1 shrink-0" aria-hidden="true"></i><span>Details are encrypted in the separate <strong class="text-gray-800">Identity Vault</strong>, never in the operational database, and are never shown in the chat window.</span></p>
                            <p class="flex gap-2"><i class="fas fa-user-check text-green-600 mt-1 shrink-0" aria-hidden="true"></i><span>Only your <strong class="text-gray-800">assigned adviser</strong> can authorize release, and only to the professional assigned to this referral.</span></p>
                            <p class="flex gap-2"><i class="fas fa-user-shield text-green-600 mt-1 shrink-0" aria-hidden="true"></i><span>Your helper and moderators can never view it. Submitting replaces any earlier submission, and a replacement needs a fresh release.</span></p>
                            <p class="flex gap-2"><i class="fas fa-hourglass-half text-green-600 mt-1 shrink-0" aria-hidden="true"></i><span>Stored for up to {{ config('identity_vault.retention_days') }} days, then deleted. Withdrawing referral consent stops all future professional access.</span></p>
                            <p class="flex gap-2"><i class="fas fa-triangle-exclamation text-green-600 mt-1 shrink-0" aria-hidden="true"></i><span>If your safety is at risk, a restricted emergency procedure may still release information to protect life, and this is logged.</span></p>
                        </div>
                    </div>

                    <form id="identityForm" autocomplete="off" novalidate>
                        <fieldset class="border-0 p-0 m-0">
                            <legend class="text-sm font-bold text-gray-800">Your contact details</legend>
                            <p class="text-xs text-gray-500 mt-1 mb-4 leading-relaxed">Use details a professional can actually reach you on. Everything is optional except the two marked required.</p>
                            <div class="grid sm:grid-cols-2 gap-4">
                                @foreach (\App\Services\IdentityVaultService::FIELDS as $field)
                                    @php($isRequired = in_array($field, ['real_name', 'phone_number'], true))
                                    <div>
                                        <label for="identity-{{ $field }}" class="block text-xs font-semibold text-gray-700 mb-1.5">
                                            {{ ucwords(str_replace('_', ' ', $field)) }}
                                            @if($isRequired)
                                                <span class="text-red-600" title="Required">*</span>
                                            @else
                                                <span class="text-gray-400 font-normal">(optional)</span>
                                            @endif
                                        </label>
                                        <input id="identity-{{ $field }}"
                                               name="{{ $field }}"
                                               type="{{ $field === 'email' ? 'email' : 'text' }}"
                                               maxlength="500"
                                               @required($isRequired)
                                               @if($isRequired)aria-required="true"@endif
                                               autocomplete="{{ $field === 'email' ? 'email' : ($field === 'real_name' ? 'name' : 'tel') }}"
                                               class="block w-full rounded-xl border-gray-300 text-sm focus:border-green-600 focus:ring-green-600">
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>

                        <label class="flex items-start gap-3 mt-5 p-4 rounded-xl bg-green-50 border border-green-100 cursor-pointer">
                            <input type="checkbox" name="identity_disclosure" value="1" required class="mt-0.5 w-4 h-4 shrink-0" style="accent-color:#04A052">
                            <span class="text-sm leading-relaxed text-gray-800">
                                I have read the referral consent above, and I voluntarily consent to store these contact details and authorize their disclosure to the professional assigned to this referral.
                            </span>
                        </label>

                        <div class="flex flex-wrap gap-3 mt-5">
                            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-green-600 text-white text-sm font-semibold hover:bg-green-700">
                                <i class="fas fa-lock" aria-hidden="true"></i>Store details securely
                            </button>
                        </div>
                    </form>

                @else
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <p class="text-sm text-gray-600 leading-relaxed">This referral is no longer collecting identity information.</p>
                    </div>
                @endif

                <p id="result" role="status" class="hidden text-sm leading-relaxed rounded-xl px-4 py-3"></p>
            </div>
        </div>
    </div>

    <style>
        .rv-consent-terms__list { margin: 0; padding-left: 18px; }
        .rv-consent-terms__list li { font-size: 13px; line-height: 1.7; color: #475569; margin-bottom: 5px; }
        .rv-consent-terms__list li:last-child { margin-bottom: 0; }
        .rv-consent-terms__list strong { color: #163b2d; }
    </style>

    <script>
    (() => {
        const result = document.getElementById('result');
        const show = (message, ok) => {
            result.textContent = message;
            result.classList.remove('hidden', 'bg-red-50', 'text-red-700', 'border', 'border-red-200', 'bg-green-50', 'text-green-800', 'border-green-200');
            result.classList.add('block', 'border', ok ? 'bg-green-50' : 'bg-red-50', ok ? 'text-green-800' : 'text-red-700', ok ? 'border-green-200' : 'border-red-200');
        };
        async function send(url, data) {
            const response = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())}, body: JSON.stringify(data)});
            const body = await response.json();
            if (!response.ok) throw new Error(body.errors ? Object.values(body.errors).flat().join(' ') : body.message);
            return body;
        }
        async function consent(value) {
            try { await send(@json(route('referrals.consent', $referral)), {consent_given: value}); location.reload(); }
            catch (error) { show(error.message, false); }
        }
        document.getElementById('consentYes')?.addEventListener('click', () => consent(true));
        document.getElementById('consentNo')?.addEventListener('click', () => consent(false));
        document.getElementById('identityForm')?.addEventListener('submit', async event => {
            event.preventDefault();
            const form = event.target, button = form.querySelector('button[type=submit]');
            button.disabled = true;
            const label = button.innerHTML;
            button.textContent = 'Storing securely…';
            try {
                const body = await send(@json(route('identity.store', $referral)), Object.fromEntries(new FormData(form)));
                form.hidden = true;
                show(body.message, true);
            } catch (error) {
                show(error.message, false);
                button.disabled = false;
                button.innerHTML = label;
            }
        });
    })();
    </script>
</x-app-layout>
