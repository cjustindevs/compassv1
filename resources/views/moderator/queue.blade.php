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
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto auto auto;
            align-items: center;
            gap: 10px 16px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color, #F3F4F6);
            transition: background 0.2s;
        }
        .queue-item:hover { background: var(--hover-bg, #F9FAFB); }
        .queue-item:last-child { border-bottom: none; }

        /* Grouped helper controls; Remove stays outside the form at the end. */
        .queue-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .queue-actions form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .btn-danger { color: #DC2626; }
        .btn-danger:hover { border-color: #DC2626; color: #DC2626; }
        .alias { font-weight: 600; color: var(--text-primary, #1F2937); overflow-wrap: anywhere; }

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
            .queue-item { grid-template-columns: auto minmax(0, 1fr); }
            .queue-item .type-pill,
            .queue-item .wait-chip { grid-column: 2; justify-self: start; }
            .queue-actions { grid-column: 1 / -1; justify-content: stretch; }
            .queue-actions form { flex: 1 1 100%; }
            .assign-select { flex: 1 1 auto; width: 100%; min-width: 0; }
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
                    ['key' => 'queue_size', 'label' => 'Queue Size', 'value' => $stats['queue_size'], 'icon' => 'fa-list-ol'],
                    ['key' => 'avg_wait', 'label' => 'Avg Wait', 'value' => $stats['avg_wait'], 'icon' => 'fa-clock'],
                    ['key' => 'avg_holding', 'label' => 'Avg Holding', 'value' => $stats['avg_holding'], 'icon' => 'fa-pause-circle'],
                    ['key' => 'unserved', 'label' => 'Unserved Waiting', 'value' => $stats['unserved'], 'icon' => 'fa-user-clock'],
                    ['key' => 'oldest_wait', 'label' => 'Oldest Wait', 'value' => $stats['oldest_wait'], 'icon' => 'fa-hourglass-half'],
                ];
            @endphp
            @foreach($metrics as $metric)
                <div class="bg-white rounded-2xl border border-gray-100 p-4 flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gray-50 flex items-center justify-center flex-shrink-0">
                        <i class="fas {{ $metric['icon'] }} text-gray-400"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold text-gray-800 text-sm truncate" data-metric="{{ $metric['key'] }}">{{ $metric['value'] }}</p>
                        <p class="text-[11px] text-gray-400 truncate">{{ $metric['label'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Priority service targets (P1–P4) -->
        <div class="bg-white rounded-2xl border border-gray-100 p-4 mb-6">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs">
                <span class="font-semibold text-gray-700 uppercase tracking-wider text-[11px]">Service targets by priority class</span>
                @foreach(['P1'=>'Emergency','P2'=>'High','P3'=>'Moderate','P4'=>'Low'] as $class=>$label)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="px-2 py-0.5 rounded-full font-bold text-white {{ $class==='P1'?'bg-red-600':($class==='P2'?'bg-orange-500':($class==='P3'?'bg-amber-500':'bg-gray-400')) }}">{{ $class }}</span>
                        {{ $label }} · target ≤ {{ ['P1'=>1,'P2'=>3,'P3'=>10,'P4'=>20][$class] }} min
                    </span>
                @endforeach
            </div>
        </div>

        <!-- Waiting Seekers -->
        <div class="card mb-6">
            <div class="card-header">
                <h3>Waiting Seekers</h3>
                <span class="text-xs text-gray-400" id="waitingHeading">{{ $queueItems->where('request_status', 'waiting')->count() }} waiting</span>
            </div>
            <div>
                @forelse($queueItems->where('request_status', 'waiting') as $item)
                    <div class="queue-item fade-in" data-queue-row="{{ $item->id }}">
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold text-white whitespace-nowrap {{ $item->priority_level==='emergency'?'bg-red-600':($item->priority_level==='high'?'bg-orange-500':($item->priority_level==='moderate'?'bg-amber-500':'bg-gray-400')) }}"
                              title="Priority class {{ \App\Models\QueueRequest::priorityClass($item->priority_level) }}">
                            {{ \App\Models\QueueRequest::priorityClass($item->priority_level) }} · {{ ucfirst($item->priority_level) }}
                        </span>
                        <div class="min-w-0">
                            <p class="alias text-sm">{{ $item->seeker?->generated_alias ?? 'Anonymous' }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ $item->concern_name }}</p>
                        </div>
                        <span class="type-pill hidden md:inline-flex items-center">
                            <i class="fas {{ $item->preferred_session_type === 'voice' ? 'fa-microphone-alt' : 'fa-comment-dots' }} mr-1"></i>{{ ucfirst($item->preferred_session_type) }}
                        </span>
                        <span class="wait-chip {{ $item->wait_minutes >= 30 ? 'alert' : ($item->wait_minutes >= 15 ? 'warn' : 'ok') }}">
                            <i class="fas fa-hourglass-half mr-1"></i>{{ $item->wait_minutes }} min
                        </span>
                        <div class="queue-actions">
                            <form method="POST" action="{{ route('moderator.queue.assign') }}" class="assign-form" data-queue-id="{{ $item->id }}">
                                @csrf
                                <input type="hidden" name="queue_id" value="{{ $item->id }}">
                                <label class="sr-only" for="helper-{{ $item->id }}">Eligible helper for queue request {{ $item->id }}</label>
                                <select id="helper-{{ $item->id }}" name="helper_id" class="assign-select" required>
                                    <option value="">Select helper…</option>
                                    @foreach($availableHelpers->sortByDesc(fn($candidate) => $candidate->calculateMatchingScore($item->supportSession?->risk_level ?? 'low', $item->supportSession?->concern?->concern_name, $item->seeker?->user?->preferred_language)) as $helper)
                                        @php($eligibility = app(\App\Services\HelperEligibilityService::class)->status($helper, $item->supportSession))
                                        <option @disabled(!$eligibility['assignable']) data-ineligible="{{ $eligibility['assignable'] ? '0' : '1' }}" value="{{ $helper->id }}"
                                            title="{{ $eligibility['reason'] }}">
                                            {{ $helper->full_name }} · {{ $eligibility['assignable'] ? '✓ Ready · ' . $helper->remaining_capacity . '/' . \App\Models\Helper::MAX_SESSIONS_PER_SHIFT : $eligibility['label'] }} · {{ $helper->competency_level }}/5
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn-primary whitespace-nowrap">
                                    <i class="fas fa-user-check"></i> Assign
                                </button>
                            </form>
                            <form method="POST" action="{{ route('moderator.queue.remove', $item->id, false) }}" onsubmit="if (this.dataset.submitting) return false; if (!window.confirm('Remove this queue entry? Waiting requests will be cancelled; unaccepted assignments will return to waiting. Started sessions cannot be removed.')) return false; this.dataset.submitting = '1'; this.querySelector('button').disabled = true;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-outline btn-danger whitespace-nowrap">
                                <i class="fas fa-times"></i> Remove
                            </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-10 text-gray-400">
                        <p>No seekers waiting. Queue is clear!</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Assigned -->
        <div class="card">
            <div class="card-header">
                <h3>Recently Matched</h3>
                <span class="text-xs text-gray-400" id="assignedHeading">{{ $queueItems->where('request_status', 'assigned')->count() }} assigned</span>
            </div>
            <div>
                @forelse($queueItems->where('request_status', 'assigned') as $item)
                    <div class="queue-item fade-in" data-queue-row="{{ $item->id }}">
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold text-white whitespace-nowrap {{ $item->priority_level==='emergency'?'bg-red-600':($item->priority_level==='high'?'bg-orange-500':($item->priority_level==='moderate'?'bg-amber-500':'bg-gray-400')) }}"
                              title="Priority class {{ \App\Models\QueueRequest::priorityClass($item->priority_level) }}">
                            {{ \App\Models\QueueRequest::priorityClass($item->priority_level) }} · {{ ucfirst($item->priority_level) }}
                        </span>
                        <div class="min-w-0">
                            <p class="alias text-sm">{{ $item->seeker?->generated_alias ?? 'Anonymous' }}</p>
                            <p class="text-xs text-gray-500 truncate">
                                Matched {{ $item->matched_date?->diffForHumans() }} with
                                <span class="font-semibold text-emerald-600">{{ $item->assignedHelper?->full_name ?? '—' }}</span>
                            </p>
                        </div>
                        <span class="type-pill hidden md:inline-flex items-center">
                            <i class="fas {{ $item->preferred_session_type === 'voice' ? 'fa-microphone-alt' : 'fa-comment-dots' }} mr-1"></i>{{ ucfirst($item->preferred_session_type) }}
                        </span>
                        <span class="wait-chip ok">
                            <i class="fas fa-calendar-check mr-1"></i>{{ $item->scheduled_date ? $item->scheduled_date->copy()->setTimezone(config('app.schedule_timezone'))->format('M d, h:i A') : 'Awaiting acceptance' }}
                        </span>
                        <div class="queue-actions">
                            <form method="POST" action="{{ route('moderator.queue.schedule') }}">
                                @csrf
                                <label class="sr-only" for="scheduled_at-{{ $item->id }}">Appointment time for queue request {{ $item->id }}</label>
                                <input type="datetime-local" id="scheduled_at-{{ $item->id }}" name="scheduled_at" class="assign-select"
                                       value="{{ $item->scheduled_date?->copy()->setTimezone(config('app.schedule_timezone'))->format('Y-m-d\TH:i') }}"
                                       title="Set the appointment time for this already-assigned session ({{ config('app.schedule_timezone') }}).">
                                <input type="hidden" name="queue_id" value="{{ $item->id }}">
                                <button type="submit" class="btn-outline whitespace-nowrap">
                                    <i class="fas fa-calendar-alt"></i> Schedule
                                </button>
                            </form>
                            <form method="POST" action="{{ route('moderator.queue.reassign') }}">
                                @csrf
                                <input type="hidden" name="queue_id" value="{{ $item->id }}">
                                <label class="sr-only" for="reassign-{{ $item->id }}">Replacement helper for queue request {{ $item->id }}</label>
                                <select id="reassign-{{ $item->id }}" name="helper_id" class="assign-select">
                                    <option value="">Select helper…</option>
                                    @foreach($availableHelpers->sortByDesc(fn($candidate) => $candidate->calculateMatchingScore($item->supportSession?->risk_level ?? 'low', $item->supportSession?->concern?->concern_name, $item->seeker?->user?->preferred_language)) as $helper)
                                        @php($eligibility = app(\App\Services\HelperEligibilityService::class)->status($helper, $item->supportSession))
                                        <option @disabled(!$eligibility['assignable']) data-ineligible="{{ $eligibility['assignable'] ? '0' : '1' }}" value="{{ $helper->id }}" {{ $item->assigned_helper_id === $helper->id ? 'selected' : '' }}
                                            title="{{ $eligibility['reason'] }}">
                                            {{ $helper->full_name }} · {{ $eligibility['assignable'] ? '✓ Ready · ' . $helper->remaining_capacity . '/' . \App\Models\Helper::MAX_SESSIONS_PER_SHIFT : $eligibility['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn-outline whitespace-nowrap">
                                    <i class="fas fa-sync-alt"></i> Reassign
                                </button>
                            </form>
                            <form method="POST" action="{{ route('moderator.queue.remove', $item->id, false) }}" onsubmit="if (this.dataset.submitting) return false; if (!window.confirm('Remove this queue entry? Waiting requests will be cancelled; unaccepted assignments will return to waiting. Started sessions cannot be removed.')) return false; this.dataset.submitting = '1'; this.querySelector('button').disabled = true;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-outline btn-danger whitespace-nowrap"><i class="fas fa-times" aria-hidden="true"></i> Remove assignment</button>
                            </form>
                        </div>
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

            const csrfToken = () => document.querySelector('meta[name="csrf-token"]').content;
            const setText = (id, value) => {
                const el = document.getElementById(id);
                if (el && value !== undefined && value !== null) el.textContent = value;
            };

            /**
             * Read a fetch response as JSON even when the server answers with an
             * HTML error page (419 CSRF, 403, 409 …). Previously r.json() threw on
             * those, which surfaced as a misleading "Network error" and left the
             * button stuck in its loading state.
             */
            const readJson = async (response) => {
                const text = await response.text();
                let data = null;
                try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }
                if (!response.ok) {
                    return {
                        success: false,
                        status: response.status,
                        message: (data && (data.message || data.error))
                            || (response.status === 419
                                ? 'Your session expired. Please refresh the page and try again.'
                                : (response.status === 403
                                    ? 'You are not authorized to perform that action.'
                                    : 'The server rejected that request (HTTP ' + response.status + ').')),
                    };
                }
                return data || { success: false, message: 'Unexpected server response.' };
            };

            // ── Auto-refresh: figures only, never re-render rows ──
            // Updating text nodes in place means dropdown selections, focus and
            // in-flight forms survive the refresh, so rows cannot flicker,
            // duplicate or lose the helper a moderator already picked.
            const refreshStats = async () => {
                // Do not refresh while a moderator is mid-action.
                if (document.querySelector('form.assign-form[data-submitting="1"]')) return;
                if (document.activeElement && document.activeElement.matches('.assign-select')) return;

                try {
                    const response = await fetch('{{ route('moderator.queue.stats') }}', {
                        headers: { 'Accept': 'application/json' },
                        cache: 'no-store',
                    });
                    if (!response.ok) return;
                    const data = await response.json();

                    setText('statWaiting', data.waiting);
                    setText('statAssigned', data.assigned);
                    setText('statAvgWait', data.avg_wait);
                    setText('statUnserved', data.unserved);
                    setText('waitingHeading', data.waiting + ' waiting');
                    setText('assignedHeading', data.assigned + ' assigned');

                    const unserved = document.getElementById('statUnserved');
                    if (unserved) {
                        unserved.className = 'stat-number ' + (data.unserved > 0 ? 'text-red-600' : 'text-gray-800');
                    }

                    document.querySelectorAll('[data-metric]').forEach((el) => {
                        const value = data[el.dataset.metric];
                        if (value !== undefined && value !== null) el.textContent = value;
                    });

                    const qb = document.getElementById('queueBadge');
                    if (qb) {
                        qb.textContent = data.waiting;
                        qb.style.display = data.waiting > 0 ? 'inline-block' : 'none';
                    }
                    setText('queueCount', data.waiting);
                } catch (e) {
                    // Offline or transient failure: leave the last good figures.
                }
            };

            setInterval(refreshStats, 30000);
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) refreshStats();
            });

            // ── Assign: validate the selection and block double submission ──
            document.querySelectorAll('form.assign-form').forEach((form) => {
                form.addEventListener('submit', function (event) {
                    if (form.dataset.submitting === '1') {
                        event.preventDefault();
                        return;
                    }
                    const select = form.querySelector('select[name="helper_id"]');
                    if (!select || !select.value) {
                        event.preventDefault();
                        showToast('Select an eligible helper before assigning.', 'error');
                        if (select) select.focus();
                        return;
                    }
                    const chosen = select.options[select.selectedIndex];
                    if (chosen && chosen.disabled) {
                        event.preventDefault();
                        showToast('That helper is no longer available. Pick another helper.', 'error');
                        if (select) select.focus();
                        return;
                    }
                    form.dataset.submitting = '1';
                    const button = form.querySelector('button[type="submit"]');
                    if (button && window.setButtonLoading) window.setButtonLoading(button, 'Assigning…');
                });
            });

            // ── Remove: cancel a waiting request / release an assignment ──

        });
    </script>

    @include('components.confirmation-modal')

    @include('layouts.partials.pwa-banner')

</body>
</html>
