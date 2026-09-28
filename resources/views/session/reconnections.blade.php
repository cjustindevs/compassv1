@extends('layouts.app')
@section('content')
@include('partials.referral-ui-styles')
<div class="referral-ui"><h1>Connection interruptions</h1><p class="ru-muted">Review reconnection progress and replacement requests. No conversation content is shown here.</p>
@if(session('success'))<p role="status">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
@forelse($incidents as $incident)
<section class="ru-card"><h2>Session #{{ $incident->session_id }}</h2><p>{{ ucfirst($incident->status) }} ? Detected {{ $incident->detected_at->diffForHumans() }}</p>
@if(auth()->user()->role==='moderator')
<form method="POST" action="{{ route('reconnections.offer',$incident) }}">@csrf<button type="submit" @disabled($incident->status!=='requested' || !$incident->session->isActive())>Find and offer eligible Helper</button></form>
<p class="ru-muted">Replacement requires the Seeker's request after the reconnection window. Emergency sessions require Adviser coordination.</p>
@else
<p>Accept a continuation of an interrupted chat. Earlier private messages remain with the original session. The original 90-minute deadline remains in effect.</p>
<form method="POST" action="{{ route('reconnections.accept',$incident) }}" class="ru-actions">@csrf<button name="accept" value="1">Accept replacement</button><button name="accept" value="0">Decline</button></form>
@endif</section>
@empty<div class="ru-card">No connection interruptions require your attention.</div>@endforelse
{{ $incidents->links() }}</div>
@endsection
