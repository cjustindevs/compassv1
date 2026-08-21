<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Reports & Analytics</title>

    @vite(['resources/js/app.js', 'resources/js/adviser-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F8FBF9; }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px);
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
        .sidebar .logo span { font-weight: 700; font-size: 20px; color: var(--green-700); }

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
        .sidebar .user-section .user-card .info .name { font-weight: 600; font-size: 14px; color: var(--gray-800); }
        .sidebar .user-section .user-card .info .role { font-size: 12px; color: var(--gray-400); }
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

        .filter-select {
            padding: 8px 14px;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            outline: none;
            font-size: 13px;
            background: white;
        }
        .filter-select:focus { border-color: var(--green-500); }

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

        .chart-bar {
            display: flex;
            align-items: flex-end;
            height: 120px;
            gap: 6px;
        }
        .chart-bar .bar {
            flex: 1;
            border-radius: 4px 4px 0 0;
            min-height: 8px;
            transition: height 0.5s ease;
            background: linear-gradient(180deg, #04A052, #38C172);
        }
        .chart-bar .bar-label {
            font-size: 10px;
            text-align: center;
            color: var(--gray-400);
            margin-top: 4px;
        }

        .ranking-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 12px;
            transition: background 0.2s;
        }
        .ranking-item:hover { background: var(--gray-50); }
        .ranking-item .rank { font-weight: 700; font-size: 14px; color: var(--gray-400); width: 24px; }
        .ranking-item .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 12px;
            flex-shrink: 0;
        }
        .ranking-item .info { flex: 1; }
        .ranking-item .info .name { font-weight: 500; font-size: 14px; color: var(--gray-800); }
        .ranking-item .info .detail { font-size: 12px; color: var(--gray-400); }
        .ranking-item .score { font-weight: 600; font-size: 14px; color: var(--gray-800); }

        .competency-level {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .competency-level.expert { background: #dcfce7; color: #166534; }
        .competency-level.advanced { background: #dbeafe; color: #1e40af; }
        .competency-level.intermediate { background: #fef3c7; color: #92400e; }
        .competency-level.beginner { background: #fee2e2; color: #991b1b; }
        .competency-level.trainee { background: #e5e7eb; color: #6b7280; }

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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Reports & Analytics</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Program performance metrics and helper analytics
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <form action="{{ route('adviser.reports.export') }}" method="GET" class="inline">
                    <input type="hidden" name="period" value="{{ $period }}">
                    <input type="hidden" name="helper_id" value="{{ $helperId }}">
                    @if(request('from'))
                        <input type="hidden" name="from" value="{{ request('from') }}">
                        <input type="hidden" name="to" value="{{ request('to') }}">
                    @endif
                    <button type="submit" class="btn-outline">
                        <i class="fas fa-file-export mr-1"></i> Export
                    </button>
                </form>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-6 flex flex-wrap items-center gap-4">
            <form method="GET" action="{{ route('adviser.reports') }}" class="flex flex-wrap items-center gap-3">
                <select name="period" class="filter-select">
                    <option value="weekly" {{ $period == 'weekly' ? 'selected' : '' }}>Last 7 Days</option>
                    <option value="monthly" {{ $period == 'monthly' ? 'selected' : '' }}>Last 30 Days</option>
                    <option value="quarterly" {{ $period == 'quarterly' ? 'selected' : '' }}>Last 90 Days</option>
                    <option value="yearly" {{ $period == 'yearly' ? 'selected' : '' }}>Last Year</option>
                </select>

                <select name="helper_id" class="filter-select">
                    <option value="">All Helpers</option>
                    @foreach($helpers as $helper)
                        <option value="{{ $helper->id }}" {{ $helperId == $helper->id ? 'selected' : '' }}>
                            {{ $helper->first_name }} {{ $helper->last_name }}
                        </option>
                    @endforeach
                </select>

                <input type="date" name="from" value="{{ request('from') }}" class="filter-select" title="From date">
                <input type="date" name="to" value="{{ request('to') }}" class="filter-select" title="To date">

                <button type="submit" class="btn-primary">
                    <i class="fas fa-filter mr-1"></i> Apply Filters
                </button>
            </form>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Total Sessions</span>
                    <span class="text-2xl">📊</span>
                </div>
                <div class="stat-number">{{ $totalSessions }}</div>
                <span class="text-xs text-gray-400">All sessions</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Completion Rate</span>
                    <span class="text-2xl">✅</span>
                </div>
                <div class="stat-number">{{ $completionRate }}%</div>
                <span class="text-xs text-gray-400">{{ $completedSessions }} completed</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Avg Response</span>
                    <span class="text-2xl">⏱️</span>
                </div>
                <div class="stat-number">{{ $avgResponseTime }}</div>
                <span class="text-xs text-gray-400">First response</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Avg Waiting</span>
                    <span class="text-2xl">🕐</span>
                </div>
                <div class="stat-number">{{ $avgWaitingTime }}</div>
                <span class="text-xs text-gray-400">Queue to session</span>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">

            <!-- Referral Stats -->
            <div class="card">
                <div class="card-header">
                    <h3>Referral Statistics</h3>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Total Referrals</span>
                        <span class="font-bold text-gray-800">{{ $referralStats['total'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Approved</span>
                        <span class="font-bold text-green-600">{{ $referralStats['approved'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Pending Review</span>
                        <span class="font-bold text-yellow-600">{{ $referralStats['pending'] }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Declined</span>
                        <span class="font-bold text-red-600">{{ $referralStats['declined'] }}</span>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-gray-200">
                        <span class="text-sm font-semibold text-gray-700">Acceptance Rate</span>
                        <span class="font-bold text-gray-800">{{ $referralStats['acceptance_rate'] }}%</span>
                    </div>
                </div>
            </div>

            <!-- Satisfaction Scores -->
            <div class="card">
                <div class="card-header">
                    <h3>Satisfaction Scores</h3>
                </div>
                <div class="space-y-3">
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Overall Satisfaction</span>
                            <span class="font-bold text-gray-800">{{ $satisfactionScores['overall'] }} / 5</span>
                        </div>
                        <div class="w-full h-2 bg-gray-200 rounded-full mt-1">
                            <div class="h-full bg-green-500 rounded-full" style="width: {{ ($satisfactionScores['overall'] / 5) * 100 }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Helpfulness</span>
                            <span class="font-bold text-gray-800">{{ $satisfactionScores['helpfulness'] }} / 5</span>
                        </div>
                        <div class="w-full h-2 bg-gray-200 rounded-full mt-1">
                            <div class="h-full bg-green-500 rounded-full" style="width: {{ ($satisfactionScores['helpfulness'] / 5) * 100 }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Comfort Level</span>
                            <span class="font-bold text-gray-800">{{ $satisfactionScores['comfort'] }} / 5</span>
                        </div>
                        <div class="w-full h-2 bg-gray-200 rounded-full mt-1">
                            <div class="h-full bg-green-500 rounded-full" style="width: {{ ($satisfactionScores['comfort'] / 5) * 100 }}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm text-gray-600">Post-Session Feeling</span>
                            <span class="font-bold text-gray-800">{{ $satisfactionScores['feeling'] }} / 5</span>
                        </div>
                        <div class="w-full h-2 bg-gray-200 rounded-full mt-1">
                            <div class="h-full bg-green-500 rounded-full" style="width: {{ ($satisfactionScores['feeling'] / 5) * 100 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Monthly Trends -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Monthly Trends</h3>
                <span class="text-sm text-gray-400">Sessions & performance</span>
            </div>
            <div class="overflow-x-auto">
                <div class="chart-bar" style="height: 180px;">
                    @foreach($monthlyTrends as $trend)
                        <div class="flex-1 flex flex-col items-center">
                            <div class="w-full flex flex-col items-center gap-1">
                                <span class="text-xs text-gray-600 font-medium">{{ $trend['sessions'] }}</span>
                                <div class="bar w-full" style="height: {{ ($trend['sessions'] / 60) * 140 }}px; background: linear-gradient(180deg, #04A052, #38C172);"></div>
                            </div>
                            <span class="text-xs text-gray-400 mt-2">{{ $trend['month'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="flex justify-center gap-6 mt-4 text-xs text-gray-500">
                <span><span class="inline-block w-3 h-3 bg-green-500 rounded-sm mr-1"></span> Sessions</span>
                <span><span class="inline-block w-3 h-3 bg-blue-400 rounded-sm mr-1"></span> Completion Rate</span>
            </div>
        </div>

        <!-- Helper Ranking -->
        <div class="card">
            <div class="card-header">
                <h3>Helper Performance Ranking</h3>
                <span class="text-sm text-gray-400">Top performers</span>
            </div>
            <div class="space-y-1">
                @foreach($helperRanking->take(10) as $index => $helper)
                    <div class="ranking-item">
                        <span class="rank">#{{ $index + 1 }}</span>
                        <div class="avatar" style="background: {{ $helper->competency >= 4 ? '#04A052' : ($helper->competency >= 3 ? '#3B82F6' : '#9CA3AF') }};">
                            {{ $helper->initials }}
                        </div>
                        <div class="info">
                            <div class="name">{{ $helper->name }}</div>
                            <div class="detail">
                                {{ $helper->sessions }} sessions · {{ $helper->rating }} ★
                                <span class="competency-level {{ strtolower($helper->level) }} ml-2">
                                    {{ $helper->level }}
                                </span>
                            </div>
                        </div>
                        <div class="score">{{ $helper->competency }} / 5</div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Data-driven decisions lead to better outcomes.
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('adviser.dashboard') }}" class="nav-item">
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
        <a href="{{ route('adviser.reports') }}" class="nav-item active">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
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