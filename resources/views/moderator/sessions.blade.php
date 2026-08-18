<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Active Sessions</title>

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
            padding: 18px 22px;
            border: 1px solid #E5E7EB;
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.04); }
        .stat-number { font-size: 26px; font-weight: 800; color: #1F2937; }
        .stat-label { font-size: 12px; color: #6B7280; }

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

        .session-row {
            display: grid;
            grid-template-columns: 90px 1fr 1.2fr 90px 90px 80px 110px 30px;
            align-items: center;
            gap: 10px;
            padding: 13px 16px;
            border-bottom: 1px solid #F3F4F6;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 13px;
        }
        .session-row:hover { background: #F9FAFB; }
        .session-row:last-child { border-bottom: none; }
        .session-row.risk-high { background: #FFF7F7; border-left: 3px solid #DC2626; }
        .session-row.risk-emergency { background: #FEF2F2; border-left: 3px solid #DC2626; animation: pulse-row 2s infinite; }
        @keyframes pulse-row { 0%,100% { background: #FEF2F2; } 50% { background: #FEE2E2; } }

        .session-table-head {
            display: grid;
            grid-template-columns: 90px 1fr 1.2fr 90px 90px 80px 110px 30px;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #9CA3AF;
            border-bottom: 1px solid #E5E7EB;
        }

        .risk-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
        }
        .risk-badge.low { background: #DCFCE7; color: #166534; }
        .risk-badge.moderate { background: #FEF3C7; color: #92400E; }
        .risk-badge.high { background: #FEE2E2; color: #991B1B; }
        .risk-badge.emergency { background: #FEE2E2; color: #991B1B; animation: pulse-risk 1.5s infinite; }
        @keyframes pulse-risk { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

        .mode-pill { font-size: 11px; color: #6B7280; background: #F3F4F6; padding: 3px 10px; border-radius: 20px; text-align: center; }
        .status-pill { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; text-align: center; }
        .status-pill.active { background: #DBEAFE; color: #1D4ED8; }
        .status-pill.assigned { background: #FEF3C7; color: #92400E; }

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
            font-size: 13px;
        }
        .activity-item .icon.emergency { background: #FEE2E2; color: #DC2626; }
        .activity-item .icon.assignment { background: #DBEAFE; color: #1D4ED8; }
        .activity-item .icon.session { background: #DCFCE7; color: #166534; }
        .activity-item .content .message { font-weight: 500; font-size: 13px; color: #1F2937; }
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
        .btn-primary:hover { background: #027039; }
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
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: #04A052; }
        .bottom-nav .nav-item.active i { color: #04A052; }

        @media (max-width: 1024px) {
            .session-table-head, .session-row {
                grid-template-columns: 90px 1fr 110px 90px 30px;
            }
            .session-table-head .hide-md, .session-row .hide-md { display: none; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .session-table-head { display: none; }
            .session-row {
                grid-template-columns: 1fr;
                gap: 6px;
                padding: 14px 16px;
            }
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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Active Sessions</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Live monitoring of all ongoing peer support sessions</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('moderator.queue') }}" class="btn-outline">
                    <i class="fas fa-hourglass-half"></i> Review Queue
                </a>
                <a href="{{ route('moderator.emergency') }}" class="btn-primary">
                    <i class="fas fa-exclamation-triangle"></i> Emergency
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="stat-card">
                <div class="stat-label">Ongoing</div>
                <div class="stat-number text-blue-600" id="statOngoing">{{ $stats['ongoing'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Chat</div>
                <div class="stat-number text-gray-800" id="statChat">{{ $stats['chat'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Voice</div>
                <div class="stat-number text-gray-800" id="statVoice">{{ $stats['voice'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Emergency Flagged</div>
                <div class="stat-number text-red-600" id="statEmergency">{{ $stats['emergency_flagged'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Referrals Pending</div>
                <div class="stat-number text-amber-600">{{ $stats['referrals_pending'] }}</div>
            </div>
        </div>

        <!-- Live Sessions Table -->
        <div class="card mb-6 overflow-x-auto">
            <div class="card-header">
                <h3>Live Sessions</h3>
                <span class="text-xs text-gray-400">Click a row for the monitoring drawer</span>
            </div>
            <div class="min-w-[760px]">
                <div class="session-table-head">
                    <span>Session ID</span>
                    <span>Alias</span>
                    <span>Helper</span>
                    <span>Risk</span>
                    <span>Duration</span>
                    <span>Mode</span>
                    <span>Status</span>
                    <span></span>
                </div>
                <div id="sessionList">
                    @forelse($sessions as $session)
                        <div class="session-row risk-{{ $session->risk_level ?? 'low' }}" data-id="{{ $session->id }}" onclick="window.location.href='{{ route('moderator.sessions.show', $session->id) }}'">
                            <span class="font-semibold text-gray-800">{{ $session->reference_number }}</span>
                            <span class="text-gray-600 truncate">{{ $session->seeker?->generated_alias ?? 'Anonymous' }}</span>
                            <span class="text-gray-600 truncate">
                                <i class="fas fa-user-tie mr-1 text-gray-400"></i>{{ $session->helper?->full_name ?? 'Unassigned' }}
                            </span>
                            <span class="risk-badge {{ $session->risk_level ?? 'low' }}">{{ ucfirst($session->risk_level ?? 'Low') }}</span>
                            <span class="text-gray-500"><i class="fas fa-clock mr-1 text-gray-300"></i>{{ $session->elapsed_label }}</span>
                            <span class="mode-pill">
                                <i class="fas {{ $session->session_type === 'voice' ? 'fa-microphone-alt' : 'fa-comment-dots' }} mr-1"></i>{{ $session->mode_label }}
                            </span>
                            <span class="status-pill {{ $session->session_status === 'active' ? 'active' : 'assigned' }}">
                                {{ $session->session_status === 'active' ? 'Active' : 'Awaiting Start' }}
                            </span>
                            <i class="fas fa-chevron-right text-gray-300 text-xs"></i>
                        </div>
                    @empty
                        <div class="text-center py-10 text-gray-400">
                            <p class="text-3xl mb-2">💬</p>
                            <p>No active sessions right now</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Live Activity Feed -->
            <div class="card">
                <div class="card-header">
                    <h3>Live Activity</h3>
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                </div>
                <div>
                    @forelse($recentActivity as $activity)
                        <div class="activity-item">
                            <div class="icon {{ $activity['type'] }}">
                                <i class="{{ $activity['icon'] }}"></i>
                            </div>
                            <div class="content">
                                <div class="message">{{ $activity['message'] }}</div>
                                <div class="time">{{ $activity['time'] }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-400">
                            <p>No recent activity</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Workload Overview -->
            <div class="card">
                <div class="card-header">
                    <h3>Helper Workload</h3>
                    <a href="{{ route('moderator.manage') }}" class="text-sm text-[#04A052] hover:underline">Manage</a>
                </div>
                <div class="space-y-3">
                    @foreach(\App\Models\Helper::with('sessions')->get()->sortByDesc(fn ($h) => $h->sessions()->whereIn('session_status', ['active', 'helper_assigned'])->count())->take(8) as $helper)
                        @php
                            $load = $helper->sessions()->whereIn('session_status', ['active', 'helper_assigned'])->count();
                            $max = $helper->max_concurrent_sessions ?: 2;
                            $pct = min(100, round(($load / $max) * 100));
                            $color = $pct >= 100 ? 'bg-red-500' : ($pct >= 66 ? 'bg-amber-500' : 'bg-emerald-500');
                        @endphp
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-semibold text-xs flex-shrink-0">
                                {{ substr($helper->first_name, 0, 1) }}{{ substr($helper->last_name, 0, 1) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-sm font-medium text-gray-700 truncate">{{ $helper->full_name }}</span>
                                    <span class="text-xs text-gray-400">{{ $load }}/{{ $max }}</span>
                                </div>
                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full {{ $color }} rounded-full transition-all" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('moderator.dashboard') }}" class="nav-item">
            <i class="fas fa-th-large"></i><span>Dashboard</span>
        </a>
        <a href="{{ route('moderator.queue') }}" class="nav-item">
            <i class="fas fa-hourglass-half"></i><span>Queue</span>
        </a>
        <a href="{{ route('moderator.sessions') }}" class="nav-item active">
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
                fetch('/moderator/sessions/stats', {
                    headers: { 'Accept': 'application/json' },
                })
                    .then((response) => response.json())
                    .then((data) => {
                        document.getElementById('statOngoing').textContent = data.ongoing;
                        document.getElementById('statChat').textContent = data.chat;
                        document.getElementById('statVoice').textContent = data.voice;
                        document.getElementById('statEmergency').textContent = data.emergency_flagged;

                        const sb = document.getElementById('sessionBadge');
                        if (sb) { sb.textContent = data.ongoing; sb.style.display = data.ongoing > 0 ? 'inline-block' : 'none'; }
                        document.getElementById('liveCount') && (document.getElementById('liveCount').textContent = data.ongoing);
                    })
                    .catch(() => {});
            }, 30000);
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
