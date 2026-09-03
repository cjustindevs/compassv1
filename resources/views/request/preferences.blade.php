<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Session Preferences</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0;
            --green-100: #D0F0D8;
            --green-500: #04A052;
            --green-600: #038A45;
            --green-700: #027039;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #163B2D;
            --gray-900: #111827;
            --red-500: #EF4444;
        }

        body { background: #F8FBF9; }

        /* ─── Sidebar ─── */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid rgba(4,160,82,0.06);
            box-shadow: 4px 0 40px rgba(0,0,0,0.02);
            z-index: 100;
            transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
            display: flex;
            flex-direction: column;
            padding: 24px 16px 20px;
        }
        .sidebar.closed { transform: translateX(-100%); }

        .sidebar .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(4,160,82,0.06);
            margin-bottom: 20px;
        }
        .sidebar .logo .icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #38C172, #038A45);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 20px;
            box-shadow: 0 4px 16px rgba(4,160,82,0.2);
        }
        .sidebar .logo span {
            font-weight: 700;
            font-size: 20px;
            color: var(--green-700);
        }

        .sidebar .nav { flex: 1; overflow-y: auto; }
        .sidebar .nav .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--gray-500);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            margin-bottom: 2px;
        }
        .sidebar .nav .nav-item i { width: 20px; text-align: center; font-size: 16px; color: var(--gray-400); }
        .sidebar .nav .nav-item:hover { background: var(--green-50); color: var(--gray-800); }
        .sidebar .nav .nav-item:hover i { color: var(--green-500); }
        .sidebar .nav .nav-item.active {
            background: var(--green-50);
            color: var(--green-700);
            font-weight: 600;
        }
        .sidebar .nav .nav-item.active i { color: var(--green-500); }
        .sidebar .nav .nav-item .badge {
            margin-left: auto;
            background: var(--green-500);
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 20px;
        }

        .sidebar .user-section {
            border-top: 1px solid rgba(4,160,82,0.06);
            padding-top: 16px;
            margin-top: auto;
        }
        .sidebar .user-section .user-card {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar .user-section .user-card .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #38C172, #038A45);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }
        .sidebar .user-section .user-card .info .name {
            font-weight: 600;
            font-size: 14px;
            color: var(--gray-800);
        }
        .sidebar .user-section .user-card .info .role {
            font-size: 12px;
            color: var(--gray-400);
        }
        .sidebar .user-section .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 12px;
            padding: 8px 12px;
            border-radius: 10px;
            color: var(--gray-500);
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
        }
        .sidebar .user-section .logout-btn:hover {
            background: #FEE2E2;
            color: #DC2626;
        }
        .sidebar .user-section .logout-btn i { width: 20px; text-align: center; }

        .main-content {
            margin-left: 260px;
            padding: 24px 40px 80px;
            min-height: 100vh;
        }

        .form-card {
            background: white;
            border-radius: 24px;
            padding: 32px 36px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }

        .form-label {
            display: block;
            font-weight: 600;
            font-size: 14px;
            color: var(--gray-700);
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border-radius: 14px;
            border: 1.5px solid var(--gray-200);
            outline: none;
            transition: all 0.2s ease;
            background: white;
            font-size: 14px;
        }
        .form-input:focus {
            border-color: var(--green-500);
            box-shadow: 0 0 0 3px rgba(4,160,82,0.08);
        }

        .mode-card {
            flex: 1;
            min-width: 120px;
            padding: 20px 16px;
            border-radius: 16px;
            border: 2px solid var(--gray-200);
            text-align: center;
            cursor: pointer;
            transition: all 0.25s ease;
            background: white;
        }
        .mode-card:hover {
            border-color: var(--green-300);
            background: var(--green-50);
        }
        .mode-card input[type="radio"] { display: none; }
        .mode-card .mode-content {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .mode-card .mode-content .icon {
            font-size: 32px;
            margin-bottom: 8px;
        }
        .mode-card .mode-content .label {
            font-weight: 600;
            font-size: 15px;
            color: var(--gray-700);
        }
        .mode-card .mode-content .sub {
            font-size: 12px;
            color: var(--gray-400);
            margin-top: 2px;
        }
        .mode-card .mode-content .checkmark {
            opacity: 0;
            transition: all 0.25s ease;
            color: var(--green-500);
            font-size: 20px;
            margin-top: 6px;
        }
        .mode-card input[type="radio"]:checked + .mode-content {
            border-color: var(--green-500);
            background: var(--green-50);
        }
        .mode-card input[type="radio"]:checked + .mode-content .checkmark {
            opacity: 1;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            font-weight: 600;
            padding: 14px 36px;
            border-radius: 50px;
            border: none;
            box-shadow: 0 4px 20px rgba(4,160,82,0.2);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(4,160,82,0.3);
        }

        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            font-weight: 600;
            padding: 14px 28px;
            border-radius: 50px;
            border: 1.5px solid var(--gray-200);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-outline:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
        }

        .step-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
        }
        .step-dot {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.3s ease;
            border: 2px solid var(--gray-200);
            color: var(--gray-400);
        }
        .step-dot.active {
            background: var(--green-500);
            color: white;
            border-color: var(--green-500);
        }
        .step-dot.done {
            background: var(--green-100);
            color: var(--green-600);
            border-color: var(--green-300);
        }
        .step-line {
            flex: 1;
            height: 2px;
            background: var(--gray-200);
            border-radius: 2px;
        }
        .step-line.done { background: var(--green-300); }

        .risk-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .risk-badge.low { background: var(--green-100); color: var(--green-700); }
        .risk-badge.moderate { background: #FEF3C7; color: #D97706; }
        .risk-badge.high { background: #FEE2E2; color: #DC2626; }
        .risk-badge.emergency { background: #FEE2E2; color: #DC2626; }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.94);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-top: 1px solid var(--gray-200);
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
            z-index: 200;
            justify-content: space-around;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0px;
            color: var(--gray-400);
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 12px;
            transition: all 0.2s ease;
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: var(--green-500); }
        .bottom-nav .nav-item.active i { color: var(--green-500); }

        .hamburger {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--gray-700);
            cursor: pointer;
            padding: 4px;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.25);
            z-index: 99;
        }
        .sidebar-overlay.active { display: block; }

        @media (min-width: 769px) {
            .sidebar-overlay { display: none !important; }
        }
        @media (max-width: 1024px) {
            .main-content { padding: 20px 24px 80px; }
            .form-card { padding: 24px 20px; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 280px; padding: 16px; }
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .form-card { padding: 20px 16px; border-radius: 16px; }
            .mode-card { min-width: 100px; padding: 16px 12px; }
            .mode-card .mode-content .icon { font-size: 28px; }
            .btn-primary, .btn-outline { padding: 12px 24px; font-size: 14px; width: 100%; justify-content: center; }
            .step-dot { width: 28px; height: 28px; font-size: 11px; }
        }
        @media (max-width: 480px) {
            .form-card { padding: 16px 12px; }
            .mode-card { min-width: 100%; }
            .step-dot { width: 24px; height: 24px; font-size: 10px; }
        }
    </style>
