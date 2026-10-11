<!DOCTYPE html>
<html class="compass-ui" lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Session Preferences</title>

    <!-- Tailwind -->
    <!-- Google Fonts -->
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
        .sidebar .nav .nav-item :is(i, .compass-icon) { width: 20px; text-align: center; font-size: 16px; color: var(--gray-400); }
        .sidebar .nav .nav-item:hover { background: var(--green-50); color: var(--gray-800); }
        .sidebar .nav .nav-item:hover :is(i, .compass-icon) { color: var(--green-500); }
        .sidebar .nav .nav-item.active {
            background: var(--green-50);
            color: var(--green-700);
            font-weight: 600;
        }
        .sidebar .nav .nav-item.active :is(i, .compass-icon) { color: var(--green-500); }
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
        .sidebar .user-section .logout-btn :is(i, .compass-icon) { width: 20px; text-align: center; }

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
        .bottom-nav .nav-item :is(i, .compass-icon) { font-size: 20px; }
        .bottom-nav .nav-item.active { color: var(--green-500); }
        .bottom-nav .nav-item.active :is(i, .compass-icon) { color: var(--green-500); }

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
    @vite(['resources/css/app.css'])
    @include('partials.ui-assets')
    <link rel="stylesheet" href="{{ asset('css/seeker-request.css') }}?v={{ filemtime(public_path('css/seeker-request.css')) }}">
</head>
<body class="compass-compact">

    @include('partials.sidebar', [
        'active' => ['request.preferences*'],
        'role'   => 'Help Seeker',
    ])

    <!-- ══════════════════════════════════════════════ -->
    <!-- MAIN CONTENT                                 -->
    <!-- ══════════════════════════════════════════════ -->

    <main class="main-content request-flow-compact">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn" aria-label="Open navigation" aria-controls="sidebar">
                    <x-ui-icon name="menu"  />
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Request Peer Support</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        <span class="text-green-600">Step 2 of 3</span> · Session Preferences
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now('Asia/Manila')->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Step Indicator -->
        @include('request.partials.progress', ['currentStep'=>2])

        <!-- ─── FORM CARD ─── -->
        <div class="form-card">

            <form id="preferencesForm" class="form-maximized" method="POST" action="{{ route('request.preferences.process') }}" data-request-form>
                @csrf
                <input type="hidden" name="instrument_version" value="{{ \App\Services\CompactScreening::FORM_VERSION }}">
                <input type="hidden" name="stage" value="preferences">
                <input type="hidden" name="session_id" value="{{ $session->id }}">
                @if($draft)<p class="text-sm text-gray-500 mb-4" role="status">Your previously saved language preference was restored.</p>@endif

                <div class="form-row">

                <!-- ============================================ -->
                <!-- SECTION 1: SUPPORT MODE                     -->
                <!-- ============================================ -->
                <div class="form-section-compact">
                    <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-1">Communication mode</h3>
                    <p class="text-sm text-gray-500 mb-4">Choose your language for a private chat session.</p>

                    <label class="mode-card">
                        <input type="radio" name="support_mode" value="chat" checked>
                        <div class="mode-content">
                            <div class="label">Chat</div>
                            <div class="sub">Private text-based conversation</div>
                            <div class="checkmark"><x-ui-icon name="check-circle" /></div>
                        </div>
                    </label>
                    @error('support_mode')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- ============================================ -->
                <!-- SECTION 2: PREFERRED LANGUAGE               -->
                <!-- ============================================ -->
                <div class="form-section-compact">
                    <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wider mb-1">Preferences</h3>
                    <p class="text-sm text-gray-500 mb-4">Select your preferred language.</p>

                    <div>
                        <label class="form-label" for="preferred_language">Preferred Language <span class="text-red-500">*</span></label>
                        <select id="preferred_language" name="preferred_language" class="form-input" required>
                            <option value="">Select language...</option>
                            <option value="English" {{ old('preferred_language', $draft?->payload['preferred_language'] ?? auth()->user()->preferred_language) == 'English' ? 'selected' : '' }}>English</option>
                            <option value="Tagalog" {{ old('preferred_language', $draft?->payload['preferred_language'] ?? auth()->user()->preferred_language) == 'Tagalog' ? 'selected' : '' }}>Tagalog</option>
                            <option value="English/Tagalog" {{ old('preferred_language', $draft?->payload['preferred_language'] ?? auth()->user()->preferred_language) == 'English/Tagalog' ? 'selected' : '' }}>Both (English and Tagalog)</option>
                        </select>
                        @error('preferred_language')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- ============================================ -->
                <!-- FORM ACTIONS                                -->
                <!-- ============================================ -->
                </div>
                <div class="actions-compact">
                    <a href="{{ route('seeker.dashboard') }}" class="btn-outline">
                        <x-ui-icon name="arrow-left" class="mr-2" /> Back
                    </a>

                    <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                        <button type="submit" id="preferencesSubmit" class="btn-primary w-full sm:w-auto">
                            Find a Helper <x-ui-icon name="arrow-right"  />
                        </button>
                    </div>
                </div>

            </form>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">

            You are not alone. We are here for you.
        </div>

    </main>

    <!-- ══════════════════════════════════════════════ -->
    <!-- JAVASCRIPT                                   -->
    <!-- ══════════════════════════════════════════════ -->



    <script src="{{ asset('js/seeker-request.js') }}?v={{ filemtime(public_path('js/seeker-request.js')) }}" defer></script>
    @include('layouts.partials.pwa-banner')

</body>
</html>
