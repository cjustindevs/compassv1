<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Emergency Alerts</title>

    @vite(['resources/js/app.js', 'resources/js/moderator-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            padding: 18px 22px;
            border: 1px solid var(--border-color, #E5E7EB);
            transition: all 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px var(--shadow-color, rgba(0,0,0,0.04)); }
        .stat-number { font-size: 26px; font-weight: 800; color: var(--text-primary, #1F2937); }
        .stat-label { font-size: 12px; color: var(--text-secondary, #6B7280); }

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

        .case-row {
            display: grid;
            grid-template-columns: 70px 130px 1fr 90px 130px 110px 170px;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-light, #F3F4F6);
        }
        .case-row:last-child { border-bottom: none; }
        .case-row.emergency { background: #FEF2F2; border-left: 3px solid #DC2626; }
        .case-row.high { background: #FFF7F7; border-left: 3px solid #F97316; }

        .case-head {
            display: grid;
            grid-template-columns: 70px 130px 1fr 90px 130px 110px 170px;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted, #9CA3AF);
            border-bottom: 1px solid #E5E7EB;
        }

        .risk-badge { padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-align: center; }
        .risk-badge.emergency { background: #FEE2E2; color: #DC2626; animation: pulse-risk 1.5s infinite; }
        .risk-badge.high { background: #FEE2E2; color: #991B1B; }
        .risk-badge.moderate { background: #FEF3C7; color: #92400E; }
        .risk-badge.low { background: #DCFCE7; color: #166534; }
        @keyframes pulse-risk { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

        .status-pill { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 20px; text-align: center; }
        .status-pill.open { background: #FEE2E2; color: #DC2626; }
        .status-pill.under_review { background: #FEF3C7; color: #92400E; }
        .status-pill.escalated { background: #DBEAFE; color: #1D4ED8; }
        .status-pill.resolved { background: #DCFCE7; color: #166534; }

        .btn-escalate {
            background: #DC2626;
            color: white;
            padding: 7px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-escalate:hover { background: #B91C1C; }
        .btn-resolve {
            background: #DCFCE7;
            color: #166534;
            padding: 7px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-resolve:hover { background: #BBF7D0; }

        .workflow-step { text-align: center; flex: 1; position: relative; }
        .workflow-step .dot {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--border-light, #F3F4F6);
            color: var(--text-muted, #9CA3AF);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 6px;
            font-size: 13px;
            position: relative;
            z-index: 2;
        }
        .workflow-step.active .dot { background: #04A052; color: white; }
        .workflow-step .label { font-size: 10px; color: var(--text-muted, #9CA3AF); text-transform: uppercase; letter-spacing: 0.03em; }
        .workflow-step .count { font-size: 13px; font-weight: 700; color: var(--text-primary, #1F2937); }
        .workflow-connector { position: absolute; top: 17px; left: calc(50% + 18px); right: calc(-50% + 18px); height: 2px; background: #E5E7EB; }

        .contact-card {
            background: var(--bg-hover, #F9FAFB);
            border: 1px solid #F3F4F6;
            border-radius: 14px;
            padding: 14px 16px;
        }
        .contact-card .agency { font-weight: 600; font-size: 13px; color: var(--text-primary, #1F2937); }
        .contact-card .hotline { font-weight: 800; font-size: 16px; color: #DC2626; }
        .contact-card .desc { font-size: 11px; color: var(--text-muted, #9CA3AF); }

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

        @media (max-width: 1024px) {
            .case-head, .case-row { grid-template-columns: 70px 130px 1fr 130px 170px; }
            .case-head .hide-md, .case-row .hide-md { display: none; }
        }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .case-head { display: none; }
            .case-row { grid-template-columns: 1fr; gap: 6px; padding: 14px 16px; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body class="compass-compact">

    @include('layouts.partials.moderator-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Emergency Alerts</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Incident triage, escalation and resolution</p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs text-gray-400">
                <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse inline-block"></span>
                Real-time monitoring
            </div>
        </div>

        @if(session('success'))
            <div class="bg-[#EAF8F0] text-[#027039] border border-[#D0F0D8] rounded-xl px-4 py-3 text-sm font-medium mb-4">
                <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
            </div>
        @endif

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="stat-label">Open Emergencies</div>
                <div class="stat-number text-red-600" id="statOpen">{{ $stats['open'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Escalated Today</div>
                <div class="stat-number text-blue-600" id="statEscalated">{{ $stats['escalated_today'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg Response</div>
                <div class="stat-number text-gray-800">{{ $stats['avg_response'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Resolved (30d)</div>
                <div class="stat-number text-emerald-600" id="statResolved">{{ $stats['resolved_30d'] }}</div>
            </div>
        </div>

        <!-- Workflow Timeline -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Emergency Workflow</h3>
                <span class="text-xs text-gray-400">Detected → Notified → Reviewing → Referral → Closed</span>
            </div>
            <div class="flex items-start">
                @php
                    $steps = [
                        ['label' => 'Detected', 'count' => $workflow['detected'], 'active' => $workflow['detected'] > 0, 'icon' => 'fa-eye'],
                        ['label' => 'Notified', 'count' => $workflow['notified'], 'active' => $workflow['notified'] > 0, 'icon' => 'fa-bell'],
                        ['label' => 'Reviewing', 'count' => $workflow['reviewing'], 'active' => $workflow['reviewing'] > 0, 'icon' => 'fa-search'],
                        ['label' => 'Referral', 'count' => $workflow['referral'], 'active' => $workflow['referral'] > 0, 'icon' => 'fa-paper-plane'],
                        ['label' => 'Closed', 'count' => $workflow['closed'], 'active' => $workflow['closed'] > 0, 'icon' => 'fa-check'],
                    ];
                @endphp
                @foreach($steps as $i => $step)
                    <div class="workflow-step">
                        @if($i < count($steps) - 1)
                            <div class="workflow-connector"></div>
                        @endif
                        <div class="dot {{ $step['active'] ? 'active' : '' }}">
                            <i class="fas {{ $step['icon'] }}"></i>
                        </div>
                        <div class="count">{{ $step['count'] }}</div>
                        <div class="label">{{ $step['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Active Cases -->
            <div class="card lg:col-span-2 overflow-x-auto">
                <div class="card-header">
                    <h3>Active Emergency Cases</h3>
                    <span class="text-xs text-gray-400">{{ $openIncidents->total() }} active</span>
                </div>
                <div class="min-w-[760px]">
                    <div class="case-head">
                        <span>Case ID</span>
                        <span>Alias</span>
                        <span>Detected By</span>
                        <span>Risk</span>
                        <span>Status</span>
                        <span>Onset</span>
                        <span>Actions</span>
                    </div>
                    @forelse($openIncidents as $incident)
                        <div class="case-row {{ $incident->risk_level }}">
                            <span class="font-semibold text-gray-800">#{{ str_pad($incident->id, 3, '0', STR_PAD_LEFT) }}</span>
                            <span class="text-gray-600 text-sm truncate">{{ $incident->session?->seeker?->generated_alias ?? 'Unknown' }}</span>
                            <span class="text-gray-600 text-sm truncate">
                                <i class="fas fa-user-tie mr-1 text-gray-300"></i>{{ $incident->session?->helper?->full_name ?? 'System' }}
                            </span>
                            <span class="risk-badge {{ $incident->risk_level }}">{{ ucfirst($incident->risk_level) }}</span>
                            <span class="status-pill {{ $incident->status }}">
                                {{ ucwords(str_replace('_', ' ', $incident->status)) }}
                            </span>
                            <span class="text-xs text-gray-500">{{ $incident->created_at?->diffForHumans() }}</span>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('moderator.emergency.escalate', $incident->id) }}"
                                      data-confirm="Escalate emergency?"
                                      data-confirm-message="This will alert higher authorities and cannot be undone lightly."
                                      data-confirm-text="Escalate"
                                      data-confirm-class="bg-red-600 hover:bg-red-700 focus:ring-red-500">
                                    @csrf
                                    <button type="submit" class="btn-escalate" {{ $incident->status === 'escalated' ? 'disabled style=opacity:.35;cursor:not-allowed' : '' }}>
                                        <i class="fas fa-arrow-up mr-1"></i> Escalate
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('moderator.emergency.resolve', $incident->id) }}"
                                      data-confirm="Resolve emergency?"
                                      data-confirm-message="Mark this incident as resolved."
                                      data-confirm-text="Resolve"
                                      data-confirm-class="bg-green-600 hover:bg-green-700 focus:ring-green-500">
                                    @csrf
                                    <button type="submit" class="btn-resolve" {{ in_array($incident->status, ['resolved', 'closed']) ? 'disabled style=opacity:.35;cursor:not-allowed' : '' }}>
                                        <i class="fas fa-check mr-1"></i> Resolve
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-10 text-gray-400">
                            <p class="text-3xl mb-2">✅</p>
                            <p>No open emergency cases</p>
                        </div>
                    @endforelse
                </div>
                {{ $openIncidents->links() }}
            </div>

            <!-- Side Column -->
            <div class="space-y-6">
                <!-- Priority Distribution -->
                <div class="card">
                    <div class="card-header">
                        <h3>Priority Distribution</h3>
                    </div>
                    <canvas id="priorityChart" height="180"></canvas>
                    <div class="space-y-2 mt-4">
                        @foreach(['emergency', 'high', 'moderate', 'low'] as $level)
                            @php $count = $priorityDistribution[$level] ?? 0; @endphp
                            <div class="flex items-center gap-2 text-xs">
                                <span class="w-2.5 h-2.5 rounded-full {{ $level === 'emergency' ? 'bg-red-600' : ($level === 'high' ? 'bg-orange-500' : ($level === 'moderate' ? 'bg-amber-400' : 'bg-emerald-500')) }}"></span>
                                <span class="text-gray-500 capitalize flex-1">{{ $level }}</span>
                                <span class="font-semibold text-gray-700">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Emergency Contacts -->
                <div class="card">
                    <div class="card-header">
                        <h3>Emergency Contacts</h3>
                        <a href="/emergency" target="_blank" class="text-xs text-[#04A052] hover:underline">Seeker view</a>
                    </div>
                    <div class="space-y-3">
                        @forelse($contacts as $contact)
                            <div class="contact-card">
                                <p class="agency">{{ $contact->agency_name }}</p>
                                <p class="hotline">{{ $contact->hotline }}</p>
                                <p class="desc mt-1">{{ $contact->description }}</p>
                            </div>
                        @empty
                            <div class="text-center py-6 text-gray-400 text-sm">
                                No emergency resources configured.
                            </div>
                        @endforelse
                    </div>
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
        <a href="{{ route('moderator.sessions') }}" class="nav-item">
            <i class="fas fa-comments"></i><span>Sessions</span>
        </a>
        <a href="{{ route('moderator.emergency') }}" class="nav-item active">
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

            const priorityData = @json(array_values($priorityDistribution));

            if (window.Chart) {
                new Chart(document.getElementById('priorityChart'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Low', 'Moderate', 'High', 'Emergency'],
                        datasets: [{
                            data: priorityData,
                            backgroundColor: ['#10B981', '#F59E0B', '#F97316', '#EF4444'],
                            borderWidth: 0,
                        }],
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                        },
                        cutout: '68%',
                    },
                });
            }

            setInterval(function () {
                fetch('/moderator/emergency/stats', {
                    headers: { 'Accept': 'application/json' },
                })
                    .then((response) => response.json())
                    .then((data) => {
                        document.getElementById('statOpen').textContent = data.open;
                        document.getElementById('statEscalated').textContent = data.escalated_today;
                        document.getElementById('statResolved').textContent = data.resolved_30d;

                        const eb = document.getElementById('emergencyBadge');
                        if (eb) { eb.textContent = data.open; eb.style.display = data.open > 0 ? 'inline-block' : 'none'; }
                        document.getElementById('emergencyCount') && (document.getElementById('emergencyCount').textContent = data.open);
                    })
                    .catch(() => {});
            }, 30000);
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
