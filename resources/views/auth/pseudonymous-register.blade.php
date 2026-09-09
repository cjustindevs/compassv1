@extends('layouts.auth')
@section('container-width', 'max-w-5xl')
@section('title', 'Create Account - COMPASS')
@section('subtitle', 'Join COMPASS Peer Support System')

@section('content')
<h1 class="text-xl font-bold text-gray-800 mb-2">Create Your Account</h1>
<p class="text-gray-500 text-sm mb-6">Verify your email and choose a generated alias for signing in.</p>
@if ($errors->any())
    <div role="alert" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">{{ $errors->first('registration') ?: 'Please check the highlighted fields below.' }}</div>
@endif
<div class="registration-columns">
<section class="verification-panel space-y-4">
    <h2 class="text-base font-semibold text-gray-800">1. Email verification</h2>
    <p class="text-sm text-gray-600">Verify your Gmail or other email address before creating your account.</p>
    <button type="button" id="open-verification" class="registration-action registration-action-solid">Verify email address</button>
    <p id="email-summary" class="text-sm text-green-700" role="status"></p>
</section>
<dialog id="email-dialog" aria-labelledby="verification-heading" class="email-dialog">
    <button type="button" id="close-verification" class="registration-action" aria-label="Close email verification">Close</button>
<section class="verification-panel space-y-4" aria-labelledby="verification-heading">
    <h2 id="verification-heading" class="text-base font-semibold text-gray-800">1. Email verification</h2>
    <div>
        <label for="verification-email" class="block text-sm font-medium text-gray-700 mb-1.5">Email for verification <span class="text-red-500">*</span></label>
        <div class="registration-input-action">
        <input id="verification-email" type="email" autocomplete="email" class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200" placeholder="Enter your email">
        <button id="send-code" type="button" class="registration-action registration-action-solid"><i class="fas fa-paper-plane" aria-hidden="true"></i> <span>Send OTP</span></button>
        </div>
        <p class="text-xs text-gray-500 mt-1">Used only to send your code. Sign in with your alias after registration.</p>
        @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="verification-code" class="block text-sm font-medium text-gray-700 mb-1.5">Verification code</label>
        <div class="registration-input-action">
        <input id="verification-code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200" placeholder="6-digit code">
        <button id="verify-code" type="button" class="registration-action registration-action-solid"><i class="fas fa-check-circle" aria-hidden="true"></i> Verify OTP</button>
        </div>
    </div>
    <p id="verification-status" role="status" aria-live="polite" class="text-sm text-gray-600">{{ session('registration_verified_until', 0) > now()->timestamp ? 'Email verified. You can now create your account.' : '' }}</p>
