<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Pending Evaluations</title>

    @vite(['resources/js/app.js', 'resources/js/adviser-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0;
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
            --yellow-500: #F59E0B;
            --blue-500: #3B82F6;
        }

        body { background: #F8FBF9; }

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
        .sidebar .nav .nav-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--gray-400);
            padding: 12px 14px 6px;
        }
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
        .sidebar .user-section .user-stats {
            display: flex;
            justify-content: space-around;
            padding: 12px 0;
            border-bottom: 1px solid rgba(4,160,82,0.06);
            margin-bottom: 12px;
        }
        .sidebar .user-section .user-stats .stat { text-align: center; }
        .sidebar .user-section .user-stats .stat .value { display: block; font-weight: 700; font-size: 14px; color: var(--gray-800); }
        .sidebar .user-section .user-stats .stat .label { font-size: 10px; color: var(--gray-400); text-transform: uppercase; letter-spacing: 0.03em; }
        .sidebar .user-section .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
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
        .sidebar .user-section .logout-btn:hover { background: #FEE2E2; color: #DC2626; }
        .sidebar .user-section .logout-btn i { width: 20px; text-align: center; }

        .main-content {
            margin-left: 260px;
            padding: 24px 32px 80px;
            min-height: 100vh;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.04); }
        .stat-number { font-size: 28px; font-weight: 800; color: var(--gray-800); }
        .stat-label { font-size: 13px; color: var(--gray-500); }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: var(--gray-800); }

        .eval-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-bottom: 1px solid var(--gray-100);
            transition: background 0.2s;
        }
        .eval-item:hover { background: var(--gray-50); }
        .eval-item:last-child { border-bottom: none; }
        .eval-item .seeker-info .alias { font-weight: 600; color: var(--gray-800); }
        .eval-item .seeker-info .details { font-size: 13px; color: var(--gray-500); }
        .eval-item .seeker-info .concern { font-size: 12px; color: var(--gray-400); }
        .eval-item .actions { display: flex; gap: 8px; flex-wrap: wrap; }

        .risk-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .risk-badge.low { background: var(--green-100); color: var(--green-700); }
        .risk-badge.moderate { background: #FEF3C7; color: #D97706; }
        .risk-badge.high { background: #FEE2E2; color: #DC2626; }
        .risk-badge.emergency { background: #FEE2E2; color: #DC2626; animation: pulse-risk 1.5s infinite; }
        @keyframes pulse-risk { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 20px;
            border: none;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-primary:hover { background: var(--green-700); transform: scale(1.02); }

        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            padding: 8px 18px;
            border-radius: 20px;
            border: 1px solid var(--gray-200);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-outline:hover { background: var(--gray-50); border-color: var(--gray-300); }

        .btn-skip {
            background: transparent;
            color: var(--gray-400);
            padding: 8px 18px;
            border-radius: 20px;
            border: none;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-skip:hover { color: var(--gray-600); text-decoration: underline; }

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

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.94);
            backdrop-filter: blur(16px);
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

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .sidebar { width: 280px; padding: 16px; }
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .stat-number { font-size: 22px; }
            .grid-cols-3 { grid-template-columns: 1fr; }
            .eval-item { flex-direction: column; align-items: flex-start; gap: 10px; }
            .eval-item .actions { width: 100%; }
            .eval-item .actions .btn-primary,
            .eval-item .actions .btn-outline,
            .eval-item .actions .btn-skip { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .grid-cols-3 { grid-template-columns: 1fr; }
            .stat-number { font-size: 18px; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body>

    @include('layouts.partials.adviser-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Pending Evaluations</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Review session documentation and evaluate helper competency
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Pending Reviews</span>
                    <span class="text-2xl">📋</span>
                </div>
                <div class="stat-number">{{ $totalPending }}</div>
                <span class="text-xs text-gray-400">Sessions awaiting review</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">High Risk</span>
                    <span class="text-2xl">⚠️</span>
                </div>
                <div class="stat-number">{{ $highRiskPending }}</div>
                <span class="text-xs text-gray-400">Require immediate attention</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Total Feedback Given</span>
                    <span class="text-2xl">📝</span>
                </div>
                <div class="stat-number">{{ $feedbackCount }}</div>
                <span class="text-xs text-gray-400">Evaluations completed</span>
            </div>
        </div>

        <!-- Pending Evaluations -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Awaiting Review</h3>
                <span class="text-sm text-gray-400">{{ $totalPending }} sessions</span>
            </div>

            @if($pendingReports->isNotEmpty())
                @foreach($pendingReports as $report)
                    <div class="eval-item">
                        <div class="seeker-info">
                            <div class="alias">
                                {{ $report->session->seeker->generated_alias ?? 'Anonymous' }}
                                <span class="risk-badge {{ $report->session->risk_level ?? 'low' }}">
                                    {{ ucfirst($report->session->risk_level ?? 'Low') }}
                                </span>
                            </div>
                            <div class="details">
                                {{ $report->session->helper->first_name ?? 'Unknown' }} {{ $report->session->helper->last_name ?? '' }}
                                · {{ $report->created_at->format('M d, Y') }}
                                · {{ $report->session->session_type ?? 'Chat' }}
                            </div>
                            <div class="concern">
                                Concern: {{ $report->session->concern->concern_name ?? 'General' }}
                                @if($report->referral_recommended)
                                    <span class="text-yellow-600 font-medium ml-2">⚡ Referral Recommended</span>
                                @endif
                            </div>
                        </div>
                        <div class="actions">
                            <a href="{{ route('adviser.evaluate', $report->id) }}" class="btn-primary">
                                <i class="fas fa-check mr-1"></i> Evaluate
                            </a>
                            <a href="#" class="btn-outline">
                                <i class="fas fa-eye mr-1"></i> View
                            </a>
                            <form method="POST" action="{{ route('adviser.evaluations.skip', $report->id) }}" class="inline">
                                @csrf
                                <button type="submit" class="btn-skip">
                                    <i class="fas fa-forward mr-1"></i> Skip
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="text-center py-12 text-gray-400">
                    <i class="fas fa-check-circle text-5xl mb-3 block text-green-500"></i>
                    <p class="text-lg font-medium text-gray-600">All caught up!</p>
                    <p class="text-sm">No pending evaluations to review</p>
                </div>
            @endif
        </div>

        <!-- Recently Completed -->
        @if($completedReports->isNotEmpty())
            <div class="card">
                <div class="card-header">
                    <h3>Recently Completed</h3>
                    <span class="text-sm text-gray-400">Last 10 reviews</span>
                </div>
                @foreach($completedReports as $report)
                    <div class="eval-item">
                        <div class="seeker-info">
                            <div class="alias text-gray-500">
                                {{ $report->session->seeker->generated_alias ?? 'Anonymous' }}
                                <span class="text-xs text-green-600 font-medium ml-2">✓ Reviewed</span>
                            </div>
                            <div class="details text-gray-400">
                                {{ $report->session->helper->first_name ?? 'Unknown' }}
                                · {{ $report->updated_at->format('M d, Y') }}
                            </div>
                        </div>
                        <span class="text-xs text-gray-400">{{ $report->updated_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Quality supervision leads to quality support.
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('adviser.dashboard') }}" class="nav-item">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('adviser.evaluations') }}" class="nav-item active">
            <i class="fas fa-clipboard-list"></i>
            <span>Evaluations</span>
        </a>
        <a href="{{ route('adviser.referrals') }}" class="nav-item">
            <i class="fas fa-arrow-right"></i>
            <span>Referrals</span>
        </a>
        <a href="{{ route('adviser.helpers') }}" class="nav-item">
            <i class="fas fa-users"></i>
            <span>Helpers</span>
        </a>
        <a href="{{ route('adviser.notifications') }}" class="nav-item">
            <i class="fas fa-bell"></i>
            <span>Alerts</span>
        </a>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const hamburger = document.getElementById('hamburgerBtn');

            function toggleSidebar() {
                sidebar.classList.toggle('closed');
                overlay.classList.toggle('active');
            }

            function closeSidebar() {
                sidebar.classList.add('closed');
                overlay.classList.remove('active');
            }

            hamburger.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) closeSidebar();
            });

            document.querySelectorAll('.bottom-nav .nav-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    document.querySelectorAll('.bottom-nav .nav-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });

            document.querySelectorAll('.sidebar .nav .nav-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    document.querySelectorAll('.sidebar .nav .nav-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    if (window.innerWidth <= 768) closeSidebar();
                });
            });
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>