</head>
<body>

    @include('partials.sidebar', [
        'active' => ['request.preferences*'],
        'role'   => 'Help Seeker',
    ])

    <!-- ══════════════════════════════════════════════ -->
    <!-- MAIN CONTENT                                 -->
    <!-- ══════════════════════════════════════════════ -->

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Request Peer Support</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        <span class="text-green-600">Step 2 of 3</span> · Session Preferences
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step-dot done">✓</div>
            <div class="step-line done"></div>
            <div class="step-dot active">2</div>
            <div class="step-line"></div>
            <div class="step-dot">3</div>
        </div>

        <!-- ─── FORM CARD ─── -->
        <div class="form-card">

            <!-- Risk Summary -->
            <div class="mb-6 p-4 bg-gray-50 rounded-xl flex items-center justify-between flex-wrap gap-3">
                <div>
                    <span class="text-sm font-medium text-gray-600">Risk Classification:</span>
                    <span class="risk-badge {{ session('risk_level', 'low') }}">{{ ucfirst(session('risk_level', 'low')) }}</span>
                </div>
                <span class="text-xs text-gray-400">Updated in real time</span>
            </div>

            <form id="preferencesForm" method="POST" action="{{ route('request.preferences.process') }}">
                @csrf

                <!-- ============================================ -->
                <!-- SECTION 1: SUPPORT MODE                     -->
                <!-- ============================================ -->
                <div class="mb-8">
                    <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-1">Screening</h3>
                    <p class="text-sm text-gray-500 mb-4">Choose chat or voice support. Voice sessions require recording consent before matching.</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <label class="mode-card">
                            <input type="radio" name="support_mode" value="chat" {{ old('support_mode', 'chat') === 'chat' ? 'checked' : '' }}>
                            <div class="mode-content">
                                <div class="icon">💬</div>
                                <div class="label">Chat</div>
                                <div class="sub">Private text-based conversation</div>
                                <div class="checkmark"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </label>
                        <label class="mode-card">
                            <input type="radio" name="support_mode" value="voice" {{ old('support_mode') === 'voice' ? 'checked' : '' }}>
                            <div class="mode-content">
                                <div class="icon">🎙️</div>
                                <div class="label">Voice</div>
                                <div class="sub">Real-time voice call with consent controls</div>
                                <div class="checkmark"><i class="fas fa-check-circle"></i></div>
                            </div>
                        </label>
                    </div>
                    @error('support_mode')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror

                    <div id="voiceConsentSection" class="mt-5 p-4 rounded-xl border border-amber-200 bg-amber-50 {{ old('support_mode') === 'voice' ? '' : 'hidden' }}">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-microphone"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between gap-3 flex-wrap">
                                    <h4 class="font-semibold text-gray-800">Voice Recording Consent</h4>
                                    <span class="text-xs font-semibold text-amber-700 bg-white border border-amber-200 rounded-full px-3 py-1">Required for voice</span>
                                </div>
                                <p class="text-sm text-gray-600 mt-2">
                                    Voice sessions may be recorded for supervision and quality assurance. Recordings are stored securely and only authorized personnel can access them.
                                </p>
                                <label class="mt-4 flex items-start gap-3 cursor-pointer">
                                    <input id="voiceConsent" name="voice_consent" type="checkbox" value="1" class="mt-1 rounded border-amber-300 text-[#04A052] focus:ring-[#04A052]" {{ old('voice_consent') ? 'checked' : '' }}>
                                    <span class="text-sm text-gray-700">
                                        <strong>I consent to recording this voice session.</strong><br>
                                        <span class="text-gray-500">I understand it will be used only for supervision and quality assurance.</span>
                                    </span>
                                </label>
                                <p id="voiceConsentClientError" class="text-red-500 text-sm mt-2 hidden">Please provide consent to continue with a voice session.</p>
                                @error('voice_consent')
                                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECTION 2: PREFERRED LANGUAGE               -->
                <!-- ============================================ -->
                <div class="mb-8">
                    <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-1">Preferences</h3>
                    <p class="text-sm text-gray-500 mb-4">Select your preferred language.</p>

                    <div>
                        <label class="form-label" for="preferred_language">Preferred Language <span class="text-red-500">*</span></label>
                        <select id="preferred_language" name="preferred_language" class="form-input" required>
                            <option value="">Select language...</option>
                            <option value="English" {{ old('preferred_language') == 'English' ? 'selected' : '' }}>English</option>
                            <option value="Tagalog" {{ old('preferred_language') == 'Tagalog' ? 'selected' : '' }}>Tagalog</option>
                            <option value="Bisaya" {{ old('preferred_language') == 'Bisaya' ? 'selected' : '' }}>Bisaya</option>
                            <option value="Ilocano" {{ old('preferred_language') == 'Ilocano' ? 'selected' : '' }}>Ilocano</option>
                            <option value="Hiligaynon" {{ old('preferred_language') == 'Hiligaynon' ? 'selected' : '' }}>Hiligaynon</option>
                            <option value="Waray" {{ old('preferred_language') == 'Waray' ? 'selected' : '' }}>Waray</option>
                            <option value="Other" {{ old('preferred_language') == 'Other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('preferred_language')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECTION 3: ADDITIONAL NOTES                 -->
                <!-- ============================================ -->
                <div class="mb-8">
                    <label class="form-label" for="additional_notes">Additional Notes <span class="text-gray-400 text-sm font-normal">(optional)</span></label>
                    <p class="text-sm text-gray-500 mb-2">Anything you want the team to know?</p>
                    <textarea id="additional_notes" name="additional_notes" class="form-input" rows="3" maxlength="500" placeholder="Any additional information that might help us support you better...">{{ old('additional_notes') }}</textarea>
                    <div class="char-count text-right text-xs text-gray-400 mt-1" id="noteCount">0 / 500</div>
                    @error('additional_notes')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ============================================ -->
                <!-- FORM ACTIONS                                -->
                <!-- ============================================ -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-gray-200">
                    <a href="{{ route('request.screening') }}" class="text-gray-500 hover:text-gray-700 transition font-medium text-sm">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                        <button type="submit" id="preferencesSubmit" class="btn-primary w-full sm:w-auto">
                            Find a Helper <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

            </form>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            You are not alone. We are here for you.
        </div>

    </main>

    <!-- ══════════════════════════════════════════════ -->
    <!-- JAVASCRIPT                                   -->
    <!-- ══════════════════════════════════════════════ -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // ── Character Counter ──
            const noteInput = document.getElementById('additional_notes');
            const noteCount = document.getElementById('noteCount');

            if (noteInput && noteCount) {
                noteInput.addEventListener('input', function() {
                    const length = this.value.length;
                    noteCount.textContent = length + ' / 500';
                });
            }

            // ── Mode Card Selection + Voice Consent ──
            const form = document.getElementById('preferencesForm');
            const voiceConsentSection = document.getElementById('voiceConsentSection');
            const voiceConsent = document.getElementById('voiceConsent');
            const voiceConsentClientError = document.getElementById('voiceConsentClientError');
            const submitButton = document.getElementById('preferencesSubmit');

            function updateSupportMode(selectedMode) {
                document.querySelectorAll('.mode-card').forEach(card => {
                    const radio = card.querySelector('input[type="radio"]');
                    const isSelected = radio && radio.value === selectedMode;
                    card.style.borderColor = isSelected ? '#04A052' : '';
                    card.style.background = isSelected ? '#EAF8F0' : '';
                });

                const voiceSelected = selectedMode === 'voice';
                voiceConsentSection.classList.toggle('hidden', !voiceSelected);
                voiceConsent.required = voiceSelected;

                if (!voiceSelected) {
                    voiceConsent.checked = false;
                    voiceConsentClientError.classList.add('hidden');
                }
            }

            document.querySelectorAll('.mode-card input[type="radio"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    updateSupportMode(this.value);
                });

                if (radio.checked) {
                    updateSupportMode(radio.value);
                }
            });

            form.addEventListener('submit', function(event) {
                const selectedMode = document.querySelector('.mode-card input[name="support_mode"]:checked')?.value;

                if (selectedMode === 'voice' && !voiceConsent.checked) {
                    event.preventDefault();
                    voiceConsentClientError.classList.remove('hidden');
                    voiceConsent.focus();
                    voiceConsentSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return;
                }

                submitButton.disabled = true;
            });

            voiceConsent.addEventListener('change', function() {
                if (this.checked) {
                    voiceConsentClientError.classList.add('hidden');
                }
            });

        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
