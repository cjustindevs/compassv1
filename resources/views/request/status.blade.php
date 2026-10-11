@extends('layouts.app')
@section('content')
<x-seeker-step title="Your support request">
<p class="font-semibold">Reference {{ $session->reference_number }}</p><a class="text-green-700 underline text-sm" href="{{ route('seeker.requests.show',$session) }}">View request details</a>
@switch($session->permitsEmergencySupport() ? 'emergency_escalated' : $session->workflow_state)
@case('emergency_escalated')
@include('request.partials.emergency-waiting')
@break
@case('adviser_review_required')<h2 class="font-semibold">Awaiting adviser review</h2><p>Your responses need review before we can continue. You can still access self-help and emergency resources.</p>@break
@case('closed')<h2 class="font-semibold">This request has ended</h2>@if($session->session_status === \App\Models\Session::STATUS_NO_SHOW)<p>The helper was not able to join, and this request has been closed. If you still need support, you can start a new request.</p>@elseif($session->session_status === \App\Models\Session::STATUS_CANCELLED)<p>This request was cancelled. If you still need support, you can start a new request.</p>@else<p>This conversation has been closed. If you still need support, you can start a new request.</p>@endif@break
@case('session_ready')<h2 class="font-semibold">Your helper is preparing</h2><p>Your request has been accepted. Chat opens when the helper starts the session.</p>@break
@case('session_active')<h2 class="font-semibold">Session status</h2>@if($session->helper_accepted_at && $session->helper)<a class="text-green-700 underline" href="{{ route('session.chat') }}">Open chat</a>@elseif(!$session->helper_accepted_at)<p>This older request has no recorded helper acceptance. Please contact your adviser for assistance with this request.</p>@else<p>The helper connection for this request cannot be confirmed. Please contact your support team for assistance.</p>@endif@break
@case('helper_pending_acceptance')<h2 class="font-semibold">Waiting for helper acceptance</h2><p>{{ $session->helper?->public_alias ?? 'Peer Helper' }} has been invited. Chat opens after acceptance when the helper starts the session.</p>@break
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

<details><summary class="cursor-pointer font-semibold">Status history</summary><ol class="space-y-2 mt-3">@foreach($events as $event)<li>{{ \App\Services\SeekerRequestPresentation::STATES[$event->to_state] ?? 'Request updated' }} &middot; {{ \Illuminate\Support\Carbon::parse($event->occurred_at)->timezone('Asia/Manila')->format('M d, Y g:i A') }}</li>@endforeach</ol></details>
</x-seeker-step>
@push('scripts')<script>setTimeout(()=>{if(document.visibilityState==='visible')location.reload();},15000);</script>@endpush
@endsection
