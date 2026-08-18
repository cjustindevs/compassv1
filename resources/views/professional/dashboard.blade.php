<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Professional Dashboard</title>

    @vite(['resources/js/app.js'])
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
        }

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
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0,0,0,0.04); }
        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
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

        .referral-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-bottom: 1px solid var(--gray-100);
            transition: background 0.2s;
        }
        .referral-item:hover { background: var(--gray-50); }
        .referral-item:last-child { border-bottom: none; }
        .referral-item .referral-info .alias { font-weight: 600; color: var(--gray-800); }
        .referral-item .referral-info .details { font-size: 13px; color: var(--gray-500); }
        .referral-item .referral-info .reason { font-size: 13px; color: var(--gray-600); }

        .priority-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .priority-badge.low { background: var(--gray-200); color: var(--gray-600); }
        .priority-badge.moderate { background: #FEF3C7; color: #D97706; }
        .priority-badge.high { background: #FEE2E2; color: #DC2626; }
        .priority-badge.emergency { background: #FEE2E2; color: #DC2626; animation: pulse-risk 1.5s infinite; }
        @keyframes pulse-risk { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge.pending_professional { background: #FEF3C7; color: #D97706; }
        .status-badge.accepted { background: #DBEAFE; color: #1D4ED8; }
        .status-badge.in_progress { background: #E0E7FF; color: #4338CA; }
        .status-badge.completed { background: var(--green-50); color: var(--green-700); }
        .status-badge.closed { background: var(--gray-200); color: var(--gray-600); }
        .status-badge.declined { background: #FEE2E2; color: #DC2626; }

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

        .notification-item {
            display: flex;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            transition: background 0.2s;
        }
        .notification-item:hover { background: var(--gray-50); }
        .notification-item .notif-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: var(--green-50);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        .notification-item .notif-title { font-weight: 600; font-size: 13px; color: var(--gray-800); }
        .notification-item .notif-msg { font-size: 12px; color: var(--gray-500); }
        .notification-item .notif-time { font-size: 11px; color: var(--gray-400); }

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

        .flash-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: white;
            border-left: 4px solid var(--green-500);
            border-radius: 12px;
            padding: 14px 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            font-size: 14px;
            font-weight: 500;
            color: var(--gray-800);
            animation: flashIn 0.3s ease-out;
        }
        .flash-alert.info { border-left-color: #3B82F6; }
        .flash-alert.error { border-left-color: #EF4444; }
        @keyframes flashIn { from { transform: translateY(-10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .stat-number { font-size: 22px; }
            .referral-item { flex-direction: column; align-items: flex-start; gap: 10px; }
        }
        @media (max-width: 480px) {
            .stat-number { font-size: 18px; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body>

    @include('layouts.partials.professional-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Professional Dashboard</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Welcome back, {{ $professional->title ?? auth()->user()->name }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
                <a href="{{ route('professional.referrals') }}" class="btn-primary hidden sm:inline-flex">
                    <i class="fas fa-clipboard-list"></i> View Referrals
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="stat-number" id="statPending">{{ $pendingReferrals }}</div>
                        <div class="stat-label">Pending Referrals</div>
                    </div>
                    <div class="stat-icon" style="background:#FEF3C7;color:#D97706;">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">Awaiting your decision</p>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="stat-number" id="statActive">{{ $activeCases }}</div>
                        <div class="stat-label">Active Cases</div>
                    </div>
                    <div class="stat-icon" style="background:#DBEAFE;color:#1D4ED8;">
                        <i class="fas fa-folder-open"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">In your care right now</p>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="stat-number" id="statCompleted">{{ $completedCases }}</div>
                        <div class="stat-label">Completed Interventions</div>
                    </div>
                    <div class="stat-icon" style="background:#EAF8F0;color:#04A052;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">Completed or closed</p>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="stat-number" id="statResponse">
                            @if($avgResponseHours > 48)
                                {{ number_format($avgResponseHours / 24, 1) }}<span class="text-base">d</span>
                            @else
                                {{ $avgResponseHours }}<span class="text-base">h</span>
                            @endif
                        </div>
                        <div class="stat-label">Avg Response Time</div>
                    </div>
                    <div class="stat-icon" style="background:#E0E7FF;color:#4338CA;">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">Assignment to decision</p>
            </div>
        </div>

        <!-- Quick Actions (mobile) -->
        <div class="flex gap-3 mb-6 sm:hidden">
            <a href="{{ route('professional.referrals') }}" class="btn-primary flex-1 justify-center">
                <i class="fas fa-clipboard-list"></i> Referrals
            </a>
            <a href="{{ route('professional.cases') }}" class="btn-outline flex-1 justify-center">
                <i class="fas fa-folder-open"></i> Cases
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Recent Referrals -->
            <div class="card">
                <div class="card-header">
                    <h3>Recent Referrals</h3>
                    <a href="{{ route('professional.referrals') }}" class="text-xs font-semibold text-[#04A052] hover:underline">View all</a>
                </div>

                @if($recentReferrals->isNotEmpty())
                    <div class="space-y-1">
                        @foreach($recentReferrals as $referral)
                            <div class="referral-item">
                                <div class="referral-info">
                                    <div class="alias">
                                        {{ $referral->session->seeker->generated_alias ?? 'Anonymous' }}
                                        <span class="priority-badge {{ $referral->priority_level }}">{{ ucfirst($referral->priority_level) }}</span>
                                        <span class="status-badge {{ $referral->status }}">{{ ucfirst(str_replace('_', ' ', $referral->status)) }}</span>
                                    </div>
                                    <div class="details">
                                        Referral #{{ $referral->id }} · {{ $referral->created_at->format('M d, Y') }}
                                    </div>
                                    <div class="reason">
                                        {{ \Illuminate\Support\Str::limit($referral->referral_reason, 60) }}
                                    </div>
                                </div>
                                <a href="{{ route('professional.referral.show', $referral->id) }}" class="btn-outline">
                                    <i class="fas fa-eye mr-1"></i> View
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-10 text-gray-400">
                        <i class="fas fa-clipboard-list text-5xl mb-3 block text-green-500"></i>
                        <p class="text-lg font-medium text-gray-600">No referrals yet</p>
                        <p class="text-sm">Referrals from advisers will appear here</p>
                    </div>
                @endif
            </div>

            <!-- Active Cases -->
            <div class="card">
                <div class="card-header">
                    <h3>Active Cases</h3>
                    <a href="{{ route('professional.cases') }}" class="text-xs font-semibold text-[#04A052] hover:underline">View all</a>
                </div>

                @if($activeCasesList->isNotEmpty())
                    <div class="space-y-1">
                        @foreach($activeCasesList as $case)
                            <div class="referral-item">
                                <div class="referral-info">
                                    <div class="alias">
                                        {{ $case->session->seeker->generated_alias ?? 'Anonymous' }}
                                        <span class="status-badge {{ $case->status }}">{{ ucfirst(str_replace('_', ' ', $case->status)) }}</span>
                                    </div>
                                    <div class="details">
                                        Case #{{ $case->id }} ·
                                        Last update: {{ optional($case->professionalNotes->first())->created_at?->diffForHumans() ?? $case->updated_at->diffForHumans() }}
                                    </div>
                                    <div class="reason">
                                        {{ \Illuminate\Support\Str::limit($case->referral_reason, 60) }}
                                    </div>
                                </div>
                                <a href="{{ route('professional.cases.show', $case->id) }}" class="btn-outline">
                                    <i class="fas fa-arrow-right mr-1"></i> Open
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-10 text-gray-400">
                        <i class="fas fa-folder-open text-5xl mb-3 block text-green-500"></i>
                        <p class="text-lg font-medium text-gray-600">No active cases</p>
                        <p class="text-sm">Accept a referral to start a case</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Recent Activity / Notifications -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Recent Activity</h3>
                @if($unreadNotifications > 0)
                    <span class="status-badge accepted">{{ $unreadNotifications }} unread</span>
                @endif
            </div>

            @if($notifications->isNotEmpty())
                <div class="space-y-1">
                    @foreach($notifications as $notification)
                        <div class="notification-item">
                            <div class="notif-icon">{{ $notification->type_icon }}</div>
                            <div class="flex-1 min-w-0">
                                <div class="notif-title">{{ $notification->title }}</div>
                                <div class="notif-msg">{{ \Illuminate\Support\Str::limit($notification->message, 90) }}</div>
                            </div>
                            <span class="notif-time flex-shrink-0">{{ $notification->created_at->diffForHumans() }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-10 text-gray-400">
                    <i class="fas fa-bell text-5xl mb-3 block text-green-500"></i>
                    <p class="text-lg font-medium text-gray-600">No recent activity</p>
                    <p class="text-sm">Notifications about referrals and cases will appear here</p>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Professional care, delivered with compassion.
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('professional.dashboard') }}" class="nav-item active">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="{{ route('professional.referrals') }}" class="nav-item">
            <i class="fas fa-clipboard-list"></i>
            <span>Referrals</span>
        </a>
        <a href="{{ route('professional.cases') }}" class="nav-item">
            <i class="fas fa-folder-open"></i>
            <span>Cases</span>
        </a>
        <a href="{{ route('professional.reports') }}" class="nav-item">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>
        <a href="{{ route('professional.profile') }}" class="nav-item">
            <i class="fas fa-user-circle"></i>
            <span>Profile</span>
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

            // Live badge refresh
            setInterval(function() {
                fetch('{{ route('professional.dashboard.stats') }}')
                    .then(res => res.json())
                    .then(data => {
                        document.getElementById('statPending').textContent = data.pending;
                        document.getElementById('statActive').textContent = data.active;
                        document.getElementById('statCompleted').textContent = data.completed;
                        document.getElementById('sidePending').textContent = data.pending;
                        document.getElementById('sideActive').textContent = data.active;
                        document.getElementById('sideCompleted').textContent = data.completed;
                    })
                    .catch(() => {});
            }, 30000);

            // Auto-dismiss flash messages
            document.querySelectorAll('.flash-alert').forEach(alert => {
                setTimeout(() => alert.remove(), 5000);
            });
        });
    </script>

    @if(session('success'))
        <div class="flash-alert"><i class="fas fa-check-circle text-[#04A052] mr-2"></i>{{ session('success') }}</div>
    @endif
    @if(session('info'))
        <div class="flash-alert info"><i class="fas fa-info-circle text-blue-500 mr-2"></i>{{ session('info') }}</div>
    @endif
    @if(session('error'))
        <div class="flash-alert error"><i class="fas fa-exclamation-circle text-red-500 mr-2"></i>{{ session('error') }}</div>
    @endif

    @include('layouts.partials.pwa-banner')

</body>
</html>