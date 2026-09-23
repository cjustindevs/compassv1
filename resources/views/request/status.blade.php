@extends('layouts.app')
@section('content')
<x-seeker-step title="Your support request">
<p class="font-semibold">Reference {{ $session->reference_number }}</p>
@switch($session->workflow_state)
@case('emergency_escalated')<h2 class="font-semibold">Emergency resources are available</h2><p>COMPASS cannot promise an immediate emergency response and does not replace emergency or professional services. An alert has been recorded for adviser review.</p><a class="text-green-700 underline" href="{{ route('emergency') }}">View emergency resources</a>@break
@case('adviser_review_required')<h2 class="font-semibold">Awaiting adviser review</h2><p>Your responses need review before we can continue. You can still access self-help and emergency resources.</p>@break
@case('session_ready')<h2 class="font-semibold">Your helper is preparing</h2><p>Your request has been accepted. Chat opens when the helper starts the session.</p>@break
@case('session_active')<h2 class="font-semibold">Your helper accepted</h2>@if($session->helper_accepted_at)<a class="text-green-700 underline" href="{{ route('session.chat') }}">Open chat</a>@else<p>This older request has no recorded helper acceptance. Please cancel it and start a new request, or contact your adviser.</p>@endif@break
@case('helper_pending_acceptance')<h2 class="font-semibold">Waiting for helper acceptance</h2><p>{{ $session->helper?->public_alias ?? 'Peer Helper' }} has been invited. Chat will open after acceptance.</p>@break
@default <h2 class="font-semibold">Waiting for an available helper</h2><p>{{ app(\App\Services\OperatingHoursService::class)->message() }}</p>
@endswitch
<p class="text-sm text-gray-500">This page refreshes every 15 seconds while you wait.</p>
<form method="POST" action="{{ route('request.cancel',$session) }}" data-confirm="Cancel this support request? Your history will be retained.">@csrf<x-secondary-button type="submit">Cancel request</x-secondary-button></form>
<details><summary class="cursor-pointer font-semibold">Status history</summary><ol class="space-y-2 mt-3">@foreach($events as $event)<li>{{ ucfirst(str_replace('_',' ',$event->to_state)) }} &middot; {{ \Illuminate\Support\Carbon::parse($event->occurred_at)->timezone('Asia/Manila')->format('M d, Y g:i A') }}</li>@endforeach</ol></details>
</x-seeker-step>
@push('scripts')<script>setTimeout(()=>{if(document.visibilityState==='visible')location.reload();},15000);</script>@endpush
@endsection
