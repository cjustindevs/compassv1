@extends('layouts.app')
@section('title', 'Session documentation - COMPASS')
@section('content')
<div class="adviser-page-content">
    <x-ui.page-header title="Session documentation" description="Review the Helper's submitted summary, reflection, and correction history." :back-href="route('adviser.dashboard')" back-label="Back to Dashboard" />
    <x-ui.card>
        <dl class="ui-summary">
            <div><dt>Session</dt><dd>{{ $session->reference_number }}</dd></div>
            <div><dt>Helper</dt><dd>{{ $session->helper?->full_name ?? 'Unassigned' }}</dd></div>
            <div><dt>Status</dt><dd><x-ui.badge tone="green">{{ ucfirst($session->session_status) }}</x-ui.badge></dd></div>
            <div><dt>Documentation</dt><dd>{{ ucfirst($session->documentation_status ?? 'Pending') }}</dd></div>
        </dl>
    </x-ui.card>
    <div class="ui-card-grid ui-card-grid--wide">
        @foreach(['Session summary' => 'session_summary', 'Helper reflection' => 'personal_reflection', 'Session result' => 'session_result', 'Follow-up plan' => 'follow_up_plan'] as $label => $field)
            <x-ui.card :title="$label">
                <p class="ui-prose">{{ $session->report?->$field ?: 'Not submitted yet.' }}</p>
            </x-ui.card>
        @endforeach
    </div>
    @if($session->report && in_array($session->session_status, ['completed', 'evaluated']))
        <p class="ui-prose-actions"><a class="btn-primary" href="{{ route('adviser.evaluate', $session->report->id) }}">Review competency evaluation</a></p>
    @endif
    <x-ui.card title="Previous documentation versions">
        @forelse($revisions as $revision)
            <details class="ui-prose-details">
                <summary>{{ $revision->created_at }} | {{ $revision->reason }}</summary>
                @php($snapshot = json_decode($revision->snapshot, true) ?? [])
                <p class="ui-prose">{{ $snapshot['session_summary'] ?? 'No summary in this version.' }}</p>
                <p class="ui-prose">{{ $snapshot['personal_reflection'] ?? 'No reflection in this version.' }}</p>
            </details>
        @empty
            <p class="ui-card-sub">No corrections recorded.</p>
        @endforelse
    </x-ui.card>
    <p class="ui-card-sub">Conversation access is separate and requires a documented purpose and valid session consent. <a class="ui-back-link" href="{{ route('adviser.transcripts') }}">Review transcript access</a></p>
</div>
@endsection
