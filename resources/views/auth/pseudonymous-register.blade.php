@extends('layouts.auth')
@section('container-width', 'max-w-5xl')
@section('title', 'Create Account - COMPASS')
@section('subtitle', 'Join COMPASS Peer Support System')

@section('content')
<h1 class="auth-title">Create your account</h1>
<p class="auth-intro">Read the terms and privacy notice, then verify your email and choose your account details.</p>
<ol class="auth-progress" aria-label="Registration progress">
    <li id="registration-progress-terms" @if(!$errors->any()) aria-current="step" @endif><span aria-hidden="true">1</span> Terms &amp; privacy</li>
    <li id="registration-progress-account" @if($errors->any()) aria-current="step" @endif><span aria-hidden="true">2</span> Verify &amp; create</li>
</ol>
@if ($errors->any())
    <div role="alert" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm">{{ $errors->first('registration') ?: ($errors->first('email') ?: 'Please check the highlighted fields below.') }}</div>
@endif

<!-- STEP 1: Terms and Condition + Privacy Notice (verbatim, consent first) -->
<div id="registrationStep1" class="{{ $errors->any() ? 'hidden' : '' }}">
    <section class="agreement-panel" aria-label="Terms and Condition and Privacy Notice">
        <h2 class="text-base font-semibold text-gray-800 mb-2">1. Terms and Condition &amp; Privacy Notice</h2>
        <p class="text-sm text-gray-600 mb-3">Please read the full documents below carefully before you create your account.</p>
        <div class="agreement-scroll" id="agreementScroll" tabindex="0" aria-label="Terms and privacy documents" aria-describedby="agreement-hint">
            @include('partials.terms-text')
            <hr class="my-4 border-gray-200">
            @include('partials.privacy-text')
        </div>
        <p id="agreement-hint" class="text-xs text-gray-400 mt-2">Scroll to the bottom of the documents to continue.</p>
        <label class="agreement-check">
            <input type="checkbox" id="agree-terms-checkbox" value="1" disabled>
            <span>I have read and understood the Terms and Condition and the Privacy Notice, and I agree to them.</span>
        </label>
        <p id="agree-error" class="text-sm text-red-600 mt-1" role="alert"></p>
        <button type="button" id="agree-continue" class="registration-action registration-action-solid" disabled><x-ui-icon name="check" class="mr-2" /> Agree and continue</button>
    </section>
</div>

<!-- STEP 2: Email verification + account details -->
<div id="registrationStep2" class="{{ $errors->any() ? '' : 'hidden' }}">
<div class="registration-columns">
<section class="verification-panel space-y-4">
    <h2 id="registration-account-heading" tabindex="-1" class="text-base font-semibold text-gray-800">2. Email verification</h2>
    @if(app(\App\Services\OtpMailConfiguration::class)->demoEnabled())<p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-xl p-3">Demo Mode: your generated code will be displayed here. No email delivery is required.</p>@endif
    <p class="text-sm text-gray-600">Verify your Gmail or other email address before creating your account.</p>
    <button type="button" id="open-verification" class="registration-action registration-action-solid">Verify email address</button>
    <p id="email-summary" class="text-sm text-green-700" role="status"></p>
</section>
<dialog id="email-dialog" aria-labelledby="verification-heading" class="email-dialog">
    <button type="button" id="close-verification" class="registration-action" aria-label="Close email verification">Close</button>
<section class="verification-panel space-y-4" aria-labelledby="verification-heading">
    <h2 id="verification-heading" class="text-base font-semibold text-gray-800">2. Email verification</h2>
    <div>
        <label for="verification-email" class="block text-sm font-medium text-gray-700 mb-1.5">Email for verification <span class="text-red-500">*</span></label>
        <div class="registration-input-action">
        <input id="verification-email" type="email" autocomplete="email" class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200" placeholder="Enter your email">
        <button id="send-code" type="button" class="registration-action registration-action-solid"><x-ui-icon name="send"  /> <span>Send OTP</span></button>
        </div>
        <p class="text-xs text-gray-500 mt-1">Used only to send your code. Sign in with your alias after registration.</p>
        @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="verification-code" class="block text-sm font-medium text-gray-700 mb-1.5">Verification code</label>
        <div class="registration-input-action">
        <input id="verification-code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200" placeholder="6-digit code">
        <button id="verify-code" type="button" class="registration-action registration-action-solid"><x-ui-icon name="check-circle"  /> Verify OTP</button>
        </div>
    </div>
    <p id="verification-status" role="status" aria-live="polite" class="text-sm text-gray-600">{{ session('registration_verified_until', 0) > now()->timestamp ? 'Email verified. You can now create your account.' : '' }}</p>
