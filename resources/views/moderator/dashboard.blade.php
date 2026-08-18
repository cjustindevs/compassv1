<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Moderator Dashboard</title>

    @vite(['resources/js/app.js', 'resources/js/moderator-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F8FBF9; }

        .main-content {
            margin-left: 260px;
            padding: 24px 32px 80px;
            min-height: 100vh;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid #E5E7EB;
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.04); }
        .stat-number { font-size: 28px; font-weight: 800; color: #1F2937; }
        .stat-number.emergency { color: #DC2626; }
        .stat-number.active { color: #2563EB; }
        .stat-number.queue { color: #D97706; }
        .stat-number.helper { color: #059669; }
        .stat-label { font-size: 13px; color: #6B7280; }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: #1F2937; }

        .session-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #F3F4F6;
            cursor: pointer;
            transition: background 0.2s;
        }
        .session-item:hover { background: #F9FAFB; }
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
        .activity-item .content .message { font-weight: 500; font-size: 14px; color: #1F2937; }
        .activity-item .content .detail { font-size: 13px; color: #6B7280; }
        .activity-item .content .time { font-size: 11px; color: #9CA3AF; }

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
            background: white;
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
        .btn-outline:hover { background: #FEF2F2; }

        .flash-success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }
        .flash-error { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }

        .hamburger { display: none; background: none; border: none; font-size: 24px; color: #374151; cursor: pointer; padding: 4px; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.25); z-index: 99; }
        .sidebar-overlay.active { display: block; }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.94);
            backdrop-filter: blur(16px);
            border-top: 1px solid #E5E7EB;
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
            z-index: 200;
            justify-content: space-around;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #9CA3AF;
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 12px;
            transition: all 0.2s ease;
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: #04A052; }
        .bottom-nav .nav-item.active i { color: #04A052; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .stat-number { font-size: 22px; }
            .session-item { flex-wrap: wrap; gap: 8px; }
        }
        @media (max-width: 480px) {
            .stat-number { font-size: 18px; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body>

    @include('layouts.partials.moderator-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Moderator Dashboard</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Overview of the peer support program</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
                <a href="{{ route('moderator.emergency') }}" class="btn-outline">
                    <i class="fas fa-exclamation-triangle"></i> Emergency
                </a>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="flash-error"><i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}</div>
        @endif

        <!-- High Risk Banner -->
        @if($highRiskSessions->isNotEmpty())
            <div class="bg-red-50 border border-red-200 rounded-2xl p-4 mb-6 flex flex-wrap items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-red-800 text-sm">High-risk sessions need attention</p>
                    <p class="text-xs text-red-600">
                        @foreach($highRiskSessions as $hs)
                            <a href="{{ route('moderator.sessions.show', $hs->id) }}" class="underline hover:text-red-800 mr-3">
                                {{ $hs->seeker?->generated_alias ?? 'Anonymous' }} ({{ ucfirst($hs->risk_level) }})
                            </a>
                        @endforeach
                    </p>
                </div>
                <a href="{{ route('moderator.sessions') }}" class="text-xs font-semibold text-red-700 hover:underline">Monitor →</a>
            </div>
        @endif

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Active Sessions</span>
                    <span class="text-2xl">💬</span>
                </div>
                <div class="stat-number active" id="statSessions">{{ $activeSessions }}</div>
                <span class="text-xs text-gray-400">{{ $chatSessions }} chat · {{ $voiceSessions }} voice</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Queue</span>
                    <span class="text-2xl">⏳</span>
                </div>
                <div class="stat-number queue" id="statQueue">{{ $queueWaiting }}</div>
                <span class="text-xs text-gray-400">Avg wait: <span id="avgWait">{{ $avgWait }}</span></span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Emergency</span>
                    <span class="text-2xl">🚨</span>
                </div>
                <div class="stat-number emergency" id="statEmergency">{{ $emergencyCount }}</div>
                <span class="text-xs text-gray-400">Open emergencies</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Helpers</span>
                    <span class="text-2xl">👥</span>
                </div>
                <div class="stat-number helper" id="statHelpers">{{ $availableHelpers }}</div>
                <span class="text-xs text-gray-400">{{ $busyHelpers }} busy</span>
            </div>
        </div>

        <!-- Live Sessions -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Live Sessions</h3>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-400">Click a row to monitor</span>
                    <a href="{{ route('moderator.sessions') }}" class="text-sm text-[#04A052] hover:underline">View all</a>
                </div>
            </div>
            <div>
                @forelse($sessions as $session)
                    <div class="session-item" onclick="window.location.href='{{ route('moderator.sessions.show', $session->id) }}'">
                        <div class="flex items-center gap-3 flex-wrap">
                            <span class="font-semibold text-gray-800">{{ $session->reference_number }}</span>
                            <span class="text-gray-500 text-sm">{{ $session->seeker?->generated_alias ?? 'Anonymous' }}</span>
                            <span class="risk-badge {{ $session->risk_level ?? 'low' }}">
                                {{ ucfirst($session->risk_level ?? 'Low') }}
                            </span>
                            @if($session->session_status === 'helper_assigned')
                                <span class="status-badge assigned">Awaiting Start</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm text-gray-500"><i class="fas fa-user-tie mr-1"></i>{{ $session->helper?->full_name ?? 'Unassigned' }}</span>
                            <span class="text-sm text-gray-400"><i class="fas fa-clock mr-1"></i>{{ $session->elapsed_label ?? $session->created_date?->diffForHumans() }}</span>
                            <span class="text-xs text-gray-400"><i class="fas fa-microphone-alt mr-1"></i>{{ $session->mode_label }}</span>
                            <span class="status-badge active">{{ $session->status_label }}</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-400">
                        <p class="text-3xl mb-2">💬</p>
                        <p>No active sessions right now</p>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h3>Recent Activity</h3>
                </div>
                <div>
                    @forelse($recentActivity as $activity)
                        <a href="{{ $activity['link'] }}" class="activity-item no-underline">
                            <div class="icon {{ $activity['type'] }}">
                                <i class="{{ $activity['icon'] }}"></i>
                            </div>
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
                            <i class="fas fa-hourglass-half text-amber-600 text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800 text-sm">Review Incoming Queue</p>
                            <p class="text-xs text-gray-500">{{ $queueWaiting }} waiting · {{ $unserved }} waiting 30+ min</p>
                        </div>
                        <i class="fas fa-chevron-right text-gray-300"></i>
                    </a>
                    <a href="{{ route('moderator.emergency') }}" class="flex items-center gap-4 p-4 bg-red-50 border border-red-100 rounded-2xl hover:bg-red-100 transition no-underline">
                        <div class="w-11 h-11 rounded-xl bg-red-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-red-600 text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800 text-sm">Emergency Alerts</p>
                            <p class="text-xs text-gray-500">{{ $emergencyCount }} open cases require review</p>
                        </div>
                        <i class="fas fa-chevron-right text-gray-300"></i>
                    </a>
                    <a href="{{ route('moderator.manage') }}" class="flex items-center gap-4 p-4 bg-blue-50 border border-blue-100 rounded-2xl hover:bg-blue-100 transition no-underline">
                        <div class="w-11 h-11 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-users-cog text-blue-600 text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800 text-sm">Manage Team</p>
                            <p class="text-xs text-gray-500">{{ $availableHelpers }} helpers available for assignment</p>
                        </div>
                        <i class="fas fa-chevron-right text-gray-300"></i>
                    </a>
                    <a href="{{ route('moderator.analytics') }}" class="flex items-center gap-4 p-4 bg-emerald-50 border border-emerald-100 rounded-2xl hover:bg-emerald-100 transition no-underline">
                        <div class="w-11 h-11 rounded-xl bg-emerald-100 flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-chart-line text-emerald-600 text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800 text-sm">Analytics</p>
                            <p class="text-xs text-gray-500">View program performance metrics</p>
                        </div>
                        <i class="fas fa-chevron-right text-gray-300"></i>
                    </a>
                </div>
            </div>
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('moderator.dashboard') }}" class="nav-item active">
            <i class="fas fa-th-large"></i><span>Dashboard</span>
        </a>
        <a href="{{ route('moderator.queue') }}" class="nav-item">
            <i class="fas fa-hourglass-half"></i><span>Queue</span>
        </a>
        <a href="{{ route('moderator.sessions') }}" class="nav-item">
            <i class="fas fa-comments"></i><span>Sessions</span>
        </a>
        <a href="{{ route('moderator.emergency') }}" class="nav-item">
            <i class="fas fa-exclamation-triangle"></i><span>Emergency</span>
        </a>
        <a href="{{ route('moderator.analytics') }}" class="nav-item">
            <i class="fas fa-chart-line"></i><span>Analytics</span>
        </a>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
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

            window.addEventListener('resize', function () {
                if (window.innerWidth > 768) closeSidebar();
            });

            setInterval(function () {
                fetch('/moderator/dashboard/stats', {
                    headers: { 'Accept': 'application/json' },
                })
                    .then((response) => response.json())
                    .then((data) => {
                        const sessions = document.getElementById('statSessions');
                        const queue = document.getElementById('statQueue');
                        const emergency = document.getElementById('statEmergency');
                        const helpers = document.getElementById('statHelpers');
                        const avgWait = document.getElementById('avgWait');

                        if (sessions) sessions.textContent = data.active_sessions;
                        if (queue) queue.textContent = data.queue_waiting;
                        if (emergency) emergency.textContent = data.emergency_open;
                        if (helpers) helpers.textContent = data.helpers_available;
                        if (avgWait) avgWait.textContent = data.avg_wait;

                        const qb = document.getElementById('queueBadge');
                        if (qb) { qb.textContent = data.queue_waiting; qb.style.display = data.queue_waiting > 0 ? 'inline-block' : 'none'; }
                        const sb = document.getElementById('sessionBadge');
                        if (sb) { sb.textContent = data.active_sessions; sb.style.display = data.active_sessions > 0 ? 'inline-block' : 'none'; }
                        const eb = document.getElementById('emergencyBadge');
                        if (eb) { eb.textContent = data.emergency_open; eb.style.display = data.emergency_open > 0 ? 'inline-block' : 'none'; }
                        document.getElementById('liveCount') && (document.getElementById('liveCount').textContent = data.active_sessions);
                        document.getElementById('queueCount') && (document.getElementById('queueCount').textContent = data.queue_waiting);
                        document.getElementById('emergencyCount') && (document.getElementById('emergencyCount').textContent = data.emergency_open);
                    })
                    .catch(() => {});
            }, 30000);
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
