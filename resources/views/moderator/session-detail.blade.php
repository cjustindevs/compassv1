<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Monitoring {{ $session->reference_number }}</title>

    @vite(['resources/js/app.js', 'resources/js/moderator-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg-primary, #F8FBF9); }

        .main-content {
            margin-left: 260px;
            max-width: 1000px;
            padding: 24px 32px 80px;
            min-height: 100vh;
        }

        .card {
            background: var(--bg-card, white);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border-color, #E5E7EB);
            box-shadow: var(--card-shadow, 0 4px 20px rgba(0,0,0,0.01));
        }

        .risk-badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .risk-badge.low { background: #DCFCE7; color: #166534; }
        .risk-badge.moderate { background: #FEF3C7; color: #92400E; }
        .risk-badge.high { background: #FEE2E2; color: #991B1B; }
        .risk-badge.emergency { background: #FEE2E2; color: #991B1B; animation: pulse-risk 1.5s infinite; }
        @keyframes pulse-risk { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

        .status-pill { font-size: 12px; font-weight: 600; padding: 3px 12px; border-radius: 20px; }
        .status-pill.active { background: #DBEAFE; color: #1D4ED8; }
        .status-pill.assigned { background: #FEF3C7; color: #92400E; }
        .status-pill.ended { background: #DCFCE7; color: #166534; }

        .info-chip {
            background: var(--bg-hover, #F9FAFB);
            border: 1px solid #F3F4F6;
            border-radius: 14px;
            padding: 12px 16px;
        }
        .info-chip .label { font-size: 11px; color: var(--text-muted, #9CA3AF); text-transform: uppercase; letter-spacing: 0.04em; }
        .info-chip .value { font-size: 14px; font-weight: 600; color: var(--text-primary, #1F2937); margin-top: 2px; }

        .msg-bubble {
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 13px;
            max-width: 80%;
            line-height: 1.5;
        }
        .msg-bubble.seeker { background: #EAF8F0; color: #065F46; }
        .msg-bubble.helper { background: var(--border-light, #F3F4F6); color: var(--text-primary, #374151); }

        .flash-success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }

        .hamburger { display: none; background: none; border: none; font-size: 24px; color: var(--text-primary, #374151); cursor: pointer; padding: 4px; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.25); z-index: 99; }
        .sidebar-overlay.active { display: block; }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--navbar-bg, rgba(255,255,255,0.94));
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
            color: var(--text-muted, #9CA3AF);
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 12px;
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: #04A052; }
        .bottom-nav .nav-item.active i { color: #04A052; }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body class="compass-compact">

    @include('layouts.partials.moderator-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <div class="flex items-center gap-4 mb-6">
            <button class="hamburger" id="hamburgerBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-800">Monitoring {{ $session->reference_number }}</h1>
                <p class="text-sm text-gray-500 hidden sm:block">Live session monitor</p>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif

        <!-- Back Link -->
        <a href="{{ route('moderator.sessions') }}" class="inline-flex items-center gap-2 text-gray-500 hover:text-gray-700 mb-6 text-sm font-medium">
            <i class="fas fa-arrow-left"></i> Back to Active Sessions
        </a>

        <!-- Header -->
        <div class="card mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-gray-200">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-full bg-emerald-500 flex items-center justify-center text-white text-xl font-bold">
                        {{ $session->seeker?->generated_alias ? substr($session->seeker->generated_alias, 0, 2) : 'AN' }}
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">{{ $session->seeker?->generated_alias ?? 'Anonymous' }}</h2>
                        <p class="text-sm text-gray-500">
                            {{ $session->concern?->concern_name ?? 'General Concern' }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="risk-badge {{ $session->risk_level ?? 'low' }}">{{ ucfirst($session->risk_level ?? 'Low') }} risk</span>
                    <span class="status-pill {{ $session->session_status === 'active' ? 'active' : 'assigned' }}">
                        {{ $session->status_label }}
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4">
                <div class="info-chip">
                    <p class="label">Helper</p>
                    <p class="value">{{ $session->helper?->full_name ?? 'Unassigned' }}</p>
                </div>
                <div class="info-chip">
                    <p class="label">Mode</p>
                    <p class="value capitalize">{{ $session->mode_label }}</p>
                </div>
                <div class="info-chip">
                    <p class="label">Elapsed</p>
                    <p class="value">{{ $session->elapsed_label }}</p>
                </div>
                <div class="info-chip">
                    <p class="label">Started</p>
                    <p class="value">{{ $session->start_time?->format('h:i A') ?? $session->created_date?->format('h:i A') }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Conversation Preview -->
            <div class="card lg:col-span-2">
                <h3 class="font-bold text-gray-800 mb-4">Recent Messages <span class="text-xs font-normal text-gray-400">({{ $session->messages->count() }} shown)</span></h3>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    @forelse($session->messages as $message)
                        <div class="flex {{ $message->is_helper ? 'justify-end' : 'justify-start' }}">
                            <div class="msg-bubble {{ $message->is_helper ? 'helper' : 'seeker' }}">
                                <p class="text-[10px] mb-1 {{ $message->is_helper ? 'text-gray-400' : 'text-emerald-700' }}">
                                    {{ $message->is_helper ? ($session->helper?->full_name ?? 'Helper') : ($session->seeker?->generated_alias ?? 'Seeker') }}
                                </p>
                                {{ $message->message_text }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-10 text-gray-400">
                            <p class="text-3xl mb-2"><i class="fas fa-comments" aria-hidden="true"></i></p>
                            <p>No messages yet</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Side Panel -->
            <div class="space-y-6">
                <div class="card">
                    <h3 class="font-bold text-gray-800 mb-4">Session Info</h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-400">Session ID</dt>
                            <dd class="font-semibold text-gray-700">{{ $session->reference_number }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-400">Type</dt>
                            <dd class="font-semibold text-gray-700 capitalize">{{ $session->session_type }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-400">Voice Consent</dt>
                            <dd class="font-semibold text-gray-700">{{ $session->voice_recording_consent ? 'Granted' : 'Not required' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-400">Scheduled</dt>
                            <dd class="font-semibold text-gray-700">{{ $session->scheduled_start?->format('M d, h:i A') ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-400">Duration</dt>
                            <dd class="font-semibold text-gray-700">{{ $session->duration ? $session->duration . ' min' : '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="card">
                    <h3 class="font-bold text-gray-800 mb-4">Helper</h3>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-semibold text-sm">
                            {{ substr($session->helper?->first_name ?? '?', 0, 1) }}{{ substr($session->helper?->last_name ?? '?', 0, 1) }}
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 text-sm">{{ $session->helper?->full_name ?? 'Unassigned' }}</p>
                            <p class="text-xs text-gray-400">
                                Competency: {{ $session->helper?->latestCompetency?->competency_level ?? '—' }}
                            </p>
                        </div>
                    </div>
                    @if($session->helper)
                        <div class="flex items-center gap-2 text-xs">
                            <span class="w-2 h-2 rounded-full {{ $session->helper->status === 'available' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                            <span class="text-gray-500">{{ ucfirst($session->helper->status) }}</span>
                        </div>
                    @endif
                </div>

                @if($session->incidents->isNotEmpty())
                    <div class="card border-red-200">
                        <h3 class="font-bold text-red-700 mb-4"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> Incidents on this session</h3>
                        @foreach($session->incidents as $incident)
                            <div class="mb-3 pb-3 border-b border-red-50 last:border-0 last:mb-0 last:pb-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="risk-badge {{ $incident->risk_level }}">{{ ucfirst($incident->risk_level) }}</span>
                                    <span class="text-xs text-gray-400">{{ $incident->created_at?->diffForHumans() }}</span>
                                </div>
                                <p class="text-sm font-semibold text-gray-800">{{ $incident->incident_category }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ $incident->description }}</p>
                                <p class="text-xs mt-2">
                                    <span class="text-gray-400">Status:</span>
                                    <span class="font-semibold text-gray-600 capitalize">{{ str_replace('_', ' ', $incident->status) }}</span>
                                </p>
                            </div>
                        @endforeach
                        <a href="{{ route('moderator.emergency') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-red-600 hover:underline mt-2">
                            <i class="fas fa-exclamation-triangle"></i> Go to Emergency Workspace
                        </a>
                    </div>
                @endif

                @if($session->evaluation)
                    <div class="card">
                        <h3 class="font-bold text-gray-800 mb-3">Seeker Rating</h3>
                        <p class="text-3xl font-extrabold text-gray-800">{{ $session->evaluation->overall_score }}<span class="text-base text-gray-400">/5</span></p>
                        <p class="text-xs text-gray-400 mt-1">{{ $session->evaluation->comments ?: 'No comments' }}</p>
                    </div>
                @endif
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
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
