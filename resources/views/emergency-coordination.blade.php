@extends('layouts.app')
@section('title', 'Emergency coordination - COMPASS')
@section('content')
@include('partials.management-ui-styles')
@php($role = auth()->user()->role)
<div class="cm-page">
    <header class="cm-header"><h1>Emergency coordination #{{ $case->id }}</h1><a class="cm-button" href="{{ $role === 'moderator' ? route('moderator.emergency') : route('adviser.emergencies') }}">Back to emergencies</a></header>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div role="alert">{{ $errors->first() }}</div>@endif
    <section class="cm-card">
        <h2>Operational status</h2>
        <p>Seeker: {{ $case->seeker_alias ?? 'Anonymous' }}</p>
        <p>Status: {{ app(\App\Services\ModeratorEmergencyCases::class)->statusLabel($case) }}</p>
        <p>Helper: {{ trim($case->helper_first_name.' '.$case->helper_last_name) ?: 'Unassigned' }}</p>
        @if($case->helper_id)<p>Support: {{ $case->support_status === 'helper_assigned' && !$case->helper_accepted_at ? 'Awaiting Helper acceptance' : ucwords(str_replace('_', ' ', $case->support_status ?? 'Status unavailable')) }}</p>@endif
        <p>Adviser: {{ trim($case->adviser_first_name.' '.$case->adviser_last_name) ?: 'Awaiting coordination' }}</p>
        @if($terminal)<p>This emergency has concluded. Assignment is no longer available.</p>@if($case->resolved_at)<p>Resolved {{ \Illuminate\Support\Carbon::parse($case->resolved_at)->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT. Clinical resolution notes remain restricted to authorized reviewers.</p>@endif
        @elseif(!$current)<p>This record has been superseded by a newer emergency report. Open the Active tab for the current incident.</p>
        @elseif($case->helper_id)<p>Helper assignment is recorded. Acceptance and support progress remain visible in the operational session list.</p>
        @elseif($canAssign && $helpers->isNotEmpty())
            <form method="POST" action="{{ route($role.'.emergency.assign', [$case->source, $case->id]) }}">
                @csrf
                <div class="cm-field"><label for="emergencyHelper">Eligible Helper</label>
                <select name="helper_id" id="emergencyHelper" required><option value="">Select a Helper</option>
                    @foreach($helpers as $helper)<option value="{{ $helper->id }}">{{ $helper->full_name }}</option>@endforeach
                </select></div>
                <button class="cm-button cm-primary" type="submit">Assign Helper</button>
            </form>
        @elseif($canAssign)
            <p>No eligible Helper is available for assignment. Adviser coordination does not require waiting 15 minutes.</p>
            <form method="POST" action="{{ route($role.'.emergency.escalation', [$case->source, $case->id]) }}">@csrf<button class="cm-button cm-primary" type="submit">Request Adviser Escalation</button></form>
        @else<p>No queued assignment is actionable for this record. Review the current request state with the responsible Adviser.</p>
        @endif
        @if($role === 'adviser' && $case->source === 'emergency_alerts')<p><a class="cm-button" href="{{ route('adviser.emergencies.show', $case->id) }}">Open authorized Adviser review and Professional handoff</a></p>@endif
    </section>
</div>
@endsection
