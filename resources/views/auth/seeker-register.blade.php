<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .gradient-bg { background: linear-gradient(135deg, #ECFDF5 0%, #DCFCE7 50%, #FFFFFF 100%); }
        .btn-primary {
            background: linear-gradient(135deg, #16A34A, #22C55E);
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(22, 163, 74, 0.3);
        }
        .btn-primary:hover { transform: scale(1.02); box-shadow: 0 8px 40px rgba(22, 163, 74, 0.4); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-secondary {
            background: #F3F4F6;
            color: #4B5563;
            transition: all 0.3s ease;
        }
        .btn-secondary:hover { background: #E5E7EB; transform: scale(1.02); }
        .input-focus:focus { border-color: #16A34A; box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15); }
        .step-active { background: #16A34A; color: white; border-color: #16A34A; }
        .step-done { background: #DCFCE7; color: #16A34A; border-color: #16A34A; }
        .step-inactive { background: #F3F4F6; color: #9CA3AF; border-color: #E5E7EB; }
        .fade-enter { animation: fadeIn 0.4s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .otp-digit { width: 48px; height: 56px; text-align: center; font-size: 24px; font-weight: 700; border: 2px solid #E5E7EB; border-radius: 12px; outline: none; transition: border-color 0.2s; background: #FAFAFA; }
        .otp-digit:focus { border-color: #16A34A; box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.15); background: white; }
        .otp-digit.filled { border-color: #16A34A; background: #ECFDF5; }
        .card-shadow { box-shadow: 0 20px 60px rgba(0,0,0,0.06); }
        .otp-container { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .modal-overlay { position: fixed; inset: 0; z-index: 999; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,0.4); backdrop-filter: blur(4px); }
        .modal-overlay.active { display: flex; }
        .modal-box { background: white; border-radius: 24px; max-width: 560px; width: 92%; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 40px 80px rgba(0,0,0,0.2); animation: modalSlide 0.3s ease-out; }
        @keyframes modalSlide { from { transform: scale(0.95) translateY(20px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }
        .modal-body { padding: 28px 32px; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 16px 32px; border-top: 1px solid #E5E7EB; background: #F9FAFB; border-radius: 0 0 24px 24px; display: flex; justify-content: flex-end; gap: 12px; }
        .consent-text { font-size: 14px; line-height: 1.7; color: #4B5563; }
        .consent-text h3 { color: #1F2937; font-weight: 700; margin-top: 16px; margin-bottom: 8px; }
        .consent-text h3:first-child { margin-top: 0; }
        .consent-text ul { padding-left: 20px; margin: 8px 0 12px; }
        .consent-text li { margin-bottom: 4px; }
        .consent-scroll-indicator { text-align: center; padding: 8px; color: #9CA3AF; font-size: 12px; border-top: 1px dashed #E5E7EB; margin: 12px -32px 0; }
        .step-content { min-height: 300px; }
        .consent-step-text { max-height: 400px; overflow-y: auto; padding-right: 8px; }
        .consent-step-text::-webkit-scrollbar { width: 6px; }
        .consent-step-text::-webkit-scrollbar-track { background: #F3F4F6; border-radius: 4px; }
        .consent-step-text::-webkit-scrollbar-thumb { background: #16A34A; border-radius: 4px; }

        /* ─── Mobile responsiveness ─── */
        @media (max-width: 640px) {
            .otp-digit { width: 40px; height: 48px; font-size: 20px; border-radius: 10px; }
            .otp-container { gap: 6px; }
            .modal-box { width: 100%; max-height: 94vh; border-radius: 18px; }
            .modal-body { padding: 18px 20px; }
            .modal-footer { padding: 12px 16px; border-radius: 0 0 18px 18px; flex-wrap: wrap; }
            .step-content { min-height: 260px; }
            .consent-step-text { max-height: 320px; }
            .consent-text { font-size: 13px; line-height: 1.65; }
            .consent-scroll-indicator { margin: 12px -20px 0; }
            #step1Indicator, #step2Indicator, #step3Indicator, #step4Indicator {
                width: 28px; height: 28px; font-size: 12px;
            }
        }

        @media (max-width: 400px) {
            body { overflow-x: hidden; }
            .otp-digit { width: 34px; height: 40px; font-size: 18px; border-radius: 8px; }
            .otp-container { gap: 4px; }
            .modal-body { padding: 16px; }
            .step-content { min-height: 240px; }
            .consent-step-text { max-height: 250px; }
            .consent-text { font-size: 12.5px; line-height: 1.6; }
            .consent-scroll-indicator { margin: 12px -16px 0; }
            #step1Indicator, #step2Indicator, #step3Indicator, #step4Indicator {
                width: 24px; height: 24px; font-size: 11px; border-width: 1.5px;
            }
            .otp-digit { -webkit-appearance: none; -moz-appearance: textfield; appearance: textfield; }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center py-6 md:py-12">

    <div class="w-full max-w-lg mx-3 sm:mx-4">

        <!-- Logo -->
        <div class="text-center mb-5 md:mb-8">
            <div class="inline-flex items-center gap-2.5">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-400 to-emerald-600 flex items-center justify-center shadow-lg shadow-green-500/20">
                    <span class="text-white font-extrabold text-xl">C</span>
                </div>
                <span class="text-xl sm:text-2xl font-extrabold text-gray-800">COMPASS</span>
            </div>
            <p class="text-gray-500 text-xs sm:text-sm mt-1">Create your anonymous account</p>
        </div>

        <!-- Card -->
        <div class="bg-white rounded-2xl card-shadow overflow-hidden">
            <div class="p-5 md:p-8">

                <!-- Progress Steps -->
                <div class="flex items-center gap-2 md:gap-3 mb-6">
                    <div id="step1Indicator" class="step-active w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold text-white transition-all duration-300 border-2 border-green-500">1</div>
                    <div class="flex-1 h-0.5 bg-gray-200" id="line1"></div>
                    <div id="step2Indicator" class="step-inactive w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold border-2 transition-all duration-300">2</div>
                    <div class="flex-1 h-0.5 bg-gray-200" id="line2"></div>
                    <div id="step3Indicator" class="step-inactive w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold border-2 transition-all duration-300">3</div>
                    <div class="flex-1 h-0.5 bg-gray-200" id="line3"></div>
                    <div id="step4Indicator" class="step-inactive w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold border-2 transition-all duration-300">4</div>
                </div>

                <!-- ============================================ -->
                <!-- STEP 1: Email + OTP                         -->
                <!-- ============================================ -->
                <div id="step1" class="step-content fade-enter">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-envelope text-green-600 text-lg"></i>
                        <h2 class="text-lg sm:text-xl font-bold text-gray-800">Sign in to Compass</h2>
                    </div>
                    <p class="text-gray-500 text-sm mt-1">Enter your university email to receive a verification code.</p>

                    <form id="emailForm" class="mt-6">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                            <input type="email" id="email" name="email"
                                   class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 outline-none transition-all"
                                   placeholder="you@university.edu" required>
                            <p id="emailError" class="text-red-500 text-sm mt-1 hidden"></p>
                            <p id="emailSuccess" class="text-green-500 text-sm mt-1 hidden"></p>
                        </div>
                        <button type="submit" id="sendOtpBtn"
                                class="btn-primary w-full mt-4 py-3 rounded-xl text-white font-semibold transition-all">
                            <i class="fas fa-paper-plane mr-2"></i> Send Verification Code
                        </button>
                        <p class="text-xs text-gray-400 text-center mt-3">
                            <i class="fas fa-lock text-green-500 mr-1"></i> We respect your privacy
                        </p>
                    </form>

                    <!-- OTP Section (hidden initially) -->
                    <div id="otpSection" class="hidden mt-4 fade-enter">
                        <div class="flex items-center gap-2 mb-2">
                            <i class="fas fa-inbox text-green-600 text-lg"></i>
                            <h3 class="font-semibold text-gray-800">Check your inbox</h3>
                        </div>
                        <p class="text-sm text-gray-500 mb-3">
                            We sent a 6-digit code to <span id="otpEmailDisplay" class="font-medium text-gray-700"></span>
                        </p>

                        <div class="otp-container" id="otpContainer">
                            <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric" autofocus>
                            <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                            <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                            <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                            <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                            <input type="text" class="otp-digit" maxlength="1" pattern="[0-9]" inputmode="numeric">
                        </div>

                        <p id="otpStepError" class="text-red-500 text-sm mt-2 hidden"></p>
                        <div id="otpStepSuccess" class="hidden mt-3 p-3 bg-green-50 rounded-xl border border-green-200 text-green-700 text-sm">
                            <i class="fas fa-check-circle text-green-500 mr-2"></i> Email verified! Proceeding to registration...
                        </div>
                        <button id="resendOtpBtn" class="text-green-600 text-sm mt-2 hover:underline hidden">
                            <i class="fas fa-redo mr-1"></i> Resend Code
                        </button>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- STEP 2: Alias + Age + Gender + Password     -->
                <!-- ============================================ -->
                <div id="step2" class="step-content hidden">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-user-plus text-green-600 text-lg"></i>
                        <h2 class="text-lg sm:text-xl font-bold text-gray-800">Create Your Account</h2>
                    </div>
                    <p class="text-gray-500 text-sm mt-1">Choose an anonymous alias and provide basic information.</p>

                    <form id="registerForm" class="mt-6">
                        @csrf
                        <input type="hidden" id="verificationToken" name="verification_token" value="">

                        <!-- Alias -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Your Alias</label>
                            <div class="flex gap-3">
                                <input type="text" id="alias" name="alias"
                                       class="input-focus flex-1 px-4 py-3 rounded-xl border border-gray-200 outline-none transition-all"
                                       readonly required>
                                <button type="button" id="generateAliasBtn"
                                        class="px-4 py-3 rounded-xl border border-gray-200 hover:border-green-400 hover:bg-green-50 transition-all">
                                    <i class="fas fa-shuffle text-green-600"></i>
                                </button>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Your alias protects your identity</p>
                            <p id="aliasError" class="text-red-500 text-sm mt-1 hidden"></p>
                        </div>

                        <!-- Age & Gender -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Age</label>
                                <input type="number" id="age" name="age" min="13" max="99"
                                       class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 outline-none transition-all"
                                       placeholder="18" required>
                                <p id="ageError" class="text-red-500 text-sm mt-1 hidden"></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Gender</label>
                                <select id="gender" name="gender"
                                        class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 outline-none transition-all bg-white" required>
                                    <option value="">Select</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Non-binary">Non-binary</option>
                                    <option value="Prefer not to say">Prefer not to say</option>
                                </select>
                                <p id="genderError" class="text-red-500 text-sm mt-1 hidden"></p>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                            <input type="password" id="password" name="password"
                                   class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 outline-none transition-all"
                                   placeholder="Min 6 characters" required>
                            <p id="passwordError" class="text-red-500 text-sm mt-1 hidden"></p>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm Password</label>
                            <input type="password" id="password_confirmation" name="password_confirmation"
                                   class="input-focus w-full px-4 py-3 rounded-xl border border-gray-200 outline-none transition-all"
                                   placeholder="Confirm password" required>
                        </div>

                        <button type="button" id="nextToConsentBtn"
                                class="btn-primary w-full py-3 rounded-xl text-white font-semibold transition-all">
                            <i class="fas fa-arrow-right mr-2"></i> Next: Review & Consent
                        </button>
                    </form>
                </div>

                <!-- ============================================ -->
                <!-- STEP 3: Informed Consent + Privacy Notice    -->
                <!-- ============================================ -->
                <div id="step3" class="step-content hidden">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fas fa-shield-alt text-green-600 text-lg"></i>
                        <h2 class="text-lg sm:text-xl font-bold text-gray-800">Privacy Notice &amp; Informed Consent</h2>
                    </div>
                    <p class="text-gray-500 text-sm mt-1">Please read the full document below before proceeding.</p>

                    <div class="mt-4 p-4 bg-gray-50 rounded-xl border border-gray-200">
                        <div class="consent-step-text" id="consentTextContainer">
                            <div class="consent-text">
                                <h3>📋 Privacy Notice</h3>
                                <p>COMPASS is committed to protecting your privacy in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173).</p>
                                <ul>
                                    <li>✅ Your identity is protected using a <strong>pseudonymous alias</strong>. Helpers only see your alias.</li>
                                    <li>✅ Your <strong>personal information</strong> (name, email, contact) is stored separately in an <strong>Identity Vault</strong> and only accessed with your consent or in emergencies.</li>
                                    <li>✅ Session records are <strong>encrypted</strong> and accessible only to authorized personnel.</li>
                                    <li>✅ Voice recordings require <strong>separate explicit consent</strong> and are deleted after 12 months.</li>
                                    <li>✅ You may <strong>withdraw consent</strong> at any time.</li>
                                </ul>

                                <h3>📋 Informed Consent</h3>
                                <p>By proceeding, you agree to participate in COMPASS, a peer-support platform designed to provide emotional support through trained psychology student helpers.</p>
                                <ul>
                                    <li>🔹 This service is <strong>NOT</strong> a form of professional counseling, psychotherapy, diagnosis, or treatment.</li>
                                    <li>🔹 The listener is a <strong>trained psychology student helper</strong> under faculty supervision.</li>
                                    <li>🔹 Your participation is <strong>voluntary</strong> — you may end the session at any time.</li>
                                    <li>🔹 All conversations are <strong>confidential</strong>.</li>
                                    <li>🔹 In emergencies, authorized personnel may access information to <strong>protect life or safety</strong>.</li>
                                    <li>🔹 Live sessions are available <strong>6:00 PM – 11:00 PM (PHT)</strong>, Monday to Saturday.</li>
                                    <li>🔹 Sessions are limited to <strong>90 minutes</strong> to ensure equitable access.</li>
                                    <li>🔹 Abusive, threatening, or harassing behavior may result in <strong>termination of access</strong>.</li>
                                </ul>

                                <p style="margin-top: 12px; font-weight: 600; color: #14532D;">
                                    ⚠️ If you are in immediate danger, please use the Emergency Button or contact local crisis hotlines immediately.
                                </p>
                            </div>
                            <div id="consentScrollIndicator" class="consent-scroll-indicator">
                                ⬇️ Please scroll to the bottom to continue
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 mt-4">
                        <button id="backToStep2Btn" class="w-full sm:flex-1 py-3 rounded-xl btn-secondary font-semibold transition-all">
                            <i class="fas fa-arrow-left mr-2"></i> Back
                        </button>
                        <button id="agreeAndSubmitBtn" class="w-full sm:flex-1 py-3 rounded-xl btn-primary text-white font-semibold transition-all disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-check mr-2"></i> I Agree &amp; Create Account
                        </button>
                    </div>
                    <p id="consentStepError" class="text-red-500 text-sm mt-2 hidden"></p>
                </div>

                <!-- ============================================ -->
                <!-- STEP 4: Success                              -->
                <!-- ============================================ -->
                <div id="step4" class="step-content hidden text-center py-8">
                    <div class="w-20 h-20 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check text-3xl text-green-600"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-800">Welcome to COMPASS!</h2>
                    <p class="text-gray-500 mt-2">Your anonymous account has been created.</p>
                    <p class="text-sm text-gray-400 mt-1">Alias: <span id="successAlias" class="font-semibold text-green-600"></span></p>
                    <a href="{{ route('login') }}" class="btn-primary inline-block w-full sm:w-auto text-center mt-6 px-8 py-3 rounded-xl text-white font-semibold transition-all">
                        <i class="fas fa-sign-in-alt mr-2"></i> Go to Login
                    </a>
                </div>

            </div>

            <!-- Footer -->
            <div class="border-t border-gray-100 px-6 md:px-8 py-4 bg-gray-50/50">
                <p class="text-center text-sm text-gray-500">
                    Already have an account?
                    <a href="{{ route('login') }}" class="text-green-600 font-medium hover:underline">Login</a>
                </p>
            </div>
        </div>

        <p class="text-center text-xs text-gray-400 mt-6">
            <i class="fas fa-shield-alt text-green-500 mr-1"></i>
            100% Confidential · Anonymous · Secure
        </p>
    </div>

    <!-- ─── JAVASCRIPT ─── -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            // ── DOM Refs ──
            const step1 = document.getElementById('step1');
            const step2 = document.getElementById('step2');
            const step3 = document.getElementById('step3');
            const step4 = document.getElementById('step4');
            const step1Ind = document.getElementById('step1Indicator');
            const step2Ind = document.getElementById('step2Indicator');
            const step3Ind = document.getElementById('step3Indicator');
            const step4Ind = document.getElementById('step4Indicator');
            const line1 = document.getElementById('line1');
            const line2 = document.getElementById('line2');
            const line3 = document.getElementById('line3');

            const emailForm = document.getElementById('emailForm');
            const emailInput = document.getElementById('email');
            const sendOtpBtn = document.getElementById('sendOtpBtn');
            const emailError = document.getElementById('emailError');
            const emailSuccess = document.getElementById('emailSuccess');

            const otpSection = document.getElementById('otpSection');
            const otpDigits = document.querySelectorAll('.otp-digit');
            const otpError = document.getElementById('otpStepError');
            const otpSuccess = document.getElementById('otpStepSuccess');
            const otpEmailDisplay = document.getElementById('otpEmailDisplay');
            const resendOtpBtn = document.getElementById('resendOtpBtn');

            const registerForm = document.getElementById('registerForm');
            const aliasInput = document.getElementById('alias');
            const generateAliasBtn = document.getElementById('generateAliasBtn');
            const ageInput = document.getElementById('age');
            const genderSelect = document.getElementById('gender');
            const passwordInput = document.getElementById('password');
            const passwordConfirm = document.getElementById('password_confirmation');
            const verificationToken = document.getElementById('verificationToken');
            const nextToConsentBtn = document.getElementById('nextToConsentBtn');

            const consentTextContainer = document.getElementById('consentTextContainer');
            const consentScrollIndicator = document.getElementById('consentScrollIndicator');
            const agreeAndSubmitBtn = document.getElementById('agreeAndSubmitBtn');
            const backToStep2Btn = document.getElementById('backToStep2Btn');
            const consentStepError = document.getElementById('consentStepError');

            const successAlias = document.getElementById('successAlias');

            const aliasError = document.getElementById('aliasError');
            const ageError = document.getElementById('ageError');
            const genderError = document.getElementById('genderError');
            const passwordError = document.getElementById('passwordError');

            let currentStep = 1;
            let currentEmail = '';
            let consentAgreed = false;

            // ── Update steps UI ──
            function updateSteps(step) {
                const steps = [step1Ind, step2Ind, step3Ind, step4Ind];
                const contents = [step1, step2, step3, step4];
                const lines = [line1, line2, line3];

                steps.forEach((el, i) => {
                    const num = i + 1;
                    el.className = 'w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold border-2 transition-all duration-300';
                    if (num < step) {
                        el.classList.add('step-done');
                        el.textContent = '✓';
                    } else if (num === step) {
                        el.classList.add('step-active');
                        el.textContent = num;
                    } else {
                        el.classList.add('step-inactive');
                        el.textContent = num;
                    }
                });

                contents.forEach((el, i) => {
                    el.classList.toggle('hidden', i + 1 !== step);
                    if (i + 1 === step) {
                        el.classList.add('fade-enter');
                        setTimeout(() => el.classList.remove('fade-enter'), 500);
                    }
                });

                lines.forEach((el, i) => {
                    if (i + 1 < step) {
                        el.className = 'flex-1 h-0.5 bg-green-500 transition-all duration-300';
                    } else {
                        el.className = 'flex-1 h-0.5 bg-gray-200 transition-all duration-300';
                    }
                });

                currentStep = step;
            }

            // ── Helpers ──
            function showError(el, msg) {
                el.textContent = msg;
                el.classList.remove('hidden');
            }
            function hideError(el) {
                el.classList.add('hidden');
            }
            function showSuccess(el, msg) {
                el.textContent = msg;
                el.classList.remove('hidden');
            }
            function hideSuccess(el) {
                el.classList.add('hidden');
            }

            // ── Generate Alias ──
            function generateAlias() {
                fetch('/api/generate-alias')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            aliasInput.value = data.alias;
                            hideError(aliasError);
                        }
                    })
                    .catch(() => showError(aliasError, 'Failed to generate alias.'));
            }
            generateAliasBtn.addEventListener('click', generateAlias);

            // ── OTP Input Logic ──
            otpDigits.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    const val = this.value.replace(/\D/g, '');
                    this.value = val;
                    if (val.length === 1) {
                        this.classList.add('filled');
                        if (index < otpDigits.length - 1) {
                            otpDigits[index + 1].focus();
                        } else {
                            this.blur();
                            verifyOTP();
                        }
                    } else {
                        this.classList.remove('filled');
                    }
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        otpDigits[index - 1].focus();
                        otpDigits[index - 1].classList.remove('filled');
                    }
                    if (e.key === 'v' && (e.ctrlKey || e.metaKey)) return;
                    if (!/^[0-9]$/.test(e.key) && e.key !== 'Backspace' && e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' && e.key !== 'Tab') {
                        e.preventDefault();
                    }
                });

                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                    if (paste.length === 0) return;
                    const digits = paste.slice(0, 6).split('');
                    otpDigits.forEach((digitInput, i) => {
                        if (i < digits.length) {
                            digitInput.value = digits[i];
                            digitInput.classList.add('filled');
                        } else {
                            digitInput.value = '';
                            digitInput.classList.remove('filled');
                        }
                    });
                    const nextIndex = Math.min(digits.length, otpDigits.length - 1);
                    otpDigits[nextIndex].focus();
                    if (digits.length === 6) {
                        otpDigits[5].blur();
                        verifyOTP();
                    }
                });
            });

            // ── Verify OTP ──
            function verifyOTP() {
                const otp = Array.from(otpDigits).map(d => d.value).join('');
                if (otp.length !== 6) {
                    showError(otpError, 'Please enter the full 6-digit code.');
                    return;
                }

                hideError(otpError);
                otpSuccess.classList.add('hidden');

                fetch('/api/verify-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ otp })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        otpSuccess.classList.remove('hidden');
                        verificationToken.value = data.token;
                        resendOtpBtn.classList.add('hidden');
                        setTimeout(() => {
                            updateSteps(2);
                            generateAlias(); // Auto-generate alias when step 2 appears
                        }, 800);
                    } else {
                        showError(otpError, data.message || 'Invalid code.');
                        resendOtpBtn.classList.remove('hidden');
                        otpDigits.forEach(d => { d.value = ''; d.classList.remove('filled'); });
                        otpDigits[0].focus();
                    }
                })
                .catch(() => {
                    showError(otpError, 'Network error. Please try again.');
                });
            }

            // ── Resend OTP ──
            resendOtpBtn.addEventListener('click', function() {
                if (!currentEmail) return;

                resendOtpBtn.disabled = true;
                resendOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Sending...';

                fetch('/api/resend-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ email: currentEmail })
                })
                .then(res => res.json())
                .then(data => {
                    resendOtpBtn.disabled = false;
                    resendOtpBtn.innerHTML = '<i class="fas fa-redo mr-1"></i> Resend Code';
                    if (data.success) {
                        showSuccess(emailSuccess, data.message);
                        hideError(otpError);
                        otpDigits.forEach(d => { d.value = ''; d.classList.remove('filled'); });
                        otpDigits[0].focus();
                    } else {
                        showError(otpError, data.message || 'Failed to resend.');
                    }
                })
                .catch(() => {
                    resendOtpBtn.disabled = false;
                    resendOtpBtn.innerHTML = '<i class="fas fa-redo mr-1"></i> Resend Code';
                    showError(otpError, 'Network error.');
                });
            });

            // ── Step 1: Send OTP ──
            emailForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const email = emailInput.value.trim();
                if (!email) {
                    showError(emailError, 'Please enter your email address.');
                    return;
                }

                sendOtpBtn.disabled = true;
                sendOtpBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
                hideError(emailError);
                hideSuccess(emailSuccess);

                fetch('/api/send-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ email })
                })
                .then(res => res.json())
                .then(data => {
                    sendOtpBtn.disabled = false;
                    sendOtpBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Send Verification Code';

                    if (data.success) {
                        showSuccess(emailSuccess, data.message);
                        currentEmail = email;
                        otpSection.classList.remove('hidden');
                        otpEmailDisplay.textContent = email;
                        otpDigits.forEach(d => { d.value = ''; d.classList.remove('filled'); });
                        otpError.classList.add('hidden');
                        otpSuccess.classList.add('hidden');
                        resendOtpBtn.classList.add('hidden');
                        setTimeout(() => otpDigits[0].focus(), 100);
                    } else {
                        showError(emailError, data.message || 'Failed to send code.');
                    }
                })
                .catch(() => {
                    sendOtpBtn.disabled = false;
                    sendOtpBtn.innerHTML = '<i class="fas fa-paper-plane mr-2"></i> Send Verification Code';
                    showError(emailError, 'Network error. Please try again.');
                });
            });

            // ── Validate Step 2 fields ──
            function validateStep2() {
                const alias = aliasInput.value.trim();
                const age = ageInput.value;
                const gender = genderSelect.value;
                const password = passwordInput.value;
                const passwordConf = passwordConfirm.value;

                let valid = true;
                if (!alias) { showError(aliasError, 'Please generate an alias.'); valid = false; } else { hideError(aliasError); }
                if (!age || age < 13 || age > 99) { showError(ageError, 'Enter a valid age (13-99).'); valid = false; } else { hideError(ageError); }
                if (!gender) { showError(genderError, 'Please select your gender.'); valid = false; } else { hideError(genderError); }
                if (!password || password.length < 6) { showError(passwordError, 'Password must be at least 6 characters.'); valid = false; } else if (password !== passwordConf) { showError(passwordError, 'Passwords do not match.'); valid = false; } else { hideError(passwordError); }

                return valid;
            }

            // ── Next to Consent Step ──
            nextToConsentBtn.addEventListener('click', function() {
                if (!validateStep2()) return;
                if (!verificationToken.value) {
                    showError(aliasError, 'Email verification required. Please complete step 1.');
                    return;
                }
                // Move to step 3
                updateSteps(3);
                // Reset consent state
                consentAgreed = false;
                agreeAndSubmitBtn.disabled = true;
                consentStepError.classList.add('hidden');
                // Reset scroll indicator
                consentTextContainer.scrollTop = 0;
                consentScrollIndicator.style.display = 'block';
                agreeAndSubmitBtn.className = 'w-full sm:flex-1 py-3 rounded-xl btn-primary text-white font-semibold transition-all disabled:opacity-50 disabled:cursor-not-allowed';
                agreeAndSubmitBtn.disabled = true;
            });

            // ── Consent scroll detection ──
            consentTextContainer.addEventListener('scroll', function() {
                const scrollTop = this.scrollTop;
                const scrollHeight = this.scrollHeight;
                const clientHeight = this.clientHeight;
                const atBottom = scrollTop + clientHeight >= scrollHeight - 10;

                if (atBottom) {
                    consentScrollIndicator.style.display = 'none';
                    agreeAndSubmitBtn.disabled = false;
                    agreeAndSubmitBtn.className = 'w-full sm:flex-1 py-3 rounded-xl btn-primary text-white font-semibold transition-all';
                } else {
                    consentScrollIndicator.style.display = 'block';
                    agreeAndSubmitBtn.disabled = true;
                    agreeAndSubmitBtn.className = 'w-full sm:flex-1 py-3 rounded-xl btn-primary text-white font-semibold transition-all disabled:opacity-50 disabled:cursor-not-allowed';
                }
            });

            // ── Back to Step 2 ──
            backToStep2Btn.addEventListener('click', function() {
                updateSteps(2);
            });

            // ── Submit Registration (from Step 3) ──
            agreeAndSubmitBtn.addEventListener('click', function() {
                // Disable button to prevent double submission
                agreeAndSubmitBtn.disabled = true;
                agreeAndSubmitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Creating Account...';

                const data = {
                    alias: aliasInput.value,
                    email: emailInput.value,
                    age: ageInput.value,
                    gender: genderSelect.value,
                    password: passwordInput.value,
                    password_confirmation: passwordConfirm.value,
                    consent: 1,
                    verification_token: verificationToken.value
                };

                fetch('/api/register-seeker', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(data)
                })
                .then(async res => {
                    let data;
                    try {
                        data = await res.json();
                    } catch (e) {
                        data = {};
                    }

                    if (data.success) {
                        successAlias.textContent = data.alias;
                        updateSteps(4);
                        return;
                    }

                    let msg = data.message || 'Registration failed. Please try again.';
                    if (data.errors) {
                        msg = Object.values(data.errors).flat().join(' ');
                    }
                    showError(consentStepError, msg);

                    agreeAndSubmitBtn.disabled = false;
                    agreeAndSubmitBtn.innerHTML = '<i class="fas fa-check mr-2"></i> I Agree & Create Account';
                    // Re-enable based on scroll
                    const atBottom = consentTextContainer.scrollTop + consentTextContainer.clientHeight >= consentTextContainer.scrollHeight - 10;
                    if (atBottom) {
                        agreeAndSubmitBtn.disabled = false;
                    }
                })
                .catch(() => {
                    showError(consentStepError, 'Network error. Please try again.');
                    agreeAndSubmitBtn.disabled = false;
                    agreeAndSubmitBtn.innerHTML = '<i class="fas fa-check mr-2"></i> I Agree & Create Account';
                });
            });

            // ── Auto-generate alias when step 2 loads ──
            // (already called after OTP verification)

        });
    </script>

</body>
</html>