@extends('layouts.app')
@section('content')
<x-seeker-step title="Your support request">
<p class="font-semibold">Reference {{ $session->reference_number }}</p>
@switch($session->permitsEmergencySupport() ? 'emergency_escalated' : $session->workflow_state)
@case('emergency_escalated')
<div class="my-4 rounded-xl border border-green-200 bg-green-50 p-4">
<h2 class="font-semibold">Temporary peer support</h2>
@if($session->session_status === 'active')
<p>Your Helper is connected while the Adviser coordinates further support.</p>
<a class="text-green-700 underline" href="{{ route('session.chat') }}">Open chat</a>
@elseif($session->helper_id)
<p>{{ $session->helper_accepted_at ? 'Your Helper has accepted and is preparing the chat.' : 'An eligible Helper has been invited. Waiting for acceptance.' }}</p>
@else
<p>No Helper connection is confirmed yet. Your emergency review remains open while the Moderator coordinates support.</p>
@endif
</div>
<h2 class="font-semibold">Emergency resources are available</h2><p>COMPASS cannot promise an immediate emergency response and does not replace emergency or professional services. An alert has been recorded for adviser review.</p><a class="text-green-700 underline" href="{{ route('emergency') }}">View emergency resources</a>@break
@case('adviser_review_required')<h2 class="font-semibold">Awaiting adviser review</h2><p>Your responses need review before we can continue. You can still access self-help and emergency resources.</p>@break
@case('closed')<h2 class="font-semibold">This request has ended</h2>@if($session->session_status === \App\Models\Session::STATUS_NO_SHOW)<p>The helper was not able to join, and this request has been closed. If you still need support, you can start a new request.</p>@elseif($session->session_status === \App\Models\Session::STATUS_CANCELLED)<p>This request was cancelled. If you still need support, you can start a new request.</p>@else<p>This conversation has been closed. If you still need support, you can start a new request.</p>@endif@break
@case('session_ready')<h2 class="font-semibold">Your helper is preparing</h2><p>Your request has been accepted. Chat opens when the helper starts the session.</p>@break
@case('session_active')<h2 class="font-semibold">Your helper accepted</h2>@if($session->helper_accepted_at)<a class="text-green-700 underline" href="{{ route('session.chat') }}">Open chat</a>@else<p>This older request has no recorded helper acceptance. Please contact your adviser for assistance with this request.</p>@endif@break
@case('helper_pending_acceptance')<h2 class="font-semibold">Waiting for helper acceptance</h2><p>{{ $session->helper?->public_alias ?? 'Peer Helper' }} has been invited. Chat will open after acceptance.</p>@break
@default
@if(in_array($session->workflow_state, ['screening_required', 'concern_required', 'session_preferences_required', 'ready_for_submission'], true))
<h2 class="font-semibold">Your request is not submitted yet</h2><p>Finish the remaining steps to be matched with a trained peer helper.</p><a class="text-green-700 underline" href="{{ route('request.matching') }}">Continue setting up your request</a>
@elseif($session->workflow_state === 'evaluation_pending')
<h2 class="font-semibold">Your conversation has ended</h2><p>Thank you for using COMPASS. Your feedback helps us keep improving.</p>
@else
<h2 class="font-semibold">Waiting for an available helper</h2><p>{{ app(\App\Services\OperatingHoursService::class)->message() }}</p>
@endif
@endswitch
<p class="text-sm text-gray-500">This page refreshes every 15 seconds while you wait.</p>

<details><summary class="cursor-pointer font-semibold">Status history</summary><ol class="space-y-2 mt-3">@foreach($events as $event)<li>{{ ucfirst(str_replace('_',' ',$event->to_state)) }} &middot; {{ \Illuminate\Support\Carbon::parse($event->occurred_at)->timezone('Asia/Manila')->format('M d, Y g:i A') }}</li>@endforeach</ol></details>
</x-seeker-step>
@push('scripts')<script>setTimeout(()=>{if(document.visibilityState==='visible')location.reload();},15000);</script>@endpush
@endsection