</section></dialog>
<form method="POST" action="{{ route('seeker.onboarding.store') }}" class="registration-fields">
    @csrf
    <h2 class="registration-full text-base font-semibold text-gray-800">2. Your profile</h2>
    <div class="registration-full">
        <label for="alias" class="block text-sm font-medium text-gray-700 mb-1.5">Your sign-in alias</label>
        <div class="registration-input-action">
        <input id="alias" name="alias" value="{{ session('registration_alias') }}" readonly required class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 bg-green-50">
        <button id="shuffle-alias" type="button" class="registration-action"><i class="fas fa-shuffle" aria-hidden="true"></i> Shuffle alias</button>
        </div>
        <p id="alias-status" role="status" class="text-xs text-gray-500">Shuffle until you find an alias you like.</p>
        @error('alias') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="age" class="block text-sm font-medium text-gray-700 mb-1.5">Age <span class="text-red-500" aria-hidden="true">*</span></label>
        <input type="number" id="age" name="age" required class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 bg-white outline-none transition-all" aria-invalid="{{ $errors->has('age') ? 'true' : 'false' }}" @error('age') aria-describedby="age-error" @enderror min="13" max="99" value="{{ old('age') }}" placeholder="Enter your age (13-99)">
        @error('age') <p id="age-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="gender" class="block text-sm font-medium text-gray-700 mb-1.5">Gender Identity <span class="text-red-500" aria-hidden="true">*</span></label>
        <select id="gender" name="gender" required class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 bg-white outline-none transition-all" aria-invalid="{{ $errors->has('gender') ? 'true' : 'false' }}" @error('gender') aria-describedby="gender-error" @enderror>
            <option value="">Select your gender</option>
            <option value="male" @selected(old('gender') === 'male')>Male</option>
            <option value="female" @selected(old('gender') === 'female')>Female</option>
            <option value="non-binary" @selected(old('gender') === 'non-binary')>Non binary</option>
            <option value="prefer-not-to-say" @selected(old('gender') === 'prefer-not-to-say')>Prefer not to say</option>
        </select>
        @error('gender') <p id="gender-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div class="registration-full">
        <label for="preferred_language" class="block text-sm font-medium text-gray-700 mb-1.5">Preferred Language <span class="text-red-500" aria-hidden="true">*</span></label>
        <select id="preferred_language" name="preferred_language" required class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 bg-white outline-none transition-all" aria-invalid="{{ $errors->has('preferred_language') ? 'true' : 'false' }}" @error('preferred_language') aria-describedby="preferred_language-error" @enderror>
            <option value="English" @selected(old('preferred_language', 'English') === 'English')>English</option>
            <option value="Tagalog" @selected(old('preferred_language', 'English') === 'Tagalog')>Tagalog</option>
            <option value="English/Tagalog" @selected(old('preferred_language', 'English') === 'English/Tagalog')>English/Tagalog</option>
        </select>
        @error('preferred_language') <p id="preferred_language-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <h2 class="registration-full registration-divider text-base font-semibold text-gray-800">3. Account security</h2>
    <div>
        <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password <span class="text-red-500" aria-hidden="true">*</span></label>
        <input type="password" id="password" name="password" required class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 bg-white outline-none transition-all" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" aria-describedby="password-help{{ $errors->has('password') ? ' password-error' : '' }}" minlength="8" autocomplete="new-password" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^a-zA-Z0-9]).{8,}" placeholder="Create a secure password">
        <p id="password-help" class="mt-1.5 text-xs text-gray-500">Use at least 8 characters, with uppercase and lowercase letters, a number, and a symbol.</p>
        @error('password') <p id="password-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password <span class="text-red-500" aria-hidden="true">*</span></label>
        <input type="password" id="password_confirmation" name="password_confirmation" required class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 bg-white outline-none transition-all" aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}" @error('password_confirmation') aria-describedby="password_confirmation-error" @enderror minlength="8" autocomplete="new-password" placeholder="Confirm your password">
        @error('password_confirmation') <p id="password_confirmation-error" class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div class="registration-full pt-2">
        <button type="submit" class="btn-primary w-full py-3 rounded-xl text-white font-semibold transition-all"><i class="fas fa-user-plus mr-2" aria-hidden="true"></i> Create Account</button>
    </div>
</form>
</div>
@endsection

@section('footer')
<p class="text-center text-sm text-gray-500">Already have an account? <a href="{{ route('login') }}" class="text-green-600 font-medium hover:underline">Sign in</a></p>
@endsection

