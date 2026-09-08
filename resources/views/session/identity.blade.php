<x-app-layout>
    <div class="max-w-xl mx-auto p-6 bg-white rounded-xl my-8">
        <h1 class="text-2xl font-bold">Professional referral</h1>
        <p class="my-4">An adviser approved referral #{{ $referral->id }}. If you agree, your contact details will be encrypted in separate identity storage and released to the assigned psychology professional. Identity information expires after 365 days. Helpers and moderators cannot view it.</p>
        @if (!$referral->help_seeker_consent && $referral->status === 'pending_consent')
            <button id="consentYes" class="bg-green-700 text-white p-3 rounded">I consent to this referral and identity release</button>
            <button id="consentNo" class="p-3">Decline referral</button>
        @elseif ($referral->help_seeker_consent && !$referral->identity_disclosed && !in_array($referral->status, ['closed', 'declined', 'completed']))
            <form id="identityForm" autocomplete="off">
                @foreach (\App\Services\IdentityVaultService::FIELDS as $field)
                    <label class="block my-3">{{ ucwords(str_replace('_', ' ', $field)) }}{{ in_array($field, ['real_name', 'phone_number']) ? ' *' : '' }}
                        <input class="block w-full rounded border-gray-300" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" maxlength="500" @required(in_array($field, ['real_name', 'phone_number']))>
                    </label>
                @endforeach
                <button class="bg-green-700 text-white p-3 rounded">Store identity securely</button>
            </form>
        @else
            <p>This referral is no longer collecting identity information.</p>
        @endif
        <p id="result" role="status" class="my-4"></p>
    </div>
    <script>
    (() => {
        const result = document.getElementById('result');
        async function send(url, data) {
            const response = await fetch(url, {method: 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())}, body: JSON.stringify(data)});
            const body = await response.json();
            if (!response.ok) throw new Error(body.errors ? Object.values(body.errors).flat().join(' ') : body.message);
            return body;
        }
        async function consent(value) {
            try { await send(@json(route('referrals.consent', $referral)), {consent_given: value}); location.reload(); }
            catch (error) { result.textContent = error.message; }
        }
        document.getElementById('consentYes')?.addEventListener('click', () => consent(true));
        document.getElementById('consentNo')?.addEventListener('click', () => consent(false));
        document.getElementById('identityForm')?.addEventListener('submit', async event => {
            event.preventDefault();
            const form = event.target, button = form.querySelector('button'); button.disabled = true;
            try { const body = await send(@json(route('identity.store', $referral)), Object.fromEntries(new FormData(form))); form.reset(); form.hidden = true; result.textContent = body.message; }
            catch (error) { result.textContent = error.message; button.disabled = false; }
        });
    })();
    </script>
</x-app-layout>