</section></dialog>
<form method="POST" action="{{ route('seeker.onboarding.store') }}" class="registration-fields">
    @csrf
    <h2 class="registration-full text-base font-semibold text-gray-800">3. Your profile</h2>
    <div class="registration-full">
        <label for="alias" class="block text-sm font-medium text-gray-700 mb-1.5">Your sign-in alias</label>
        <div class="registration-input-action">
        <input id="alias" name="alias" value="{{ session('registration_alias') }}" readonly required aria-describedby="nickname-reminder alias-status" class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 bg-green-50">
        <button id="shuffle-alias" type="button" class="registration-action"><x-ui-icon name="shuffle"  /> Shuffle alias</button>
        </div>
        <p id="nickname-reminder" class="nickname-reminder">Remember your nickname: you will use <strong>Nickname@compass.local</strong> to log in.</p>
        <p id="alias-status" role="status" class="text-xs text-gray-500">Shuffle until you find a nickname you like.</p>
        @error('alias') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="age" class="block text-sm font-medium text-gray-700 mb-1.5">Age <span class="text-red-500" aria-hidden="true">*</span></label>
        <input type="number" id="age" name="age" required class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 bg-white outline-none transition-all" aria-invalid="{{ $errors->has('age') ? 'true' : 'false' }}" @error('age') aria-describedby="age-error" @enderror min="13" max="60" value="{{ old('age') }}" placeholder="Enter your age (13-60)">
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
    <h2 class="registration-full registration-divider text-base font-semibold text-gray-800">4. Account security</h2>
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
        <button type="submit" class="btn-primary w-full py-3 rounded-xl text-white font-semibold transition-all"><x-ui-icon name="user-plus" class="mr-2" /> Create Account</button>
    </div>
    <input type="hidden" name="agree_privacy" value="1">
    <input type="hidden" name="agree_terms" value="1">
</form>
</div>
</div>
@endsection

@section('footer')
<p class="text-center text-sm text-gray-500">Already have an account? <a href="{{ route('login') }}" class="text-green-600 font-medium hover:underline">Sign in</a></p>
@endsection

@push('scripts')
<script>
(() => {
    // ── Step 1: Terms & Privacy gate ──
    const step1 = document.getElementById('registrationStep1');
    const step2 = document.getElementById('registrationStep2');
    const scrollBox = document.getElementById('agreementScroll');
    const agreeBox = document.getElementById('agree-terms-checkbox');
    const agreeBtn = document.getElementById('agree-continue');
    const agreeHint = document.getElementById('agreement-hint');
    const agreeError = document.getElementById('agree-error');
    if (step1 && step2 && scrollBox) {
        const atBottom = () => scrollBox.scrollTop + scrollBox.clientHeight >= scrollBox.scrollHeight - 24;
        scrollBox.addEventListener('scroll', () => {
            const reached = atBottom();
            agreeBox.disabled = !reached;
            if (agreeHint) agreeHint.textContent = reached ? 'You have reached the end of the documents.' : 'Scroll to the bottom of the documents to continue.';
            if (!reached) agreeBtn.disabled = true;
        });
        agreeBox.addEventListener('change', () => { agreeBtn.disabled = !agreeBox.checked; });
        agreeBtn.addEventListener('click', () => {
            if (!agreeBox.checked) { if (agreeError) agreeError.textContent = 'Please tick the agreement checkbox to continue.'; return; }
            if (agreeError) agreeError.textContent = '';
            step1.classList.add('hidden');
            step2.classList.remove('hidden');
            document.getElementById('registration-progress-terms').removeAttribute('aria-current');
            document.getElementById('registration-progress-account').setAttribute('aria-current', 'step');
            window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
            document.getElementById('registration-account-heading').focus({ preventScroll: true });
        });
    }
})();
</script>
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
        button.innerHTML = '<x-ui-icon name="user-plus" class="mr-2" /> Create Account';
    });
    async function post(url, data, button, output) {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {
                method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify(data)
            });
            const result = response.headers.get('content-type')?.includes('application/json')
                ? await response.json() : {message: 'Your session may have expired. Refresh the page and try again.'};
            if (result.retry_after) startCooldown(result.retry_after);
            if (response.status === 419) throw new Error('Your session expired or cookies are blocked. Refresh this page before sending again.');
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Please try again.');
            output.textContent = result.message || 'New alias generated.';
            if (output.id === 'verification-status') {
                output.classList.toggle('demo-code-visible', result.demo_mode === true);
            }
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
        status.textContent = 'Sending verification code...';
        const result = await post(@json(route('registration.otp.send', [], false)), {email: email.value.trim()}, this, status);
        if (result) { verifiedUntil = 0; updateSummary(); startCooldown(result.retry_after || 60); }
    });
    document.getElementById('verify-code').addEventListener('click', async function () {
        const result = await post(@json(route('registration.otp.verify', [], false)), {otp: document.getElementById('verification-code').value}, this, status);
        if (result) { verifiedUntil = result.verified_until; updateSummary(); dialog.close(); }
    });
    document.getElementById('shuffle-alias').addEventListener('click', async function () {
        const submit = form.querySelector('button[type="submit"]');
        const aliasStatus = document.getElementById('alias-status');
        submit.disabled = true;
        const result = await post(@json(route('registration.alias.shuffle', [], false)), {}, this, aliasStatus);
        if (result && typeof result.alias === 'string' && result.alias.length) {
            document.getElementById('alias').value = result.alias;
        } else if (result && !aliasStatus.textContent) {
            aliasStatus.textContent = 'Could not generate a new alias. Please try again.';
        }
        submit.disabled = false;
    });
})();
</script>
@endpush
