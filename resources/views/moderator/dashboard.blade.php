<!DOCTYPE html>
<html class="compass-ui" lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Moderator Dashboard</title>

    @vite(['resources/js/app.js', 'resources/js/moderator-notifications.js'])
    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg-primary, #F8FBF9); }

        .main-content {
            margin-left: 260px;
            padding: 24px 32px 80px;
            min-height: 100vh;
        }

        .stat-card {
            background: var(--bg-card, white);
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--border-color, #E5E7EB);
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px var(--shadow-color, rgba(0,0,0,0.04)); }
        .stat-number { font-size: 28px; font-weight: 800; color: var(--text-primary, #1F2937); }
        .stat-number.emergency { color: #DC2626; }
        .stat-number.active { color: #2563EB; }
        .stat-number.queue { color: #D97706; }
        .stat-number.helper { color: #059669; }
        .stat-label { font-size: 13px; color: var(--text-secondary, #6B7280); }

        .card {
            background: var(--bg-card, white);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border-color, #E5E7EB);
            box-shadow: var(--card-shadow, 0 4px 20px rgba(0,0,0,0.01));
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: var(--text-primary, #1F2937); }

        .session-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border-light, #F3F4F6);
            cursor: pointer;
            transition: background 0.2s;
        }
        .session-item:hover { background: var(--bg-hover, #F9FAFB); }
        .session-item:last-child { border-bottom: none; }

        .risk-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        .risk-badge.low { background: #DCFCE7; color: #166534; }
        .risk-badge.moderate { background: #FEF3C7; color: #92400E; }
        .risk-badge.high { background: #FEE2E2; color: #991B1B; }
        .risk-badge.emergency { background: #FEE2E2; color: #991B1B; animation: pulse-risk 1.5s infinite; }
        @keyframes pulse-risk { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.active { background: #DBEAFE; color: #1D4ED8; }
        .status-badge.assigned { background: #FEF3C7; color: #92400E; }

        .activity-item {
            display: flex;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #F3F4F6;
        }
        .activity-item:last-child { border-bottom: none; }
        .activity-item .icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 14px;
        }
        .activity-item .icon.emergency { background: #FEE2E2; color: #DC2626; }
        .activity-item .icon.queue { background: #FEF3C7; color: #D97706; }
        .activity-item .icon.feedback { background: #DCFCE7; color: #166534; }
        .activity-item .icon.assignment { background: #DBEAFE; color: #1D4ED8; }
        .activity-item .content { flex: 1; min-width: 0; }
        .activity-item .content .message { font-weight: 500; font-size: 14px; color: var(--text-primary, #1F2937); }
        .activity-item .content .detail { font-size: 13px; color: var(--text-secondary, #6B7280); }
        .activity-item .content .time { font-size: 11px; color: var(--text-muted, #9CA3AF); }

        .btn-primary {
            background: linear-gradient(135deg, #04A052, #038A45);
            color: white;
            padding: 8px 18px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-primary:hover { background: #027039; transform: scale(1.02); }
        .btn-outline {
            background: var(--bg-card, white);
            color: #DC2626;
            padding: 8px 18px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            border: 1.5px solid #FECACA;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-outline:hover { background: var(--bg-hover, #FEF2F2); }

        .flash-success { background: var(--bg-active, #EAF8F0); color: #027039; border: 1px solid var(--border-color, #D0F0D8); border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        .flash-error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }

        .hamburger { display: none; background: none; border: none; font-size: 24px; color: var(--text-primary, #374151); cursor: pointer; padding: 4px; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.25); z-index: 99; }
        .sidebar-overlay.active { display: block; }


        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .stat-number { font-size: 22px; }
            .session-item { flex-wrap: wrap; gap: 8px; }
        }
        @media (max-width: 480px) {
            .stat-number { font-size: 18px; }
            .card { padding: 16px; }
        }
    </style>
    @vite(['resources/css/app.css'])
    @include('partials.ui-assets')
</head>
<body class="compass-compact">

    @include('layouts.partials.moderator-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn" aria-label="Open navigation" aria-controls="sidebar">
                    <x-ui-icon name="menu"  />
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Moderator Dashboard</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Overview of the peer support program</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
                <a href="{{ route('moderator.emergency') }}" class="btn-outline">
                    <x-ui-icon name="warning"  /> Emergency
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="flash-error"> {{ session('error') }}</div>
        @endif

        <!-- High Risk Banner -->


        <section id="dashboard-analytics" aria-label="Emergency dashboard summaries">@include('moderator.partials.dashboard-analytics')</section>

        <div class="flex flex-wrap gap-6 px-1 py-4 mb-5 border-b border-gray-200">
            <a href="{{ route('moderator.queue') }}">Waiting requests <strong id="statQueue">{{ $stats['queue_waiting'] }}</strong></a>
            <a href="{{ route('moderator.sessions') }}">Active Sessions <strong id="statSessions">{{ $stats['active_sessions'] }}</strong></a>
            <a href="{{ route('moderator.manage') }}">Available Helpers <strong id="statHelpers">{{ $stats['available_helpers'] }}</strong></a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h3>Recent Activity</h3><a href="{{ route('moderator.reports',['tab'=>'activity']) }}" class="text-sm text-green-700">View All</a>
                </div>
                <div>
                    @forelse($recentActivity as $activity)
                        <a href="{{ $activity['link'] }}" class="activity-item no-underline">
                            <div class="content">
                                <div class="message">{{ $activity['title'] }}</div>
                                <div class="detail">{{ $activity['message'] }}</div>
                                <div class="time">{{ $activity['time'] }}</div>
                            </div>
                        </a>
                    @empty
                        <div class="text-center py-8 text-gray-400">
                            <p>No recent activity</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h3>Quick Actions</h3>
                </div>
                <div class="space-y-3">
                    <a href="{{ route('moderator.queue') }}" class="flex items-center gap-4 p-4 bg-amber-50 border border-amber-100 rounded-2xl hover:bg-amber-100 transition no-underline">
                        <div class="w-11 h-11 rounded-xl bg-amber-100 flex items-center justify-center flex-shrink-0">
                            <x-ui-icon name="hourglass" class="text-amber-600 text-lg" />
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800 text-sm">Review Incoming Queue</p>
                            <p class="text-xs text-gray-500">{{ $stats['queue_waiting'] }} waiting · {{ $stats['unserved'] }} waiting 30+ min</p>
                        </div>
                        <x-ui-icon name="chevron-right" class="text-gray-300" />
                    </a>
                    <a href="{{ route('moderator.emergency') }}" class="flex items-center gap-4 p-4 bg-red-50 border border-red-100 rounded-2xl hover:bg-red-100 transition no-underline">
                        <div class="w-11 h-11 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                            <x-ui-icon name="warning" class="text-red-600 text-lg" />
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800 text-sm">Emergency Alerts</p>
                            <p class="text-xs text-gray-500"><span data-active-emergency-count>{{ $stats['emergency_count'] }}</span> open cases require review</p>
                        </div>
                        <x-ui-icon name="chevron-right" class="text-gray-300" />
                    </a>
                    <a href="{{ route('moderator.manage') }}" class="flex items-center gap-4 p-4 bg-blue-50 border border-blue-100 rounded-2xl hover:bg-blue-100 transition no-underline">
                        <div class="w-11 h-11 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                            <x-ui-icon name="users" class="text-blue-600 text-lg" />
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800 text-sm">Manage Team</p>
                            <p class="text-xs text-gray-500">{{ $stats['available_helpers'] }} helpers available for assignment</p>
                        </div>
                        <x-ui-icon name="chevron-right" class="text-gray-300" />
                    </a>
                    <a href="#dashboard-analytics" class="flex items-center gap-4 p-4 bg-emerald-50 border border-emerald-100 rounded-2xl hover:bg-emerald-100 transition no-underline">
                        <div class="w-11 h-11 rounded-xl bg-emerald-100 flex items-center justify-center flex-shrink-0">
                            <x-ui-icon name="chart-line" class="text-emerald-600 text-lg" />
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800 text-sm">Dashboard summaries</p>
                            <p class="text-xs text-gray-500">Emergency status, priority and trends</p>
                        </div>
                        <x-ui-icon name="chevron-right" class="text-gray-300" />
                    </a>
                </div>
            </div>
        </div>

    </main>

    <!-- Bottom Navigation -->

    <meta id="moderatorEmergencyConfig" content="{{ json_encode(['kind' => 'dashboard', 'stats' => route('moderator.dashboard.stats'), 'signature' => $overviewSignature]) }}">
    <script src="{{ asset('js/moderator-emergency.js') }}?v={{ filemtime(public_path('js/moderator-emergency.js')) }}" defer></script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
