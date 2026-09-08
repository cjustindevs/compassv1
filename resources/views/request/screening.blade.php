<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Request Support</title>

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
            --orange-500: #F59E0B;
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

        /* ─── Form Styles ─── */
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

        textarea.form-input { resize: vertical; min-height: 100px; }

        .char-count {
            font-size: 12px;
            color: var(--gray-400);
            text-align: right;
            margin-top: 4px;
        }
        .char-count.warning { color: var(--orange-500); }
        .char-count.danger { color: var(--red-500); }

        .radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .radio-option {
            flex: 1;
            min-width: 100px;
        }
        .radio-option input[type="radio"] { display: none; }
        .radio-option label {
            display: block;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1.5px solid var(--gray-200);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 14px;
            color: var(--gray-600);
        }
        .radio-option input[type="radio"]:checked + label {
            border-color: var(--green-500);
            background: var(--green-50);
            color: var(--green-700);
        }
        .radio-option label:hover {
            border-color: var(--green-300);
            background: var(--gray-50);
        }
        .radio-option .sub-text {
            display: block;
            font-size: 12px;
            font-weight: 400;
            color: var(--gray-400);
            margin-top: 4px;
        }

        .safety-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .safety-btn {
            flex: 1;
            min-width: 100px;
        }
        .safety-btn input[type="radio"] { display: none; }
        .safety-btn label {
            display: block;
            padding: 14px 20px;
            border-radius: 14px;
            border: 1.5px solid var(--gray-200);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 14px;
            color: var(--gray-600);
        }
        .safety-btn input[type="radio"]:checked + label {
            border-color: var(--green-500);
            background: var(--green-50);
            color: var(--green-700);
        }
        .safety-btn input[type="radio"][value="yes"]:checked + label {
            border-color: var(--red-500);
            background: #FEE2E2;
            color: var(--red-500);
        }
        .safety-btn label:hover {
            border-color: var(--gray-300);
            background: var(--gray-50);
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
        .risk-badge.emergency {
            background: #FEE2E2;
            color: #DC2626;
            animation: pulse-risk 1.5s ease-in-out infinite;
        }

        @keyframes pulse-risk {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .risk-card {
            border-radius: 16px;
            padding: 20px 24px;
            border-left: 4px solid var(--green-500);
        }
        .risk-card.low { border-left-color: var(--green-500); background: var(--green-50); }
        .risk-card.moderate { border-left-color: #D97706; background: #FEF3C7; }
        .risk-card.high { border-left-color: #DC2626; background: #FEE2E2; }
        .risk-card.emergency { border-left-color: #DC2626; background: #FEE2E2; }

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
            .radio-option { min-width: 80px; }
            .radio-option label { padding: 12px 14px; font-size: 13px; }
            .safety-btn { min-width: 80px; }
            .safety-btn label { padding: 12px 14px; font-size: 13px; }
            .btn-primary, .btn-outline { padding: 12px 24px; font-size: 14px; width: 100%; justify-content: center; }
            .step-dot { width: 28px; height: 28px; font-size: 11px; }
        }
        @media (max-width: 480px) {
            .form-card { padding: 16px 12px; }
            .radio-option { min-width: 100%; }
            .safety-btn { min-width: 100%; }
            .step-dot { width: 24px; height: 24px; font-size: 10px; }
        }
    </style>
</head>
<body class="compass-compact">

    @include('partials.sidebar', [
        'active' => ['request.screening*'],
        'role'   => 'Help Seeker',
    ])

    <!-- ══════════════════════════════════════════════ -->
    <!-- MAIN CONTENT                                 -->
    <!-- ══════════════════════════════════════════════ -->

    <main class="main-content request-flow-compact">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Request Peer Support</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        <span class="text-green-600">Step 1 of 3</span> · Screening
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Step Indicator -->
        <div class="step-indicator steps-compact">
            <div class="step-dot active">1</div>
            <div class="step-line"></div>
            <div class="step-dot">2</div>
            <div class="step-line"></div>
            <div class="step-dot">3</div>
        </div>

        <!-- ─── FORM CARD ─── -->
        <div class="form-card">

            <form id="screeningForm" method="POST" action="{{ route('request.screening.process') }}">
                @csrf

                <!-- ============================================ -->
                <!-- SECTION 1: AREA OF CONCERN                  -->
                <!-- ============================================ -->
                <div class="form-section-compact">
                    <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-1">General</h3>
                    <p class="text-sm text-gray-500 mb-4">What is your main concern today?</p>

                    <div>
                        <label class="form-label" for="concern_id">Area of Concern <span class="text-red-500">*</span></label>
                        <select id="concern_id" name="concern_id" class="form-input" required>
                            <option value="">Select your concern...</option>
                            @foreach($concerns as $concern)
                                <option value="{{ $concern->id }}" {{ old('concern_id') == $concern->id ? 'selected' : '' }}>
                                    {{ $concern->concern_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('concern_id')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Custom Concern (appears when "Others" selected) -->
                    <div id="customConcernContainer" class="mt-3 hidden">
                        <label class="form-label" for="custom_concern">Please specify your concern</label>
                        <input type="text" id="custom_concern" name="custom_concern" class="form-input" placeholder="Type your concern..." maxlength="100">
                        @error('custom_concern')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- SECTION 2: BRIEF DESCRIPTION                -->
                <!-- ============================================ -->
                <div class="form-section-compact">
                    <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-1">Brief Description</h3>
                    <p class="text-sm text-gray-500 mb-4">Tell us more about your concern.</p>

                    <div>
                        <label class="form-label" for="description">Description <span class="text-gray-400">(optional unless Other is selected)</span></label>
                        <textarea id="description" name="description" class="form-input" maxlength="500" placeholder="I have several deadlines this week and I'm having trouble sleeping because I feel like I cannot keep up with my classes.">{{ old('description') }}</textarea>
                        <div class="char-count" id="charCount">0 / 500</div>
                        @error('description')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <fieldset class="form-section-compact">
                    <legend class="font-semibold mb-4">How are you feeling?</legend>
                    @foreach(['current_suicide_plan' => 'Do you currently have a plan to end your life?', 'suicidal_thoughts' => 'Have you had thoughts of ending your life?', 'severe_distress' => 'Are you experiencing severe emotional distress?', 'recurring_distress' => 'Has your emotional distress been recurring?', 'difficulty_coping' => 'Are you finding it difficult to cope?'] as $field => $question)
                        <fieldset class="form-group-compact">
                            <legend class="form-label">{{ $question }} <span class="text-red-500" aria-hidden="true">*</span></legend>
                            <div class="options-compact">
                                @foreach(['1' => 'Yes', '0' => 'No'] as $value => $answer)
                                    <label class="option-btn" for="{{ $field }}_{{ $value }}">
                                        <input type="radio" id="{{ $field }}_{{ $value }}" name="{{ $field }}" value="{{ $value }}" required @checked((string) old($field, '') === (string) $value) @error($field) aria-invalid="true" aria-describedby="{{ $field }}_error" @enderror>
                                        <span>{{ $answer }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error($field)<p id="{{ $field }}_error" class="form-error text-red-600">{{ $message }}</p>@enderror
                        </fieldset>
                    @endforeach
                </fieldset>

                <!-- ============================================ -->
                <!-- FORM ACTIONS                                -->
                <!-- ============================================ -->
                <div class="actions-compact">
                    <a href="{{ route('seeker.dashboard') }}" class="text-gray-500 hover:text-gray-700 transition font-medium text-sm">
                        <i class="fas fa-arrow-left mr-2"></i> Back
                    </a>
                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                        <button type="submit" class="btn-primary w-full sm:w-auto">
                            Continue <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

            </form>
        </div>

        <!-- Emergency Banner -->
        <div class="mt-6 p-4 bg-red-50 rounded-xl border border-red-200 flex items-center gap-4 flex-wrap">
            <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
            <div>
                <p class="text-sm font-medium text-red-700">Need immediate help?</p>
                <p class="text-xs text-red-600">COMPASS also offers emergency services. Click the Emergency button in the sidebar.</p>
            </div>
            <a href="{{ route('emergency') }}" class="px-6 py-2 bg-red-500 text-white text-sm font-semibold rounded-full hover:bg-red-600 transition ml-auto">Emergency</a>
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
            const description = document.getElementById('description');
            const charCount = document.getElementById('charCount');

            description.addEventListener('input', function() {
                const length = this.value.length;
                charCount.textContent = length + ' / 500';
                charCount.classList.remove('warning', 'danger');
                if (length > 450) charCount.classList.add('warning');
                if (length >= 500) charCount.classList.add('danger');
            });

            // ── Custom Concern Toggle ──
            const concernSelect = document.getElementById('concern_id');
            const customContainer = document.getElementById('customConcernContainer');

            concernSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption && ['other', 'others', 'other concerns'].includes(selectedOption.text.trim().toLowerCase())) {
                    customContainer.classList.remove('hidden');
                    description.required = true;
                } else {
                    customContainer.classList.add('hidden');
                    description.required = false;
                    document.getElementById('custom_concern').value = '';
                }
            });
            concernSelect.dispatchEvent(new Event('change'));

        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
