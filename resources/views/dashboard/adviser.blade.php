<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Adviser Dashboard</title>

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
        .sidebar .nav .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 16px;
            color: var(--gray-400);
        }
        .sidebar .nav .nav-item:hover {
            background: var(--green-50);
            color: var(--gray-800);
        }
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
        .sidebar .user-section .user-stats .stat .value {
            display: block;
            font-weight: 700;
            font-size: 14px;
            color: var(--gray-800);
        }
        .sidebar .user-section .user-stats .stat .label {
            font-size: 10px;
            color: var(--gray-400);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
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

        /* ─── Cards ─── */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.04);
        }
        .stat-number { font-size: 28px; font-weight: 800; color: var(--gray-800); }
        .stat-label { font-size: 13px; color: var(--gray-500); }
        .stat-icon { font-size: 24px; opacity: 0.7; }

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
        .card-header a { font-size: 13px; color: var(--green-500); text-decoration: none; }
        .card-header a:hover { text-decoration: underline; }

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

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.completed { background: var(--green-100); color: var(--green-700); }
        .status-badge.pending { background: #FEF3C7; color: #D97706; }
        .status-badge.active { background: #DBEAFE; color: #1D4ED8; }
        .status-badge.in-progress { background: #DBEAFE; color: #1D4ED8; }

        .activity-item {
            display: flex;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-item .icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        .activity-item .icon.emergency { background: #FEE2E2; color: #DC2626; }
        .activity-item .icon.evaluation { background: var(--green-100); color: var(--green-700); }
        .activity-item .icon.referral { background: #FEF3C7; color: #D97706; }
        .activity-item .icon.helper { background: #DBEAFE; color: #1D4ED8; }
        .activity-item .content .message { font-weight: 500; font-size: 14px; color: var(--gray-800); }
        .activity-item .content .detail { font-size: 13px; color: var(--gray-500); }
        .activity-item .content .time { font-size: 11px; color: var(--gray-400); }

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
            .grid-cols-4 { grid-template-columns: repeat(2, 1fr); }
            .card { padding: 16px; }
        }
        @media (max-width: 480px) {
            .grid-cols-4 { grid-template-columns: 1fr 1fr; gap: 10px; }
            .stat-number { font-size: 18px; }
        }
    </style>
</head>
<body class="compass-compact">

    <!-- ══════════════════════════════════════════════ -->
    <!-- SIDEBAR                                      -->
    <!-- ══════════════════════════════════════════════ -->

    @include('layouts.partials.adviser-sidebar')

    <!-- ══════════════════════════════════════════════ -->
    <!-- SIDEBAR OVERLAY                              -->
    <!-- ══════════════════════════════════════════════ -->

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Dashboard</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Welcome back, <span class="font-semibold text-[#04A052]">{{ auth()->user()->name }}</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
                <button class="w-9 h-9 rounded-full bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:text-gray-700 transition">
                    <i class="fas fa-bell"></i>
                    <span class="absolute -top-1 -right-1 w-4 h-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">{{ $unreadNotifications > 0 ? $unreadNotifications : '' }}</span>
                </button>
            </div>
        </div>

        <!-- ─── STATS ─── -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Total Sessions</span>
                    <span class="stat-icon"><i class="fas fa-chart-column" aria-hidden="true"></i></span>
                </div>
                <div class="stat-number">{{ $totalSessions }}</div>
                <span class="text-xs text-gray-400">All time</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Active Sessions</span>
                    <span class="stat-icon"><i class="fas fa-circle" aria-hidden="true"></i></span>
                </div>
                <div class="stat-number">{{ $activeSessions }}</div>
                <span class="text-xs text-gray-400">Currently ongoing</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Pending Reviews</span>
                    <span class="stat-icon"><i class="fas fa-clipboard-list" aria-hidden="true"></i></span>
                </div>
                <div class="stat-number">{{ $pendingEvaluations->count() }}</div>
                <span class="text-xs text-gray-400">Awaiting your review</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Active Helpers</span>
                    <span class="stat-icon"><i class="fas fa-users" aria-hidden="true"></i></span>
                </div>
                <div class="stat-number">{{ $activeHelpers }} / {{ $totalHelpers }}</div>
                <span class="text-xs text-gray-400">Online now</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="card lg:col-span-2">
                <div class="card-header">
                    <h3>Matching Oversight</h3>
                    <a href="{{ route('adviser.schedule') }}">Manage schedules</a>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    <div class="p-3 bg-gray-50 rounded-xl"><div class="text-xs text-gray-400">Helpers</div><div class="font-bold text-gray-800">{{ $matchingStats['total_helpers'] }}</div></div>
                    <div class="p-3 bg-gray-50 rounded-xl"><div class="text-xs text-gray-400">Available</div><div class="font-bold text-gray-800">{{ $matchingStats['available_helpers'] }}</div></div>
                    <div class="p-3 bg-gray-50 rounded-xl"><div class="text-xs text-gray-400">Avg Competency</div><div class="font-bold text-gray-800">{{ $matchingStats['average_competency'] }}</div></div>
                    <div class="p-3 bg-gray-50 rounded-xl"><div class="text-xs text-gray-400">Avg Match</div><div class="font-bold text-gray-800">{{ $matchingStats['avg_matching_score'] }}%</div></div>
                </div>
                <div class="space-y-2">
                    @foreach($helpers->take(6) as $helper)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                            <div>
                                <p class="font-medium text-gray-800">{{ $helper->user?->name ?? $helper->full_name }}</p>
                                <p class="text-xs text-gray-500">{{ ucfirst($helper->availability ?? $helper->status) }} · {{ str_replace('_', ' ', $helper->getReadinessStatus()) }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-gray-500">{{ $helper->current_shift_sessions }}/{{ \App\Models\Helper::MAX_SESSIONS_PER_SHIFT }}</span>
                                <a class="text-sm text-green-600 hover:underline" href="{{ route('adviser.helper.matching', $helper->id) }}">Details</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3>Matching Alerts</h3>
                    <a href="{{ route('adviser.transcripts') }}">Transcripts</a>
                </div>
                <div class="space-y-3 text-sm">
                    <div class="p-3 rounded-xl {{ $helpersAtCapacity->isEmpty() ? 'bg-green-50 text-green-700' : 'bg-yellow-50 text-yellow-700' }}">
                        {{ $helpersAtCapacity->count() }} helper(s) at shift capacity
                    </div>
                    <div class="p-3 rounded-xl {{ $helpersExpiredReadiness->isEmpty() ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' }}">
                        {{ $helpersExpiredReadiness->count() }} helper(s) need readiness refresh
                    </div>
                    <div class="p-3 rounded-xl bg-blue-50 text-blue-700">
                        {{ $assignedQueueCount }} assigned queue request(s)
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── HIGH RISK ALERT ─── -->
        @if($highRiskCases->isNotEmpty())
            <div class="mb-6 p-4 bg-red-50 rounded-xl border border-red-200">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle text-red-500 text-xl"></i>
                    <div>
                        <p class="text-sm font-medium text-red-700">High-Risk Cases Require Attention</p>
                        <p class="text-xs text-red-600">Review these cases immediately</p>
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($highRiskCases as $case)
                        <span class="px-3 py-1 bg-white rounded-full text-xs font-medium text-red-600 border border-red-200">
                            {{ $case->seeker->generated_alias ?? 'Anonymous' }} - {{ ucfirst($case->risk_level) }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ─── LEFT COLUMN (2/3) ─── -->
            <div class="lg:col-span-2">

                <!-- Pending Evaluations -->
                <div class="card mb-6">
                    <div class="card-header">
                        <h3>Pending Evaluations</h3>
                        <a href="{{ route('adviser.evaluations') }}">View all</a>
                    </div>
                    @if($pendingEvaluations->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($pendingEvaluations->take(5) as $report)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                    <div>
                                        <p class="font-medium text-gray-800">
                                            {{ $report->session->seeker->generated_alias ?? 'Anonymous' }}
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            {{ $report->session->helper->first_name ?? 'Unknown' }} · {{ $report->created_at->format('M d, Y') }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="status-badge pending">Pending</span>
                                        <a href="#" class="text-sm text-green-600 hover:underline">Review</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-8 text-gray-400">
                            <i class="fas fa-check-circle text-4xl mb-2 block text-green-500"></i>
                            <p>No pending evaluations</p>
                        </div>
                    @endif
                </div>

                <!-- Evaluation Queue -->
                <div class="card mb-6">
                    <div class="card-header">
                        <h3>Evaluation Queue</h3>
                        <span class="text-xs text-gray-400">Session waiting time</span>
                    </div>
                    <div class="space-y-2">
                        @foreach($waitingTimeStats as $sessionId => $data)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                <div>
                                    <p class="font-medium text-gray-800">{{ $sessionId }}</p>
                                    <p class="text-sm text-gray-500">{{ $data['helper'] }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="status-badge {{ strtolower(str_replace(' ', '-', $data['status'])) }}">
                                        {{ $data['status'] }}
                                    </span>
                                    <span class="text-sm text-gray-400">{{ $data['waiting'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Pre-work Feedback -->
                <div class="card">
                    <div class="card-header">
                        <h3>Pre-work Feedback Given</h3>
                    </div>
                    <div class="space-y-2">
                        @foreach($helperProgress as $item)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                <div>
                                    <p class="font-medium text-gray-800">{{ $item['session'] }}</p>
                                    <p class="text-sm text-gray-500">{{ $item['feedback'] }}</p>
                                </div>
                                <span class="text-xs text-green-600 font-medium"><i class="fas fa-check" aria-hidden="true"></i> Feedback given</span>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>

            <!-- ─── RIGHT COLUMN (1/3) ─── -->
            <div class="lg:col-span-1">

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Activity</h3>
                    </div>
                    <div>
                        @foreach($recentActivity as $activity)
                            <div class="activity-item">
                                <div class="icon {{ $activity['type'] }}">
                                    @if($activity['type'] == 'emergency')
                                        <i class="fas fa-exclamation"></i>
                                    @elseif($activity['type'] == 'evaluation')
                                        <i class="fas fa-star"></i>
                                    @elseif($activity['type'] == 'referral')
                                        <i class="fas fa-arrow-right"></i>
                                    @else
                                        <i class="fas fa-user"></i>
                                    @endif
                                </div>
                                <div class="content">
                                    <div class="message">{{ $activity['message'] }}</div>
                                    <div class="detail">{{ $activity['detail'] }}</div>
                                    <div class="time">{{ $activity['time'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>

        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Quality supervision leads to quality support.
        </div>

    </main>

    <!-- ══════════════════════════════════════════════ -->
    <!-- BOTTOM NAVIGATION                            -->
    <!-- ══════════════════════════════════════════════ -->

    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('adviser.dashboard') }}" class="nav-item active">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('adviser.evaluations') }}" class="nav-item">
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

    <!-- ══════════════════════════════════════════════ -->
    <!-- JAVASCRIPT                                   -->
    <!-- ══════════════════════════════════════════════ -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // ── Sidebar Toggle ──
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

            // ── Bottom Nav Active State ──
            document.querySelectorAll('.bottom-nav .nav-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    document.querySelectorAll('.bottom-nav .nav-item').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });

            // ── Sidebar Nav Active State ──
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
