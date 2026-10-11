@extends('layouts.app')
@section('title','COMPASS – Request Details')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/seeker-request.css') }}?v={{ filemtime(public_path('css/seeker-request.css')) }}">
@endpush
@section('content')
@php($inlineErrors = true)
@php($inlineNotices = ['success'])
<x-seeker-step title="Request Details">
    <div class="seeker-toolbar">
        <a class="seeker-button secondary" href="{{ route('seeker.requests') }}">Back to history</a>
        @if($activeRequest?->id === $session->id)
        <a class="seeker-button" href="{{ route('request.matching') }}">View active request</a>
        @elseif($session->session_status === 'completed' && !$session->evaluation)
        <a class="seeker-button" href="{{ route('session.evaluation',['session_id'=>$session->id]) }}">Give feedback</a>
        @endif
    </div>
    <section class="request-section">
        <h2>{{ $session->reference_number }}</h2>
        <p class="text-sm text-gray-500 mb-4">{{ \App\Services\SeekerRequestPresentation::explanation($session) }}</p>
        <dl class="request-details-grid">
            <div><dt>Status</dt><dd>{{ \App\Services\SeekerRequestPresentation::status($session) }}</dd></div>
            <div><dt>Concern</dt><dd>{{ $session->concern?->concern_name ?? 'Not recorded' }}</dd></div>
            @if($screening?->responses['custom_concern'] ?? null)<div><dt>Other concern</dt><dd>{{ $screening->responses['custom_concern'] }}</dd></div>@endif
            @if($description=$narrative?->responses['concern_description'] ?? $screening?->responses['description'] ?? null)<div><dt>Submitted description</dt><dd class="whitespace-pre-wrap">{{ $description }}</dd></div>@endif
            <div><dt>{{ $session->submitted_at ? 'Submitted' : 'Created' }} (PHT)</dt><dd>{{ ($session->submitted_at ?? $session->created_date ?? $session->created_at)?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }}</dd></div>
            <div><dt>Helper</dt><dd>{{ $session->helper?->public_alias ?? 'Not assigned' }}</dd></div>
            <div><dt>Communication</dt><dd>{{ ucfirst($session->session_type) }}</dd></div>
            <div><dt>Preferred language</dt><dd>{{ $session->preferred_language === 'English/Tagalog' ? 'Both (English and Tagalog)' : ($session->preferred_language ?? 'Not recorded for this request') }}</dd></div>
            @if($session->scheduled_start)<div><dt>Scheduled (PHT)</dt><dd>{{ $session->scheduled_start->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }}</dd></div>@endif
            @if($session->expired_at)<div><dt>Expired (PHT)</dt><dd>{{ $session->expired_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }}</dd></div>@endif
            @if($session->start_time)<div><dt>Session started (PHT)</dt><dd>{{ $session->start_time->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }}</dd></div>@endif
            @if($session->end_time)<div><dt>Session ended (PHT)</dt><dd>{{ $session->end_time->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }}</dd></div>@endif
            @if($queuePosition)<div><dt>Current queue position</dt><dd>#{{ $queuePosition }} · Position follows priority and request time; it may change.</dd></div>@endif
            <div><dt>Feedback</dt><dd>{{ $session->evaluation ? 'Submitted' : ($session->session_status === 'completed' ? 'Pending' : 'Available after a completed session') }}</dd></div>
        </dl>
    </section>
    <section class="request-section">
        <h2>Request timeline</h2>
        <ol class="request-timeline">
            @forelse($events as $event)
            <li>{{ $event->to_state === 'closed' ? \App\Services\SeekerRequestPresentation::status($session) : \App\Services\SeekerRequestPresentation::STATES[$event->to_state] }}
                <time>{{ \Illuminate\Support\Carbon::parse($event->occurred_at)->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</time>
            </li>
            @empty<li>No status history was recorded for this older request.</li>@endforelse
        </ol>
    </section>
    @if($session->evaluation)
    <section id="feedback" class="request-section">
        <h2>Your submitted feedback</h2>
        <p class="text-sm text-gray-500 mb-3">{{ $session->evaluation->submitted_at?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT · Feedback cannot be submitted twice.</p>
        <dl class="request-details-grid">
            @foreach(\App\Services\EvaluationInstrument::LABELS as $field=>$label)
                @if(isset($session->evaluation->answers[$field]))
                <div><dt>{{ $label }}</dt><dd>{{ \Illuminate\Support\Str::headline($session->evaluation->answers[$field]) }}</dd></div>
                @endif
            @endforeach
        </dl>
        @if($session->evaluation->comments)<p class="mt-4 whitespace-pre-wrap break-words">{{ $session->evaluation->comments }}</p>@endif
        @if(!$session->evaluation->answers)<p class="text-sm text-gray-500">This older feedback record does not contain the original questionnaire answers.</p>@endif
    </section>
    @endif
</x-seeker-step>
@endsection
