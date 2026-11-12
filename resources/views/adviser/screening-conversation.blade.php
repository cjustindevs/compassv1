@extends('layouts.app')
@section('title', 'Session conversation - COMPASS')
@section('content')
<div class="adviser-page-content">
    <x-ui.page-header title="Session conversation"
        description="Read-only view for this screening review. Access is audited and uses aliases only."
        :back-href="route('adviser.screenings')" back-label="Back to Screening Reviews">
        <x-slot:actions><x-ui.badge tone="green">{{ $session->status_label }}</x-ui.badge></x-slot:actions>
    </x-ui.page-header>

    <x-ui.card title="Summary">
        <dl class="ui-summary">
            <div><dt>Request</dt><dd>{{ $session->reference_number }}</dd></div>
            <div><dt>Seeker</dt><dd>{{ $session->seeker?->generated_alias ?? 'Seeker' }}</dd></div>
            <div><dt>Helper</dt><dd>{{ $session->helper?->full_name ?? 'Unassigned' }}</dd></div>
            <div><dt>Risk</dt><dd>{{ ucfirst($session->risk_level ?? 'unclassified') }}</dd></div>
            <div><dt>Submitted</dt><dd>{{ ($session->created_date ?? $session->created_at)?->timezone('Asia/Manila')->format('M d, Y g:i A') }}</dd></div>
        </dl>
    </x-ui.card>

    <x-ui.card title="Messages" subtitle="Conversation content is shown as recorded in the session; it does not use voice transcription.">
        <div class="ui-convo">
        @forelse($messages as $message)
            @php($isSeeker = $message->sender === 'seeker')
            <div class="ui-convo-row {{ $isSeeker ? '' : 'is-helper' }}">
                <span class="ui-convo-avatar" aria-hidden="true">
                    {{ $isSeeker ? mb_substr($session->seeker?->generated_alias ?? 'S', 0, 1) : mb_substr($session->helper?->full_name ?? 'H', 0, 1) }}
                </span>
                <div class="ui-convo-body">
                    <div class="ui-convo-meta">
                        <strong>{{ $isSeeker ? ($session->seeker?->generated_alias ?? 'Seeker') : ($session->helper?->full_name ?? 'Helper') }}</strong>
                        <span>{{ ($message->sent_datetime ?? $message->created_at)?->timezone('Asia/Manila')->format('M d, Y \a\t g:i A') }}</span>
                    </div>
                    <p>{{ $message->message_text }}</p>
                </div>
            </div>
        @empty
            <x-ui.empty title="No chat messages recorded for this session." />
        @endforelse
        </div>
    </x-ui.card>
</div>
@endsection
