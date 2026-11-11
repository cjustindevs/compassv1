@extends('layouts.app')

@section('title', 'COMPASS – Incoming Queue')

@push('styles')
    <meta name="user-id" content="{{ auth()->id() }}">
    @vite(['resources/js/moderator-notifications.js'])
    <style>
        .queue-flash { border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
        .queue-flash.success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; }
        .queue-flash.error { background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; }
        .queue-stats { display: flex; flex-wrap: wrap; align-items: baseline; gap: 8px 32px; font-size: 15px; color: var(--compass-muted, #6B7280); }
        .queue-stats strong { font-size: 24px; font-weight: 800; color: var(--compass-text, #1F2937); margin-left: 6px; }
        .queue-stats .queue-note { font-size: 14px; }
        .queue-targets { display: flex; flex-wrap: wrap; align-items: center; gap: 12px 24px; font-size: 14px; color: var(--compass-muted, #6B7280); }
        .queue-targets span { display: inline-flex; align-items: center; gap: 8px; }
        .queue-count { font-size: 14px; color: var(--compass-muted, #6B7280); font-weight: 500; }
        .queue-alias { font-weight: 600; color: var(--compass-text, #1F2937); overflow-wrap: anywhere; }
        .ui-table td small { display: block; font-size: 13px; color: var(--compass-muted, #6B7280); }
        .queue-actions-cell { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: center; gap: 8px; }
        .queue-actions-cell form { margin: 0; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .assign-select { min-height: 44px; padding: 0 12px; border-radius: 12px; border: 1px solid var(--compass-border, #E5E7EB); font-size: 15px; background: var(--bg-card, #fff); color: var(--compass-text, #1F2937); min-width: 170px; max-width: 100%; }
        .assign-select:focus { outline: 2px solid var(--compass-green, #04A052); outline-offset: 1px; }
        .assign-form, .reassign-form { display: grid; grid-template-columns: minmax(0, 1fr); gap: 8px; }
        .assign-form .assign-select, .reassign-form .assign-select { width: 100%; }
        [data-helper-reason] { display: block; max-width: 340px; white-space: normal; font-size: 13px; color: var(--compass-muted, #6B7280); }
        button:disabled { opacity: .55; cursor: not-allowed; }
        .queue-card + .queue-card { margin-top: 24px; }
        @media (max-width: 768px) { .queue-actions-cell { justify-content: flex-start; } .assign-select { width: 100%; min-width: 0; } }
    </style>
@endpush

@push('scripts')
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
                if (window.CompassDialogs && form.closest('dialog.workflow-dialog')) return;
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

    <span hidden data-queue-helper-options="{{ route('moderator.queue.helper-options') }}"></span>
    <script src="{{ asset('js/queue-helper-options.js') }}?v={{ filemtime(public_path('js/queue-helper-options.js')) }}" defer></script>
@endpush

@section('content')
    @php
        $priorityTone = fn ($level) => match ($level) { 'emergency' => 'red', 'high' => 'orange', 'moderate' => 'amber', default => 'neutral' };
        $priorityText = fn ($level) => \App\Models\QueueRequest::priorityClass($level).' · '.ucfirst($level);
    @endphp

    <x-ui.page-header title="Incoming Queue" description="Assign helpers to waiting help seekers. Figures refresh every 30 seconds." />

    @if(session('success'))
        <div class="queue-flash success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="queue-flash error">{{ session('error') }}</div>
    @endif

    <x-ui.card class="queue-card">
        <div class="queue-stats">
            <span>Waiting <strong id="statWaiting">{{ $stats['waiting'] }}</strong></span>
            <span>Assigned <strong id="statAssigned">{{ $stats['assigned'] }}</strong></span>
            <span>Oldest wait <strong data-metric="oldest_wait">{{ $stats['oldest_wait'] }}</strong></span>
            <span class="queue-note">Unmatched requests expire after 24 hours; history is retained.</span>
        </div>
    </x-ui.card>

    <x-ui.card class="queue-card" title="Service targets by priority class">
        <div class="queue-targets">
            @foreach(['P1'=>['Emergency','red'],'P2'=>['High','orange'],'P3'=>['Moderate','amber'],'P4'=>['Low','neutral']] as $class=>[$label,$tone])
                <span><x-ui.badge :tone="$tone">{{ $class }}</x-ui.badge> {{ $label }} · target ≤ {{ ['P1'=>1,'P2'=>3,'P3'=>10,'P4'=>20][$class] }} min</span>
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card class="queue-card" title="Waiting Seekers">
        <x-slot:actions><span class="queue-count" id="waitingHeading">{{ $queueItems->total() }} waiting</span></x-slot:actions>
        @php $waiting = $queueItems->where('request_status', 'waiting'); @endphp
        @if($waiting->isEmpty())
            <x-ui.empty title="No seekers waiting. Queue is clear!" />
        @else
            <x-ui.table :stack="true">
                <thead><tr><th>Priority</th><th>Seeker</th><th>Type</th><th>Waiting</th><th>Actions</th></tr></thead>
                <tbody>
                @foreach($waiting as $item)
                    <tr data-queue-row="{{ $item->id }}">
                        <td data-label="Priority"><x-ui.badge :tone="$priorityTone($item->priority_level)" title="Priority class {{ \App\Models\QueueRequest::priorityClass($item->priority_level) }}">{{ $priorityText($item->priority_level) }}</x-ui.badge></td>
                        <td data-label="Seeker">
                            <span class="queue-alias">{{ $item->seeker?->generated_alias ?? 'Anonymous' }}</span>
                            <small>{{ $item->concern_name }}</small>
                        </td>
                        <td data-label="Type">{{ ucfirst($item->preferred_session_type) }}</td>
                        <td data-label="Waiting"><x-ui.badge :tone="$item->wait_minutes >= 30 ? 'red' : ($item->wait_minutes >= 15 ? 'amber' : 'green')">{{ $item->wait_minutes }} min</x-ui.badge></td>
                        <td data-label="Actions" class="ui-actions">
                            <button type="button" class="btn-secondary" data-dialog-open="assign-{{ $item->id }}">Assign Helper</button>
                            <dialog class="workflow-dialog" id="assign-{{ $item->id }}" aria-labelledby="title-assign-{{ $item->id }}">
                                <header class="workflow-dialog__header"><div><h2 id="title-assign-{{ $item->id }}">Assign Helper</h2><p>Queue #{{ $item->id }} &middot; {{ $item->seeker?->generated_alias }}</p></div><button type="button" data-dialog-close aria-label="Close assignment">&times;</button></header>
                                <form method="POST" action="{{ route('moderator.queue.assign') }}" class="assign-form" data-queue-id="{{ $item->id }}">
                                    <div class="workflow-dialog__body">
                                        @csrf
                                        <input type="hidden" name="queue_id" value="{{ $item->id }}">
                                        <label class="sr-only" for="helper-{{ $item->id }}">Eligible helper for queue request {{ $item->id }}</label>
                                        <select id="helper-{{ $item->id }}" name="helper_id" class="assign-select" required>
                                            <option value="">Select helper…</option>
                                            @foreach($item->helper_choices['helpers'] as $choice)
                                                <option value="{{ $choice['id'] }}">{{ $choice['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <small data-helper-reason>{{ $item->helper_choices['reason'] }}</small>
                                        @include('partials.helper-unavailable', ['choices'=>$item->helper_choices])
                                    </div>
                                    <footer class="workflow-dialog__footer"><button type="button" class="btn-secondary" data-dialog-close>Cancel</button><button type="submit" class="btn-primary whitespace-nowrap" @disabled(empty($item->helper_choices['helpers']))>
                                        <x-ui-icon name="user-check" /> Assign
                                    </button></footer>
                                </form>
                            </dialog>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    <x-ui.card class="queue-card" title="Recently Matched">
        <x-slot:actions>
            <span class="queue-count" id="assignedHeading">{{ $recentlyMatched->count() }} assigned</span>
            <a class="text-green-700 font-semibold" href="{{ route('moderator.reports',['tab'=>'operations']) }}">View all</a>
        </x-slot:actions>
        @if($recentlyMatched->isEmpty())
            <x-ui.empty title="No recent matches" />
        @else
            <x-ui.table :stack="true">
                <thead><tr><th>Priority</th><th>Seeker</th><th>Type</th><th>Appointment</th><th>Actions</th></tr></thead>
                <tbody>
                @foreach($recentlyMatched as $item)
                    <tr data-queue-row="{{ $item->id }}">
                        <td data-label="Priority"><x-ui.badge :tone="$priorityTone($item->priority_level)" title="Priority class {{ \App\Models\QueueRequest::priorityClass($item->priority_level) }}">{{ $priorityText($item->priority_level) }}</x-ui.badge></td>
                        <td data-label="Seeker">
                            <span class="queue-alias">{{ $item->seeker?->generated_alias ?? 'Anonymous' }}</span>
                            <small>Matched {{ $item->matched_date?->diffForHumans() }} with <strong>{{ $item->assignedHelper?->full_name ?? '—' }}</strong></small>
                        </td>
                        <td data-label="Type">{{ ucfirst($item->preferred_session_type) }}</td>
                        <td data-label="Appointment"><x-ui.badge tone="green">{{ $item->scheduled_date ? $item->scheduled_date->copy()->setTimezone(config('app.schedule_timezone'))->format('M d, h:i A') : 'Awaiting acceptance' }}</x-ui.badge></td>
                        <td data-label="Actions" class="ui-actions">
                            <form method="POST" action="{{ route('moderator.queue.schedule') }}">
                                @csrf
                                <label class="sr-only" for="scheduled_at-{{ $item->id }}">Appointment time for queue request {{ $item->id }}</label>
                                <input type="datetime-local" id="scheduled_at-{{ $item->id }}" name="scheduled_at" class="assign-select"
                                       value="{{ $item->scheduled_date?->copy()->setTimezone(config('app.schedule_timezone'))->format('Y-m-d\TH:i') }}"
                                       title="Set the appointment time for this already-assigned session ({{ config('app.schedule_timezone') }}).">
                                <input type="hidden" name="queue_id" value="{{ $item->id }}">
                                <button type="submit" class="btn-secondary whitespace-nowrap">
                                    <x-ui-icon name="calendar" /> Schedule
                                </button>
                            </form>
                            <button type="button" class="btn-secondary" data-dialog-open="reassign-{{ $item->id }}">Reassign Helper</button>
                            <dialog class="workflow-dialog" id="reassign-{{ $item->id }}" aria-labelledby="title-reassign-{{ $item->id }}">
                                <header class="workflow-dialog__header"><div><h2 id="title-reassign-{{ $item->id }}">Reassign Helper</h2><p>Queue #{{ $item->id }} &middot; {{ $item->seeker?->generated_alias }}</p></div><button type="button" data-dialog-close aria-label="Close assignment">&times;</button></header>
                                <form method="POST" action="{{ route('moderator.queue.reassign') }}" class="reassign-form" data-queue-id="{{ $item->id }}">
                                    <div class="workflow-dialog__body">
                                        @csrf
                                        <input type="hidden" name="queue_id" value="{{ $item->id }}">
                                        <label class="sr-only" for="reassign-{{ $item->id }}">Replacement helper for queue request {{ $item->id }}</label>
                                        <select id="reassign-{{ $item->id }}" name="helper_id" class="assign-select">
                                            <option value="">Select helper…</option>
                                            @foreach($item->helper_choices['helpers'] as $choice)
                                                <option value="{{ $choice['id'] }}">{{ $choice['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <small data-helper-reason>{{ $item->helper_choices['reason'] }}</small>
                                        @include('partials.helper-unavailable', ['choices'=>$item->helper_choices])
                                    </div>
                                    <footer class="workflow-dialog__footer"><button type="button" class="btn-secondary" data-dialog-close>Cancel</button><button type="submit" class="btn-secondary whitespace-nowrap" @disabled(empty($item->helper_choices['helpers']))>
                                        <x-ui-icon name="refresh" /> Reassign
                                    </button></footer>
                                </form>
                            </dialog>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </x-ui.table>
        @endif
    </x-ui.card>

    <div class="mt-4">{{ $queueItems->links() }}</div>
@endsection
