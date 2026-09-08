<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Incoming Queue</title>

    @vite(['resources/js/app.js', 'resources/js/moderator-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

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
            box-shadow: 0 4px 20px var(--shadow-color, rgba(0,0,0,0.01));
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: var(--text-primary, #1F2937); }

        .queue-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color, #F3F4F6);
            transition: background 0.2s;
        }
        .queue-item:hover { background: var(--hover-bg, #F9FAFB); }
        .queue-item:last-child { border-bottom: none; }

        .priority-badge {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }
        .priority-badge.emergency { background: #FEE2E2; color: #DC2626; animation: pulse-risk 1.5s infinite; }
        .priority-badge.high { background: #FEE2E2; color: #991B1B; }
        .priority-badge.moderate { background: #FEF3C7; color: #92400E; }
        .priority-badge.low { background: #DCFCE7; color: #166534; }
        @keyframes pulse-risk { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

        .type-pill { font-size: 11px; color: #6B7280; background: #F3F4F6; padding: 3px 10px; border-radius: 20px; white-space: nowrap; }
        .wait-chip {
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            white-space: nowrap;
        }
        .wait-chip.ok { background: #F0FDF4; color: #059669; }
        .wait-chip.warn { background: #FFFBEB; color: #D97706; }
        .wait-chip.alert { background: #FEF2F2; color: #DC2626; }

        .btn-primary {
            background: linear-gradient(135deg, #04A052, #038A45);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary:hover { background: #027039; }
        .btn-outline {
            background: white;
            color: #6B7280;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            border: 1.5px solid #E5E7EB;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-outline:hover { border-color: #04A052; color: #04A052; }

        .assign-select {
            padding: 8px 12px;
            border-radius: 12px;
            border: 1.5px solid var(--border-color, #E5E7EB);
            font-size: 13px;
            outline: none;
            background: var(--bg-input, white);
            color: var(--text-primary, #1F2937);
            min-width: 170px;
        }
        .assign-select:focus { border-color: #04A052; box-shadow: 0 0 0 3px rgba(4,160,82,0.08); }

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
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: #04A052; }
        .bottom-nav .nav-item.active i { color: #04A052; }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .queue-item { flex-wrap: wrap; }
            .assign-select { width: 100%; }
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
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Incoming Queue</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">Assign helpers to waiting help seekers</p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-xs text-gray-400">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
                Auto-refreshes every 30 seconds
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="flash-error"><i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}</div>
        @endif

        <!-- Queue Status -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="stat-label">Waiting</div>
                <div class="stat-number text-amber-600" id="statWaiting">{{ $stats['waiting'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Assigned</div>
                <div class="stat-number text-blue-600" id="statAssigned">{{ $stats['assigned'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg Wait Time</div>
                <div class="stat-number text-emerald-600" id="statAvgWait">{{ $stats['avg_wait'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Unserved (30+ min)</div>
                <div class="stat-number {{ $stats['unserved'] > 0 ? 'text-red-600' : 'text-gray-800' }}" id="statUnserved">{{ $stats['unserved'] }}</div>
            </div>
        </div>

        <!-- Queue Metrics -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            @php
                $metrics = [
                    ['label' => 'Queue Size', 'value' => $stats['queue_size'], 'icon' => 'fa-list-ol'],
                    ['label' => 'Avg Wait', 'value' => $stats['avg_wait'], 'icon' => 'fa-clock'],
                    ['label' => 'Avg Holding', 'value' => $stats['avg_holding'], 'icon' => 'fa-pause-circle'],
                    ['label' => 'Unserved Waiting', 'value' => $stats['unserved'], 'icon' => 'fa-user-clock'],
                    ['label' => 'Unserved Jobs', 'value' => $stats['queue_size'] - $stats['assigned'], 'icon' => 'fa-exclamation'],
                ];
            @endphp
            @foreach($metrics as $metric)
                <div class="bg-white rounded-2xl border border-gray-100 p-4 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gray-50 flex items-center justify-center flex-shrink-0">
                        <i class="fas {{ $metric['icon'] }} text-gray-400"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-gray-800 text-sm truncate">{{ $metric['value'] }}</p>
                        <p class="text-[11px] text-gray-400 truncate">{{ $metric['label'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Waiting Seekers -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Waiting Seekers</h3>
                <span class="text-xs text-gray-400">{{ $queueItems->where('request_status', 'waiting')->count() }} waiting</span>
            </div>
            <div>
                @forelse($queueItems->where('request_status', 'waiting') as $item)
                    <div class="queue-item fade-in">
                        <span class="priority-badge {{ $item->priority_level }}">
                            {{ ucfirst($item->priority_level) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-800 text-sm">{{ $item->seeker?->generated_alias ?? 'Anonymous' }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $item->concern_name }}</p>
                        </div>
                        <span class="type-pill hidden md:inline">
                            <i class="fas {{ $item->preferred_session_type === 'voice' ? 'fa-microphone-alt' : 'fa-comment-dots' }} mr-1"></i>{{ ucfirst($item->preferred_session_type) }}
                        </span>
                        <span class="wait-chip {{ $item->wait_minutes >= 30 ? 'alert' : ($item->wait_minutes >= 15 ? 'warn' : 'ok') }}">
                            <i class="fas fa-hourglass-half mr-1"></i>{{ $item->wait_minutes }} min
                        </span>
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('moderator.queue.assign') }}" class="flex items-center gap-2">
                                @csrf
                                <input type="hidden" name="queue_id" value="{{ $item->id }}">
                                <select name="helper_id" class="assign-select" required>
                                    <option value="">Select helper...</option>
                                    @foreach($availableHelpers as $helper)
                                        <option value="{{ $helper->id }}">
                                            {{ $helper->full_name }} · {{ $helper->competency_level }}/5
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn-primary">
                                    <i class="fas fa-user-check"></i> Assign
                                </button>
                            </form>
                            <button type="button"
                                    class="btn-outline text-red-600 hover:border-red-500 hover:text-red-600"
                                    data-remove-url="{{ route('moderator.queue.remove', $item->id) }}">
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-10 text-gray-400">
                        <p class="text-3xl mb-2">🎉</p>
                        <p>No seekers waiting. Queue is clear!</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Assigned -->
        <div class="card">
            <div class="card-header">
                <h3>Recently Matched</h3>
                <span class="text-xs text-gray-400">{{ $queueItems->where('request_status', 'assigned')->count() }} assigned</span>
            </div>
            <div>
                @forelse($queueItems->where('request_status', 'assigned') as $item)
                    <div class="queue-item fade-in">
                        <span class="priority-badge {{ $item->priority_level }}">
                            {{ ucfirst($item->priority_level) }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-gray-800 text-sm">{{ $item->seeker?->generated_alias ?? 'Anonymous' }}</p>
                            <p class="text-xs text-gray-500">
                                Matched {{ $item->matched_date?->diffForHumans() }} with
                                <span class="font-semibold text-emerald-600">{{ $item->assignedHelper?->full_name ?? '—' }}</span>
                            </p>
                        </div>
                        <form method="POST" action="{{ route('moderator.queue.reassign') }}" class="flex items-center gap-2">
                            @csrf
                            <input type="hidden" name="queue_id" value="{{ $item->id }}">
                            <select name="helper_id" class="assign-select">
                                @foreach($availableHelpers as $helper)
                                    <option value="{{ $helper->id }}" {{ $item->assigned_helper_id === $helper->id ? 'selected' : '' }}>
                                        {{ $helper->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn-outline">
                                <i class="fas fa-sync-alt"></i> Reassign
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-400">
                        <p>No recent matches</p>
                    </div>
                @endforelse
            </div>
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('moderator.dashboard') }}" class="nav-item">
            <i class="fas fa-th-large"></i><span>Dashboard</span>
        </a>
        <a href="{{ route('moderator.queue') }}" class="nav-item active">
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
                fetch('/moderator/queue/stats', {
                    headers: { 'Accept': 'application/json' },
                })
                    .then((response) => response.json())
                    .then((data) => {
                        document.getElementById('statWaiting').textContent = data.waiting;
                        document.getElementById('statAssigned').textContent = data.assigned;
                        document.getElementById('statAvgWait').textContent = data.avg_wait;
                        document.getElementById('statUnserved').textContent = data.unserved;
                        document.getElementById('statUnserved').className = data.unserved > 0 ? 'stat-number text-red-600' : 'stat-number text-gray-800';

                        const qb = document.getElementById('queueBadge');
                        if (qb) { qb.textContent = data.waiting; qb.style.display = data.waiting > 0 ? 'inline-block' : 'none'; }
                        document.getElementById('queueCount') && (document.getElementById('queueCount').textContent = data.waiting);
                    })
                    .catch(() => {});
            }, 30000);

            // ── Real-time: remove a request from the queue ──
            const csrfToken = () => document.querySelector('meta[name="csrf-token"]').content;

            document.querySelectorAll('[data-remove-url]').forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const ok = await confirmAction({
                        title: 'Remove from queue?',
                        message: 'This will remove the help seeker from the incoming queue.',
                        confirmText: 'Remove',
                        confirmClass: 'bg-red-600 hover:bg-red-700 focus:ring-red-600',
                    });
                    if (!ok) return;

                    setButtonLoading(btn, 'Removing…');
                    fetch(btn.dataset.removeUrl, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken(),
                            'Accept': 'application/json',
                        },
                    })
                        .then((r) => r.json())
                        .then((data) => {
                            if (data.success) {
                                showToast(data.message, 'success');
                                btn.closest('.queue-item')?.remove();
                            } else {
                                showToast(data.message || 'Could not remove request.', 'error');
                                resetButton(btn);
                            }
                        })
                        .catch(() => {
                            showToast('Network error — please try again.', 'error');
                            resetButton(btn);
                        });
                });
            });
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
