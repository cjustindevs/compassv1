@extends('layouts.app')

@section('title', 'COMPASS – Moderator Dashboard')

@push('styles')
    <meta name="user-id" content="{{ auth()->id() }}">
    @vite(['resources/js/moderator-notifications.js'])
    <style>
        .activity-item { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--compass-border, #E5E7EB); text-decoration: none; }
        .activity-item:last-child { border-bottom: none; }
        .activity-item .content { flex: 1; min-width: 0; }
        .activity-item .content .message { font-weight: 600; font-size: 15px; color: var(--compass-text, #1F2937); }
        .activity-item .content .detail { font-size: 14px; color: var(--compass-muted, #6B7280); overflow-wrap: anywhere; }
        .activity-item .content .time { font-size: 13px; color: var(--compass-muted, #6B7280); }
        .ui-empty-note { text-align: center; padding: 32px 0; font-size: 15px; color: var(--compass-muted, #6B7280); }
        .dashboard-columns { display: grid; grid-template-columns: 1fr; gap: 0 24px; align-items: start; }
        @media (min-width: 1024px) { .dashboard-columns { grid-template-columns: 1fr 1fr; } }
        .dashboard-flash { border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
        .dashboard-flash.success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; }
        .dashboard-flash.error { background: #FEF2F2; color: #B91C1C; border: 1px solid #FECACA; }
        #dashboard-analytics { margin-bottom: 24px; }
        #dashboard-analytics .analytics-toolbar{justify-content:flex-end}
        #dashboard-analytics .analytics-period-form{justify-content:flex-end;margin-inline-start:auto;max-width:100%}
    </style>
@endpush

@section('content')
    <x-ui.page-header title="Moderator Dashboard" description="Overview of the peer support program">
        <x-slot:actions>
            <a href="{{ route('moderator.emergency') }}" class="btn-outline-danger">
                <x-ui-icon name="warning" /> Emergency
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('success'))
        <div class="dashboard-flash success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="dashboard-flash error">{{ session('error') }}</div>
    @endif

    <x-ui.attention :items="[
        ['count' => $stats['emergency_count'], 'label' => $stats['emergency_count'] === 1 ? 'Emergency open' : 'Emergencies open', 'href' => route('moderator.emergency'), 'tone' => 'red'],
        ['count' => $stats['queue_waiting'], 'label' => 'Waiting in queue', 'href' => route('moderator.queue'), 'tone' => 'amber'],
    ]" />

    <x-ui.kpi-grid>
        <x-ui.kpi label="Active sessions" :value="$stats['active_sessions']" tone="blue" :href="route('moderator.sessions')" />
        <x-ui.kpi label="Waiting in queue" :value="$stats['queue_waiting']" tone="amber" :note="$stats['unserved'].' waiting 30+ min'" :href="route('moderator.queue')" />
        <x-ui.kpi label="Open emergencies" :value="$stats['emergency_count']" tone="red" :href="route('moderator.emergency')" />
        <x-ui.kpi label="Helpers available" :value="$stats['available_helpers']" tone="green" :href="route('moderator.manage')" />
    </x-ui.kpi-grid>

    <section id="dashboard-analytics" aria-label="Operations dashboard summaries">@include('moderator.partials.dashboard-analytics')</section>

    <div class="dashboard-columns">
        <x-ui.card title="Recent Activity">
            <x-slot:actions>
                <a href="{{ route('moderator.reports',['tab'=>'activity']) }}" class="text-sm text-green-700">View All</a>
            </x-slot:actions>
            @forelse($recentActivity as $activity)
                <a href="{{ $activity['link'] }}" class="activity-item no-underline">
                    <div class="content">
                        <div class="message">{{ $activity['title'] }}</div>
                        <div class="detail">{{ $activity['message'] }}</div>
                        <div class="time">{{ $activity['time'] }}</div>
                    </div>
                </a>
            @empty
                <p class="ui-empty-note">No recent activity</p>
            @endforelse
        </x-ui.card>

        <x-ui.card title="Quick Actions">
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
        </x-ui.card>
    </div>

    <meta id="moderatorEmergencyConfig" content="{{ json_encode(['kind' => 'dashboard', 'stats' => route('moderator.dashboard.stats', request()->only('period','from','to')), 'signature' => $overviewSignature]) }}">
@endsection

@push('scripts')
    <script src="{{ asset('js/moderator-emergency.js') }}?v={{ filemtime(public_path('js/moderator-emergency.js')) }}" defer></script>
@endpush
