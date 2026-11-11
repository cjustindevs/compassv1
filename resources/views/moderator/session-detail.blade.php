@extends('layouts.app')

@section('title', 'COMPASS – Monitoring '.$session->reference_number)

@push('styles')
    <meta name="user-id" content="{{ auth()->id() }}">
    @vite(['resources/js/moderator-notifications.js'])
    <style>
        .detail-card { margin-bottom: 24px; }
        .detail-flash { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
        .detail-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin: 0; }
        .detail-summary dt { font-size: 13px; color: var(--compass-muted, #6B7280); margin-bottom: 4px; }
        .detail-summary dd { margin: 0; font-size: 15px; font-weight: 600; color: var(--compass-text, #1F2937); overflow-wrap: anywhere; }
        .detail-columns { display: grid; grid-template-columns: minmax(0, 1fr); gap: 24px; align-items: start; }
        .detail-columns > .ui-card, .detail-side > .ui-card { margin-bottom: 0; }
        .detail-side { display: grid; gap: 24px; }
        @media (min-width: 1024px) { .detail-columns { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); } }
        .detail-info { display: grid; gap: 12px; margin: 0; font-size: 15px; }
        .detail-info > div { display: flex; justify-content: space-between; gap: 12px; }
        .detail-info dt { color: var(--compass-muted, #6B7280); }
        .detail-info dd { margin: 0; font-weight: 600; color: var(--compass-text, #1F2937); text-align: right; overflow-wrap: anywhere; }
        .msg-list { display: grid; gap: 12px; max-height: 384px; overflow-y: auto; }
        .msg-row { display: flex; }
        .msg-row.helper { justify-content: flex-end; }
        .msg-bubble { padding: 10px 14px; border-radius: 14px; font-size: 15px; max-width: 80%; line-height: 1.5; overflow-wrap: anywhere; }
        .msg-bubble.seeker { background: #EAF8F0; color: #065F46; }
        .msg-bubble.helper { background: #F3F4F6; color: #374151; }
        .msg-bubble .msg-who { display: block; font-size: 12px; margin-bottom: 2px; color: var(--compass-muted, #6B7280); }
        .detail-note { font-size: 13px; color: var(--compass-muted, #6B7280); margin: 0 0 12px; }
        .incident + .incident { margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--compass-divider, #F3F4F6); }
        .incident p { margin: 4px 0 0; font-size: 14px; color: var(--compass-muted, #6B7280); }
        .incident .incident-title { font-size: 15px; font-weight: 600; color: var(--compass-text, #1F2937); }
        .score-value { font-size: 28px; font-weight: 800; color: var(--compass-text, #1F2937); margin: 0; }
    </style>
@endpush

@section('content')
    @php
        $riskTone = fn ($level) => match ($level) { 'emergency' => 'red', 'high' => 'orange', 'moderate' => 'amber', default => 'green' };
        $risk = $session->risk_level ?? 'low';
        $started = $session->start_time ?? $session->created_date;
    @endphp

    <x-ui.page-header :title="'Monitoring '.$session->reference_number" description="Live session monitor"
        :back-href="route('moderator.sessions')" back-label="Back to Active Sessions" />

    @if(session('success'))
        <div class="detail-flash">{{ session('success') }}</div>
    @endif

    <x-ui.card class="detail-card" :title="$session->seeker?->generated_alias ?? 'Anonymous'" :subtitle="$session->concern?->concern_name ?? 'General Concern'">
        <x-slot:actions>
            <x-ui.badge :tone="$riskTone($risk)">{{ ucfirst($risk) }} risk</x-ui.badge>
            <x-ui.badge :tone="$session->session_status === 'active' ? 'blue' : ($session->session_status === 'completed' || $session->session_status === 'evaluated' ? 'green' : 'amber')">{{ $session->status_label }}</x-ui.badge>
        </x-slot:actions>
        <dl class="detail-summary">
            <div><dt>Status</dt><dd>{{ $session->status_label }}</dd></div>
            <div><dt>Risk</dt><dd>{{ ucfirst($risk) }}</dd></div>
            <div><dt>Helper</dt><dd>{{ $session->helper?->full_name ?? 'Unassigned' }}</dd></div>
            <div><dt>Seeker alias</dt><dd>{{ $session->seeker?->generated_alias ?? 'Anonymous' }}</dd></div>
            <div><dt>Mode</dt><dd>{{ $session->mode_label }}</dd></div>
            <div><dt>Elapsed</dt><dd>{{ $session->elapsed_label }}</dd></div>
            <div><dt>Started</dt><dd>{{ $started?->format('M d, h:i A') ?? '—' }}</dd></div>
            <div><dt>Ended</dt><dd>{{ $session->end_time?->format('M d, h:i A') ?? '—' }}</dd></div>
        </dl>
    </x-ui.card>

    <div class="detail-columns">
        <x-ui.card title="Recent Messages" :subtitle="'('.$session->messages->count().' shown)'">
            <p class="detail-note">Confidential support conversation. Moderator views are logged.</p>
            <div class="msg-list">
                @forelse($session->messages as $message)
                    <div class="msg-row {{ $message->is_helper ? 'helper' : 'seeker' }}">
                        <div class="msg-bubble {{ $message->is_helper ? 'helper' : 'seeker' }}">
                            <span class="msg-who">{{ $message->is_helper ? ($session->helper?->full_name ?? 'Helper') : ($session->seeker?->generated_alias ?? 'Seeker') }}</span>
                            {{ $message->message_text }}
                        </div>
                    </div>
                @empty
                    <x-ui.empty title="No messages yet" />
                @endforelse
            </div>
        </x-ui.card>

        <div class="detail-side">
            <x-ui.card title="Session Info">
                <dl class="detail-info">
                    <div><dt>Session ID</dt><dd>{{ $session->reference_number }}</dd></div>
                    <div><dt>Type</dt><dd class="capitalize">{{ $session->session_type }}</dd></div>
                    <div><dt>Voice Consent</dt><dd>{{ $session->voice_recording_consent ? 'Granted' : 'Not required' }}</dd></div>
                    <div><dt>Scheduled</dt><dd>{{ $session->scheduled_start?->format('M d, h:i A') ?? '—' }}</dd></div>
                    <div><dt>Duration</dt><dd>{{ $session->duration ? $session->duration . ' min' : '—' }}</dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card title="Helper">
                <x-ui.list-row :initials="strtoupper(substr($session->helper?->first_name ?? '?', 0, 1).substr($session->helper?->last_name ?? '?', 0, 1))"
                    :title="$session->helper?->full_name ?? 'Unassigned'"
                    :subtitle="'Competency: '.($session->helper?->latestCompetency?->competency_level ?? '—')">
                    @if($session->helper)
                        <x-slot:badge><x-ui.badge :tone="$session->helper->status === 'available' ? 'green' : 'amber'">{{ ucfirst($session->helper->status) }}</x-ui.badge></x-slot:badge>
                    @endif
                </x-ui.list-row>
            </x-ui.card>

            @if($session->incidents->isNotEmpty())
                <x-ui.card title="Incidents on this session">
                    @foreach($session->incidents as $incident)
                        <div class="incident">
                            <x-ui.badge :tone="$riskTone($incident->risk_level)">{{ ucfirst($incident->risk_level) }}</x-ui.badge>
                            <span class="detail-note">{{ $incident->created_at?->diffForHumans() }}</span>
                            <p class="incident-title">{{ $incident->incident_category }}</p>
                            <p>{{ $incident->description }}</p>
                            <p>Status: <strong class="capitalize">{{ str_replace('_', ' ', $incident->status) }}</strong></p>
                        </div>
                    @endforeach
                    <p style="margin-top:12px"><a href="{{ route('moderator.emergency') }}" class="text-red-700 font-semibold"><x-ui-icon name="warning" /> Go to Emergency Workspace</a></p>
                </x-ui.card>
            @endif

            @if($session->evaluation)
                <x-ui.card title="Seeker rating" subtitle="Scale 1–5">
                    <p class="score-value">{{ \App\Support\ScoreFormat::number(\App\Services\AdviserAnalytics::rating($session->evaluation->overall_score)) }}</p>
                    @if(!empty($session->evaluation->answers['helpfulness_score']))<p class="detail-note" style="margin-top:8px">Helpfulness: {{ $session->evaluation->answers['helpfulness_score'] }}</p>@endif
                    <p class="detail-note" style="margin-top:8px">{{ $session->evaluation->comments ?: 'No comments' }}</p>
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection
