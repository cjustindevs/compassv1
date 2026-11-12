@extends('layouts.app')
@section('title', 'Authorized transcript - COMPASS')
@section('content')
@php
    $helperName = $session->helper?->full_name ?: 'Helper';
    $sessionDate = ($session->created_date ?? $session->created_at)?->copy()->timezone('Asia/Manila');
    $lastDay = null;
@endphp
<div class="adviser-page-content at-page">
    <x-ui.page-header :title="$session->reference_number.' transcript'" description="Temporary, audited access: this authorization lasts ten minutes and requires current supervision. Dates and message times below use Philippine Time." :back-href="route('adviser.transcripts')" back-label="Back to Transcripts" />
    <x-ui.card>
        <dl class="ui-summary">
            <div><dt>Reference</dt><dd>{{ $session->reference_number }}</dd></div>
            <div><dt>Helper</dt><dd>{{ $helperName }}</dd></div>
            <div><dt>Seeker</dt><dd>{{ $transcript['seeker_alias'] }}</dd></div>
            <div><dt>Session date</dt><dd>{{ $sessionDate?->format('M d, Y g:i A') ?? 'Date not recorded' }} PHT</dd></div>
            <div><dt>Verified</dt><dd><x-ui.badge :tone="$session->transcript_verified ? 'green' : 'amber'">{{ $session->transcript_verified ? 'Verified' : 'Not yet verified' }}</x-ui.badge></dd></div>
        </dl>
    </x-ui.card>
    <x-ui.card class="at-reader" title="Session conversation" :subtitle="count($transcript['messages']).' '.(count($transcript['messages'])===1 ? 'message' : 'messages').' / In recorded order'">
        <div class="at-conversation" role="region" aria-label="Authorized session transcript" tabindex="0">
            <ol class="at-messages">
            @forelse($transcript['messages'] as $message)
                @php
                    $time = !empty($message['timestamp']) ? \Illuminate\Support\Carbon::parse($message['timestamp'], config('app.timezone','UTC'))->timezone('Asia/Manila') : null;
                    $day = $time?->format('Y-m-d');
                    $isHelper = $message['sender_type']==='helper';
                @endphp
                @if($day !== $lastDay && $time)<li class="at-day">{{ $time->format('M d, Y') }}</li>@endif
                @php
                    $lastDay = $day;
                @endphp
                <li class="at-message {{ $isHelper ? 'at-message-helper' : '' }}">
                    <header><strong>{{ $isHelper ? $helperName : $transcript['seeker_alias'] }}</strong><span>{{ $isHelper ? 'Helper' : 'Seeker' }}</span>@if($time)<time datetime="{{ $time->toIso8601String() }}" title="{{ $time->format('M d, Y g:i A') }} PHT">{{ $time->format('g:i A') }}</time>@else<span>Time not recorded</span>@endif</header>
                    <p>{{ $message['message'] }}</p>
                </li>
            @empty<li class="ui-card-sub">No messages were recorded for this session.</li>@endforelse
            </ol>
        </div>
        <footer class="at-reader-footer">
            <div><h2>{{ $session->transcript_verified ? 'Transcript already verified' : 'Finish transcript review' }}</h2><p class="ui-card-sub">Verification applies to this transcript. Competency evaluations remain separate.</p></div>
            @if(!$session->transcript_verified)<form method="POST" action="{{ route('adviser.transcript.verify', $session->id) }}">@csrf<button type="submit" class="btn-primary">Verify transcript</button></form>@else<a class="btn-secondary" href="{{ route('adviser.transcripts') }}">Back to Transcripts</a>@endif
        </footer>
    </x-ui.card>
</div>
@endsection