@push('scripts')
<script>
(() => {
    const status = document.getElementById('verification-status');
    const dialog = document.getElementById('email-dialog');
    let verifiedUntil = @json(session('registration_verified_until', 0));
    const updateSummary = () => document.getElementById('email-summary').textContent = verifiedUntil > Date.now() / 1000 ? 'Email verified. Ready to create your account.' : 'Email verification required.';
    updateSummary();
    document.getElementById('open-verification').addEventListener('click', () => dialog.showModal());
    document.getElementById('close-verification').addEventListener('click', () => dialog.close());
    const form = document.querySelector('.registration-fields');
    form.addEventListener('submit', (event) => {
        if (verifiedUntil <= Date.now() / 1000) {
            event.preventDefault();
            status.textContent = 'Please verify your email before creating your account.';
            dialog.showModal();
            return;
        }
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Creating account...';
    });
    window.addEventListener('pageshow', () => {
        const button = form.querySelector('button[type="submit"]');
        button.disabled = false;
        button.innerHTML = '<i class="fas fa-user-plus mr-2" aria-hidden="true"></i> Create Account';
    });
    async function post(url, data, button, output) {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {
                method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify(data)
            });
            const result = response.headers.get('content-type')?.includes('application/json')
                ? await response.json() : {message: 'Your session may have expired. Refresh the page and try again.'};
            if (result.retry_after) startCooldown(result.retry_after);
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Please try again.');
            output.textContent = result.message || 'New alias generated.';
            return result;
        } catch (error) {
            output.textContent = error.message || 'Unable to connect. Please try again.';
        } finally { button.disabled = button.id === 'send-code' && Number(button.dataset.cooldownUntil || 0) > Date.now(); button.removeAttribute('aria-busy'); }
    }
    let cooldownTimer;
    function startCooldown(seconds) {
        const button = document.getElementById('send-code');
        const deadline = Date.now() + seconds * 1000;
        button.dataset.cooldownUntil = deadline;
        clearInterval(cooldownTimer);
        const tick = () => {
            const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
            button.disabled = remaining > 0;
            button.querySelector('span').textContent = remaining ? `Resend in ${remaining}s` : 'Resend OTP';
            if (!remaining) clearInterval(cooldownTimer);
        };
        cooldownTimer = setInterval(tick, 1000);
        tick();
    }
    document.getElementById('send-code').addEventListener('click', async function () {
        const email = document.getElementById('verification-email');
        if (!email.value || !email.reportValidity()) { email.focus(); return; }
        const result = await post(@json(route('registration.otp.send')), {email: email.value}, this, status);
        if (result) { verifiedUntil = 0; updateSummary(); startCooldown(result.retry_after || 60); }
    });
    document.getElementById('verify-code').addEventListener('click', async function () {
        const result = await post(@json(route('registration.otp.verify')), {otp: document.getElementById('verification-code').value}, this, status);
        if (result) { verifiedUntil = result.verified_until; updateSummary(); dialog.close(); }
    });
    document.getElementById('shuffle-alias').addEventListener('click', async function () {
        const submit = document.querySelector('button[type="submit"]');
        submit.disabled = true;
        const result = await post(@json(route('registration.alias.shuffle')), {}, this, document.getElementById('alias-status'));
        if (result) document.getElementById('alias').value = result.alias;
        submit.disabled = false;
    });
})();
</script>
@endpush

@push('styles')
<style>
    .email-dialog { width: min(480px, calc(100% - 32px)); max-height: calc(100dvh - 32px); overflow-y: auto; border: 0; border-radius: 20px; padding: 20px; }
    .email-dialog::backdrop { background: rgba(15, 23, 42, .5); }
    .email-dialog > button { margin: 0 0 12px auto; display: flex; }
    .registration-divider { border-top: 1px solid #e5e7eb; padding-top: 16px; margin-top: 4px; }
    .registration-input-action { display: flex; align-items: stretch; gap: 8px; }
    .registration-input-action input { min-width: 0; flex: 1; width: 0; }
    .registration-input-action .registration-action { margin: 0; width: auto; flex-shrink: 0; }
    .registration-fields button[type="submit"]:disabled { opacity: .6; cursor: wait; }
    @media (max-width: 479px) { .registration-input-action { flex-direction: column; } .registration-input-action input { width: 100%; } }
    .registration-columns { display: grid; grid-template-columns: minmax(0, 1fr); gap: 24px; }
    .verification-panel { padding: 20px; background: #f0fdf4; border: 1px solid #dcfce7; border-radius: 16px; align-self: start; }
    .registration-fields { display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; align-content: start; }
    .registration-fields > div { min-width: 0; }
    .registration-full { grid-column: 1 / -1; }
    .registration-action { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 44px; margin-top: 10px; padding: 10px 16px; border: 1px solid #16a34a; border-radius: 10px; background: white; color: #15803d; font-size: 14px; font-weight: 600; cursor: pointer; }
    .registration-action:hover { background: #dcfce7; }
    .registration-action-solid { background: #15803d; color: white; border-color: #15803d; width: 100%; }
    .registration-action-solid:hover { background: #166534; }
    .registration-action:focus-visible { outline: 3px solid #22c55e; outline-offset: 3px; }
    .registration-action:disabled { opacity: .6; cursor: wait; }
    #alias-status { margin-top: 8px; }
    @media (min-width: 640px) { .registration-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 900px) { .registration-columns { grid-template-columns: minmax(0, 1fr) minmax(0, 1.65fr); gap: 32px; } }
    @media (max-width: 639px) { .registration-action { width: 100%; } }
</style>
@endpush
