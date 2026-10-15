@extends('layouts.app')
@section('title', 'Emergency coordination - COMPASS')
@section('content')
@include('partials.management-ui-styles')
<div class="cm-page">
    <header class="cm-header"><div><h1>Emergency #{{ $alert->id }}</h1><p class="cm-muted">Shared emergency coordination notice for all Advisers.</p></div><a class="cm-button" href="{{ route('adviser.emergencies') }}">Back to emergencies</a></header>
    <section class="cm-card">
        <h2>{{ $alert->acknowledged_at ? 'Reviewer has acknowledged this case' : 'Awaiting reviewer acknowledgment' }}</h2>
        <p>Responsible reviewer: <strong>{{ $alert->adviser?->full_name ?? 'Awaiting assignment' }}</strong></p>
        <p>Assigned Helper: <strong>{{ $alert->session?->helper?->full_name ?? 'Awaiting match' }}</strong></p>
        <p>Status: <strong>{{ ucfirst($alert->status) }}</strong></p>
        <p>Reported {{ $alert->triggered_at?->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT.</p>
        <p class="cm-help">Coordinate with the responsible reviewer. Private screening, session documentation, and review actions remain with the assigned reviewer and Helper supervisor.</p>
    </section>
</div>
@endsection
