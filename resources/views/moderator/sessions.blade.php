@extends('layouts.app')

@section('title', 'COMPASS – Active Sessions')

@push('styles')
    <meta name="user-id" content="{{ auth()->id() }}">
    @vite(['resources/js/moderator-notifications.js'])
    <style>
        .sessions-card + .sessions-card { margin-top: 24px; }
        .session-ref { font-weight: 600; color: var(--compass-text, #1F2937); }
        .activity-item { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--compass-divider, #F3F4F6); }
        .activity-item:last-child { border-bottom: 0; }
        .activity-item .icon { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 14px; background: #F3F4F6; color: #374151; }
        .activity-item .icon.emergency { background: #FEF2F2; color: #B91C1C; }
        .activity-item .icon.assignment { background: #EFF6FF; color: #1D4ED8; }
        .activity-item .icon.session { background: #F0FDF4; color: #15803D; }
        .activity-item .message { font-size: 15px; font-weight: 500; color: var(--compass-text, #1F2937); }
        .activity-item .time { font-size: 13px; color: var(--compass-muted, #6B7280); }
        .page-action-link { display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const setStat = (key, value) => {
                document.querySelectorAll('[data-stat="' + key + '"] .ui-kpi-value').forEach((el) => { el.textContent = value; });
            };

            setInterval(function () {
                fetch('/moderator/sessions/stats', {
                    headers: { 'Accept': 'application/json' },
                })
                    .then((response) => response.json())
                    .then((data) => {
                        setStat('ongoing', data.ongoing);
                        setStat('chat', data.chat);
                        setStat('voice', data.voice);
                        setStat('emergency_flagged', data.emergency_flagged);

                        const sb = document.getElementById('sessionBadge');
                        if (sb) { sb.textContent = data.ongoing; sb.style.display = data.ongoing > 0 ? 'inline-block' : 'none'; }
                        document.getElementById('liveCount') && (document.getElementById('liveCount').textContent = data.ongoing);
                    })
                    .catch(() => {});
            }, 30000);
        });
    </script>
@endpush

@section('content')
    @php
        $riskTone = fn ($level) => match ($level) { 'emergency' => 'red', 'high' => 'orange', 'moderate' => 'amber', default => 'green' };
    @endphp

    <x-ui.page-header title="Active Sessions" description="Live monitoring of all ongoing peer support sessions">
        <x-slot:actions>
            <a href="{{ route('moderator.queue') }}" class="btn-secondary page-action-link"><x-ui-icon name="hourglass" /> Review Queue</a>
            <a href="{{ route('moderator.emergency') }}" class="btn-primary page-action-link"><x-ui-icon name="warning" /> Emergency</a>
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.kpi-grid>
        <x-ui.kpi label="Ongoing" :value="$stats['ongoing']" tone="blue" data-stat="ongoing" />
        <x-ui.kpi label="Chat" :value="$stats['chat']" data-stat="chat" />
        <x-ui.kpi label="Voice" :value="$stats['voice']" data-stat="voice" />
        <x-ui.kpi label="Emergency Flagged" :value="$stats['emergency_flagged']" tone="red" data-stat="emergency_flagged" />
        <x-ui.kpi label="Referrals Pending" :value="$stats['referrals_pending']" tone="amber" data-stat="referrals_pending" />
    </x-ui.kpi-grid>

    <x-ui.card class="sessions-card" title="Live Sessions">
        <div id="sessionList">
            @if($sessions->isEmpty())
                <x-ui.empty title="No active sessions right now" />
            @else
                <x-ui.table :stack="true">
                    <thead><tr><th>Session ID</th><th>Alias</th><th>Helper</th><th>Risk</th><th>Duration</th><th>Mode</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($sessions as $session)
                        <tr data-id="{{ $session->id }}">
                            <td data-label="Session ID"><span class="session-ref">{{ $session->reference_number }}</span></td>
                            <td data-label="Alias">{{ $session->seeker?->generated_alias ?? 'Anonymous' }}</td>
                            <td data-label="Helper">{{ $session->helper?->full_name ?? 'Unassigned' }}</td>
                            <td data-label="Risk"><x-ui.badge :tone="$riskTone($session->risk_level ?? 'low')">{{ ucfirst($session->risk_level ?? 'Low') }}</x-ui.badge></td>
                            <td data-label="Duration">{{ $session->elapsed_label }}</td>
                            <td data-label="Mode">{{ $session->mode_label }}</td>
                            <td data-label="Status"><x-ui.badge :tone="$session->session_status === 'active' ? 'blue' : 'amber'">{{ $session->session_status === 'active' ? 'Active' : 'Awaiting Start' }}</x-ui.badge></td>
                        </tr>
                    @endforeach
                    </tbody>
                </x-ui.table>
            @endif
        </div>
    </x-ui.card>

    <x-ui.card class="sessions-card" title="Live Activity">
        @forelse($recentActivity as $activity)
            <div class="activity-item">
                <div class="icon {{ $activity['type'] }}">
                    <x-ui-icon :value="$activity['icon']" />
                </div>
                <div>
                    <div class="message">{{ $activity['message'] }}</div>
                    <div class="time">{{ $activity['time'] }}</div>
                </div>
            </div>
        @empty
            <x-ui.empty title="No recent activity" />
        @endforelse
    </x-ui.card>
@endsection
