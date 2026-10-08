@extends('layouts.app')
@section('title', 'Authorized transcript - COMPASS')
@section('content')
@php
    $helperName = $session->helper?->full_name ?: 'Helper';
    $sessionDate = ($session->created_date ?? $session->created_at)?->copy()->timezone('Asia/Manila');
    $lastDay = null;
@endphp
<div class="adviser-page-content av-page at-page">
    <header><div><a class="av-link at-back" href="{{ route('adviser.transcripts') }}">Back to transcript access</a><h1>{{ $session->reference_number }} transcript</h1><p class="av-muted">{{ $helperName }} / {{ $sessionDate?->format('M d, Y g:i A') ?? 'Date not recorded' }} PHT</p></div><span class="av-badge">Authorized review</span></header>
    <div class="at-access-note"><strong>Temporary, audited access</strong><p>This authorization lasts ten minutes and requires current supervision. Dates and message times below use Philippine Time.</p></div>
    <section class="av-panel at-reader">
        <header class="av-heading"><div><h2>Session conversation</h2><p class="av-muted">{{ count($transcript['messages']) }} {{ count($transcript['messages'])===1 ? 'message' : 'messages' }} / In recorded order</p></div><span class="av-muted">{{ $transcript['seeker_alias'] }} with {{ $helperName }}</span></header>
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
            @empty<li class="av-empty">No messages were recorded for this session.</li>@endforelse
            </ol>
        </div>
        <footer class="at-reader-footer">
            <div><h2>{{ $session->transcript_verified ? 'Transcript already verified' : 'Finish transcript review' }}</h2><p class="av-muted">Verification applies to this transcript. Competency evaluations remain separate.</p></div>
            @if(!$session->transcript_verified)<form method="POST" action="{{ route('adviser.transcript.verify', $session->id) }}">@csrf<button type="submit" class="av-button av-button-primary">Verify transcript</button></form>@else<a class="av-button" href="{{ route('adviser.transcripts') }}">Back to transcript access</a>@endif
        </footer>
    </section>
</div>
@endsection
