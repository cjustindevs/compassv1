<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Seeker Dashboard</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Google Fonts: Inter + Playfair Display -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">

    <!-- Real-time seeker notifications (Echo + Reverb) -->
    @vite(['resources/js/app.js', 'resources/js/seeker-notifications.js'])

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #F0F8F2;
            --green-100: #DCF5E0;
            --green-200: #A8E0B0;
            --green-300: #6DCB80;
            --green-400: #30B650;
            --green-500: #04A052;
            --green-600: #038A45;
            --green-700: #027039;
            --green-800: #01562B;
            --green-900: #003D1E;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;
        }

        body {
            background: #F5F8F6;
            font-family: 'Inter', sans-serif;
        }

        /* ─── Scrollbar ─── */
        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--green-500); border-radius: 4px; }

        /* ─── Sidebar ─── */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid rgba(4, 160, 82, 0.08);
            box-shadow: 4px 0 40px rgba(0, 0, 0, 0.02);
            z-index: 100;
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
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
            border-bottom: 1px solid rgba(4, 160, 82, 0.08);
            margin-bottom: 20px;
        }
        .sidebar .logo .icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--green-400), var(--green-600));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 20px;
            box-shadow: 0 4px 16px rgba(4, 160, 82, 0.25);
        }
        .sidebar .logo span {
            font-weight: 700;
            font-size: 20px;
            color: var(--green-700);
            letter-spacing: -0.5px;
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
            border-top: 1px solid rgba(4, 160, 82, 0.08);
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
            background: linear-gradient(135deg, var(--green-400), var(--green-600));
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

        /* ─── Main Content ─── */
        .main-content {
            margin-left: 260px;
            padding: 24px 32px 80px;
            min-height: 100vh;
        }

        /* ─── Hero ─── */
        .hero-section {
            background: linear-gradient(135deg, #F0F8F2 0%, #DCF5E0 40%, #F5F8F6 100%);
            border-radius: 24px;
            padding: 40px 36px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(4, 160, 82, 0.08);
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: -40%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(4, 160, 82, 0.06) 0%, transparent 70%);
            border-radius: 50%;
        }
        .hero-section .hero-content { position: relative; z-index: 1; }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--green-800);
            line-height: 1.2;
        }
        .hero-title .highlight {
            color: var(--green-500);
            font-style: italic;
        }
        .hero-sub {
            color: var(--gray-600);
            font-size: 1rem;
            max-width: 480px;
            line-height: 1.7;
        }

        /* ─── Product Cards ─── */
        .product-card {
            background: white;
            border-radius: 20px;
            padding: 28px 24px 24px;
            border: 1px solid var(--gray-200);
            transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .product-card::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--green-400), var(--green-500), var(--green-600));
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 60px rgba(4, 160, 82, 0.08);
            border-color: var(--green-300);
        }
        .product-card:hover::after { opacity: 1; }
        .product-card .icon-wrap {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: var(--green-50);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: var(--green-500);
            margin-bottom: 16px;
            transition: all 0.3s ease;
        }
        .product-card:hover .icon-wrap {
            background: var(--green-500);
            color: white;
            transform: scale(1.05);
        }
        .product-card h3 {
            font-weight: 700;
            font-size: 18px;
            color: var(--gray-800);
            margin-bottom: 6px;
        }
        .product-card p {
            font-size: 14px;
            color: var(--gray-500);
            line-height: 1.6;
            margin-bottom: 16px;
        }
        .product-card .btn-join {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
            color: var(--green-500);
            transition: all 0.3s ease;
            text-decoration: none;
        }
        .product-card .btn-join i { transition: transform 0.3s ease; }
        .product-card:hover .btn-join i { transform: translateX(6px); }
        .product-card .btn-join:hover { color: var(--green-700); }

        /* ─── Mood Check-in ─── */
        .mood-section {
            background: white;
            border-radius: 20px;
            padding: 24px 28px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.02);
        }
        .mood-btn {
            padding: 12px 18px;
            border-radius: 14px;
            border: 1.5px solid var(--gray-200);
            background: white;
            cursor: pointer;
            transition: all 0.25s ease;
            font-weight: 500;
            font-size: 14px;
            color: var(--gray-600);
        }
        .mood-btn:hover {
            border-color: var(--green-400);
            background: var(--green-50);
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(4, 160, 82, 0.08);
        }
        .mood-btn.active {
            border-color: var(--green-500);
            background: var(--green-50);
            color: var(--green-700);
            box-shadow: 0 4px 16px rgba(4, 160, 82, 0.12);
        }
        .mood-btn .mood-icon { display: block; font-size: 28px; margin-bottom: 4px; }

        /* ─── Quote Card ─── */
        .quote-card {
            background: var(--green-50);
            border-radius: 16px;
            padding: 28px 32px;
            border-left: 4px solid var(--green-500);
            position: relative;
        }
        .quote-card .quote-mark {
            font-family: 'Playfair Display', serif;
            font-size: 3.5rem;
            color: var(--green-300);
            opacity: 0.3;
            line-height: 0.8;
            position: absolute;
            top: 12px;
            left: 16px;
        }
        .quote-card p {
            font-size: 1.05rem;
            color: var(--gray-700);
            font-style: italic;
            padding-left: 32px;
            line-height: 1.8;
        }
        .quote-card .attribution {
            font-style: normal;
            font-weight: 600;
            color: var(--green-600);
            font-size: 0.9rem;
            display: block;
            margin-top: 8px;
            padding-left: 32px;
        }

        /* ─── Glass Card ─── */
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.02);
            transition: all 0.3s ease;
        }
        .glass-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 40px rgba(0, 0, 0, 0.04);
        }

        /* ─── Stats Cards ─── */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
        }
        .stat-number {
            font-size: 28px;
            font-weight: 800;
            color: var(--gray-800);
        }
        .stat-label { font-size: 13px; color: var(--gray-500); }
        .stat-change {
            font-size: 12px;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 20px;
        }
        .stat-change.positive { background: var(--green-100); color: var(--green-700); }
        .stat-change.negative { background: #FEE2E2; color: #DC2626; }

        /* ─── Table ─── */
        .table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .table-container table { min-width: 600px; width: 100%; }
        .table-container th {
            font-size: 11px;
            font-weight: 600;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 12px 16px;
            text-align: left;
        }
        .table-container td {
            padding: 12px 16px;
            font-size: 14px;
            color: var(--gray-700);
            border-top: 1px solid var(--gray-100);
        }
        .table-container tr:hover td { background: var(--gray-50); }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.completed { background: var(--green-100); color: var(--green-700); }
        .status-badge.active { background: #DBEAFE; color: #1D4ED8; }
        .status-badge.pending { background: #FEF3C7; color: #D97706; }

        .stars { color: #F59E0B; font-size: 13px; letter-spacing: 1px; }

        /* ─── Resource Cards ─── */
        .resource-card {
            background: white;
            border-radius: 14px;
            padding: 16px 20px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .resource-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.06);
            border-color: var(--green-500);
        }
        .resource-card .icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--green-50);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .resource-card .info h4 { font-weight: 600; font-size: 14px; color: var(--gray-800); }
        .resource-card .info p { font-size: 12px; color: var(--gray-500); margin-top: 2px; }

        /* ─── Bottom Nav ─── */
        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.94);
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

        /* ─── Hamburger ─── */
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
            background: rgba(0, 0, 0, 0.25);
            z-index: 99;
        }
        .sidebar-overlay.active { display: block; }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 14px;
            border: none;
            box-shadow: 0 4px 20px rgba(4, 160, 82, 0.25);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(4, 160, 82, 0.35);
        }
        .btn-outline {
            background: transparent;
            color: var(--green-600);
            font-weight: 600;
            padding: 12px 28px;
            border-radius: 14px;
            border: 1.5px solid var(--green-400);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-outline:hover {
            background: var(--green-50);
            transform: translateY(-2px);
            border-color: var(--green-500);
        }

        /* ─── Responsive ─── */
        @media (max-width: 1024px) {
            .main-content { padding: 20px 24px 80px; }
            .hero-title { font-size: 2rem; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 280px; padding: 16px; }
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .hero-section { padding: 28px 20px; }
            .hero-title { font-size: 1.6rem; }
            .hero-sub { font-size: 0.9rem; }
            .mood-section { padding: 16px 18px; }
            .mood-btn { padding: 10px 14px; font-size: 12px; }
            .mood-btn .mood-icon { font-size: 22px; }
            .quote-card { padding: 20px; }
            .quote-card p { font-size: 0.95rem; padding-left: 20px; }
            .grid-cols-3 { grid-template-columns: 1fr; }
            .grid-cols-2 { grid-template-columns: 1fr; }
            .product-card { padding: 20px; }
            .stat-number { font-size: 22px; }
            .stat-card { padding: 16px; }
            .grid-cols-4 { grid-template-columns: repeat(2, 1fr); }
            .table-container table { min-width: 500px; }
        }
        @media (max-width: 480px) {
            .hero-title { font-size: 1.3rem; }
            .hero-section { padding: 20px 16px; }
            .grid-cols-4 { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-number { font-size: 20px; }
        }
        @media (min-width: 769px) {
            .sidebar-overlay { display: none !important; }
        }

        /* ─── Tab Content ─── */
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .text-green-soft { color: var(--green-500); }
        .bg-green-soft { background: var(--green-50); }
        .border-green-soft { border-color: var(--green-200); }
    </style>
</head>
<body class="compass-compact">

    @include('partials.sidebar', [
        'active' => ['seeker.dashboard'],
        'role'   => 'Help Seeker',
        'dashboardTabs' => true,
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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800" id="pageTitle">Home</h1>
                    <p class="text-sm text-gray-500 hidden sm:block" id="pageSubtitle">
                        Welcome back, <span class="font-semibold text-[#04A052]">{{ optional(auth()->user())->name ?? optional(auth()->user())->email }}</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
                <a href="{{ route('notifications') }}" class="w-9 h-9 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-700 transition" title="Notifications">
                    <i class="fas fa-bell"></i>
                </a>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════ -->
        <!-- TAB: HOME                                     -->
        <!-- ══════════════════════════════════════════════ -->

        <div id="tab-home" class="tab-content active">

            <!-- Hero -->
            <div class="hero-section mb-6">
                <div class="hero-content">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between">
                        <div>
                            <div class="inline-flex items-center gap-2 text-sm font-medium text-[#04A052] bg-white/60 px-4 py-1.5 rounded-full backdrop-blur-sm mb-4">
                                <span class="w-2 h-2 rounded-full bg-[#04A052] animate-pulse"></span>
                                Welcome to COMPASS
                            </div>
                            <h1 class="hero-title">
                                A calm place to <span class="highlight">talk</span>,<br>
                                a space to <span class="highlight">listen</span>.
                            </h1>
                            <p class="hero-sub mt-3">
                                Your safe space for emotional support. Connect with trained peer helpers
                                through confidential chat and voice sessions.
                            </p>
                            <div class="flex flex-wrap gap-3 mt-5">
                                <a href="{{ route('request.screening') }}" class="btn-primary">
                                    <i class="fas fa-comment-dots mr-2"></i> Talk to Someone
                                </a>
                                <a href="{{ route('selfhelp') }}" class="btn-outline">
                                    <i class="fas fa-heart mr-2"></i> Self-Help
                                </a>
                            </div>
                        </div>
                        <div class="hidden md:block text-6xl opacity-10 mt-4 md:mt-0"><i class="fas fa-leaf" aria-hidden="true"></i></div>
                    </div>
                </div>
            </div>

            <!-- ═══════ ACTIVE SESSION / PENDING REQUEST ═══════ -->
            @if($activeSession)
                <div class="mb-6 p-5 rounded-2xl bg-white border border-[#04A052] shadow-sm flex flex-col sm:flex-row items-start sm:items-center gap-4" style="border-left: 6px solid #04A052;">
                    <div class="w-12 h-12 rounded-full bg-green-50 flex items-center justify-center text-2xl flex-shrink-0"><i class="fas fa-star" aria-hidden="true"></i></div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-gray-800">Your session is active</h3>
                        <p class="text-sm text-gray-500">
                            Session {{ $activeSession->reference_number }} · {{ $activeSession->mode_label }} · Started {{ $activeSession->start_time?->diffForHumans() }}
                        </p>
                    </div>
                    <div class="flex gap-3 flex-shrink-0">
                        <a href="{{ route($activeSession->session_type === 'voice' ? 'session.voice' : 'session.chat') }}" class="btn-primary" style="text-decoration:none;">
                            <i class="fas fa-comment mr-2"></i> Go to Chat
                        </a>
                        <a href="{{ route('session.evaluation') }}" class="btn-outline" style="text-decoration:none;">End &amp; Evaluate</a>
                    </div>
                </div>
            @elseif($pendingSession)
                <div class="mb-6 p-5 rounded-2xl bg-white border border-gray-200 shadow-sm flex flex-col sm:flex-row items-start sm:items-center gap-4" style="border-left: 6px solid #F59E0B;">
                    <div class="w-12 h-12 rounded-full bg-yellow-50 flex items-center justify-center text-2xl flex-shrink-0">
                        <x-ui-icon :value="$pendingSession->isHelperAssigned() ? 'fa-hourglass-half' : 'fa-magnifying-glass'" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-gray-800">
                            {{ $pendingSession->isHelperAssigned() ? 'Waiting for a helper to accept' : 'Your request is in progress' }}
                        </h3>
                        <p class="text-sm text-gray-500">
                            {{ $pendingSession->reference_number }} · {{ $pendingSession->status_label }} ·
                            {{ $pendingSession->created_at?->diffForHumans() }}
                            @if($pendingSession->session_status === \App\Models\Session::STATUS_SCREENING_COMPLETED)
                                · <span class="text-green-600 font-medium">Continue where you left off</span>
                            @endif
                        </p>
                    </div>
                    <div class="flex gap-3 flex-shrink-0">
                        <a href="{{ $pendingSession->session_status === \App\Models\Session::STATUS_SCREENING_COMPLETED ? route('request.preferences') : route('request.matching') }}" class="btn-primary" style="text-decoration:none;">
                            <i class="fas fa-arrow-right mr-2"></i> Continue
                        </a>
                    </div>
                </div>
            @endif

            <!-- ═══════ PRODUCT CARDS ═══════ -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6 dashboard-grid-max">
                <a href="{{ route('request.screening') }}" class="product-card" style="text-decoration: none;">
                    <div class="icon-wrap"><i class="fas fa-comments" aria-hidden="true"></i></div>
                    <h3>Talk to Someone</h3>
                    <p>Connect with a trained peer helper who will listen without judgment.</p>
                    <span class="btn-join">
                        Get Started <i class="fas fa-arrow-right"></i>
                    </span>
                </a>
                <a href="{{ route('selfhelp') }}" class="product-card" style="text-decoration: none;">
                    <div class="icon-wrap"><i class="fas fa-spa" aria-hidden="true"></i></div>
                    <h3>Self-Care Resources</h3>
                    <p>Guided meditations, breathing exercises, and wellness tools to support you.</p>
                    <span class="btn-join">
                        Explore Now <i class="fas fa-arrow-right"></i>
                    </span>
                </a>
                <a href="{{ route('selfhelp') }}" class="product-card" style="text-decoration: none;">
                    <div class="icon-wrap"><i class="fas fa-book-open" aria-hidden="true"></i></div>
                    <h3>Daily Wellness</h3>
                    <p>Track your mood, journal your thoughts, and build healthy habits.</p>
                    <span class="btn-join">
                        Start Today <i class="fas fa-arrow-right"></i>
                    </span>
                </a>
            </div>

            <!-- ═══════ FEELING BETTER ═══════ -->
            <div class="mood-section mb-6">
                <div class="flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-800 text-lg">How are you feeling today?</h3>
                        <p class="text-sm text-gray-400">Check in with yourself — it only takes a moment.</p>
                    </div>
                    <div class="flex flex-wrap gap-2" id="moodContainer">
                        <button class="mood-btn" data-mood="great">
                            <span class="mood-icon"><i class="fas fa-face-laugh" aria-hidden="true"></i></span> Great
                        </button>
                        <button class="mood-btn" data-mood="good">
                            <span class="mood-icon"><i class="fas fa-face-smile" aria-hidden="true"></i></span> Good
                        </button>
                        <button class="mood-btn" data-mood="okay">
                            <span class="mood-icon"><i class="fas fa-face-meh" aria-hidden="true"></i></span> Okay
                        </button>
                        <button class="mood-btn" data-mood="not-great">
                            <span class="mood-icon"><i class="fas fa-face-frown" aria-hidden="true"></i></span> Not Great
                        </button>
                        <button class="mood-btn" data-mood="struggling">
                            <span class="mood-icon"><i class="fas fa-face-sad-tear" aria-hidden="true"></i></span> Struggling
                        </button>
                    </div>
                </div>
                <div id="moodResponse" class="hidden mt-3 p-3 bg-green-50 rounded-xl border border-[#04A052] text-[#027039] text-sm">
                    <i class="fas fa-check-circle text-[#04A052] mr-2"></i>
                    <span id="moodMessage">You're not alone. We're here to listen.</span>
                </div>
            </div>

            <!-- ═══════ QUOTE ═══════ -->
            <div class="quote-card mb-6">
                <span class="quote-mark">"</span>
                <p>
                    No one should have to face their struggles alone.
                    <span class="attribution">— Join the others who have faced similar challenges, and let them know you're here for them.</span>
                </p>
            </div>

            <!-- ═══════ QUICK LINKS ═══════ -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <a href="{{ route('selfhelp') }}" class="glass-card p-5 flex items-center gap-4 cursor-pointer hover:border-[#04A052] transition-all" style="text-decoration: none;">
                    <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center text-2xl"><i class="fas fa-book-open" aria-hidden="true"></i></div>
                    <div>
                        <h4 class="font-semibold text-gray-800">Resources</h4>
                        <p class="text-xs text-gray-500">Articles &amp; guides</p>
                    </div>
                </a>
                <a href="{{ route('selfhelp') }}" class="glass-card p-5 flex items-center gap-4 cursor-pointer hover:border-[#04A052] transition-all" style="text-decoration: none;">
                    <div class="w-12 h-12 rounded-xl bg-blue-100 flex items-center justify-center text-2xl"><i class="fas fa-spa" aria-hidden="true"></i></div>
                    <div>
                        <h4 class="font-semibold text-gray-800">Meditation</h4>
                        <p class="text-xs text-gray-500">Guided sessions</p>
                    </div>
                </a>
                <a href="{{ route('emergency') }}" class="glass-card p-5 flex items-center gap-4 cursor-pointer hover:border-[#04A052] transition-all" style="text-decoration: none;">
                    <div class="w-12 h-12 rounded-xl bg-purple-100 flex items-center justify-center text-2xl">🆘</div>
                    <div>
                        <h4 class="font-semibold text-gray-800">Crisis Support</h4>
                        <p class="text-xs text-gray-500">Immediate help</p>
                    </div>
                </a>
            </div>

            <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
                <i class="fas fa-heart text-[#04A052] mr-1"></i>
                You are worthy of support. Every step counts.
            </div>
        </div>

        <!-- ══════════════════════════════════════════════ -->
        <!-- TAB: DASHBOARD (Stats, Sessions, Resources)  -->
        <!-- ══════════════════════════════════════════════ -->

        <div id="tab-dashboard" class="tab-content">

            <!-- Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 dashboard-grid-max">
                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <span class="stat-label">Total Sessions</span>
                        <span class="text-2xl"><i class="fas fa-chart-column" aria-hidden="true"></i></span>
                    </div>
                    <div class="stat-number">{{ $totalSessions }}</div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-xs text-gray-400">All time</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <span class="stat-label">Completed</span>
                        <span class="text-2xl"><i class="fas fa-circle-check" aria-hidden="true"></i></span>
                    </div>
                    <div class="stat-number">{{ $completedSessions }}</div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-xs text-gray-400">Sessions finished</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <span class="stat-label">Evaluations</span>
                        <span class="text-2xl">⭐</span>
                    </div>
                    <div class="stat-number">{{ $totalEvaluations }}</div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-xs text-gray-400">Feedback given</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <span class="stat-label">Active Sessions</span>
                        <span class="text-2xl"><i class="fas fa-circle" aria-hidden="true"></i></span>
                    </div>
                    <div class="stat-number">{{ $activeSession ? 1 : 0 }}</div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-xs text-gray-400">Currently ongoing</span>
                    </div>
                </div>
            </div>

            <!-- Recent Sessions -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4 md:p-6 mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-gray-800">Recent Sessions</h2>
                    <a href="{{ route('session.history') }}" class="text-sm text-[#04A052] hover:underline">View All</a>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Session</th>
                                <th>Helper</th>
                                <th class="hidden sm:table-cell">Mode</th>
                                <th class="hidden sm:table-cell">Duration</th>
                                <th>Rating</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentSessions as $session)
                                <tr>
                                    <td class="font-medium">{{ $session->reference_number }}</td>
                                    <td>{{ $session->helper?->public_alias ?? '—' }}</td>
                                    <td class="hidden sm:table-cell">{{ $session->mode_label }}</td>
                                    <td class="hidden sm:table-cell">{{ $session->start_time ? $session->start_time->diff($session->end_time ?? now())->format('%Hh %Im') : '—' }}</td>
                                    <td>
                                        @if($session->evaluation)
                                            <span class="stars">@for ($star = 0; $star < (min(5, max(0, (int) round($session->evaluation->overall_score)))); $star++)<i class="fas fa-star" aria-hidden="true"></i>@endfor<span class="text-gray-300">@for ($star = 0; $star < (5 - min(5, max(0, (int) round($session->evaluation->overall_score)))); $star++)<i class="fas fa-star" aria-hidden="true"></i>@endfor</span></span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td><span class="status-badge {{ $session->isCompleted() ? 'completed' : ($session->isActive() ? 'active' : 'pending') }}">{{ $session->status_label }}</span></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-gray-400 py-6">No sessions yet. Start your first session to see it here.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recommended Resources -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-bold text-gray-800">Recommended for You</h2>
                    <span class="text-xs text-gray-400">Handpicked by your helper</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="resource-card">
                        <div class="icon text-green-500"><i class="fas fa-spa" aria-hidden="true"></i></div>
                        <div class="info">
                            <h4>Grounding Techniques for Anxiety</h4>
                            <p>Anxiety · 8 min</p>
                        </div>
                    </div>
                    <div class="resource-card">
                        <div class="icon text-blue-500"><i class="fas fa-wind" aria-hidden="true"></i></div>
                        <div class="info">
                            <h4>Guided Breathing - 4-7-8</h4>
                            <p>Meditation · 8 min</p>
                        </div>
                    </div>
                    <div class="resource-card">
                        <div class="icon text-purple-500"><i class="fas fa-book-open" aria-hidden="true"></i></div>
                        <div class="info">
                            <h4>Understanding Academic Burnout</h4>
                            <p>Stress · 12 min</p>
                        </div>
                    </div>
                    <div class="resource-card">
                        <div class="icon text-yellow-500"><i class="fas fa-moon" aria-hidden="true"></i></div>
                        <div class="info">
                            <h4>Sleep Hygiene Checklist</h4>
                            <p>Mental Health · 5 min</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        </main>

    <!-- ══════════════════════════════════════════════ -->
    <!-- JAVASCRIPT (Mood check-in)                   -->
    <!-- ══════════════════════════════════════════════ -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // ── Mood Check-in ──
            const moodBtns = document.querySelectorAll('.mood-btn');
            const moodResponse = document.getElementById('moodResponse');
            const moodMessage = document.getElementById('moodMessage');

            const moodMessages = {
                'great': "We're so glad you're feeling great! Keep that positive energy going! ",
                'good': "Good to hear! Remember, we're here if you need anything. ",
                'okay': "It's okay to feel okay. If you want to talk, we're here. ",
                'not-great': "Thank you for being honest. You're not alone. We're here to listen. ",
                'struggling': "We hear you. You are not alone. Let's talk.  You matter."
            };

            if (moodBtns.length) {
                moodBtns.forEach(btn => {
                    btn.addEventListener('click', function() {
                        moodBtns.forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        const mood = this.dataset.mood;
                        moodMessage.textContent = moodMessages[mood] || "You're not alone. We're here to listen.";
                        moodResponse.classList.remove('hidden');
                    });
                });
            }

        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
