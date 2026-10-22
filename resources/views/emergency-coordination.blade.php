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
        <h2>Case overview</h2>
        <p>Seeker: {{ $case->seeker_alias ?? 'Anonymous' }}</p>
        <p>Status: {{ app(\App\Services\ModeratorEmergencyCases::class)->statusLabel($case) }}</p>
        <p>Primary Adviser: {{ trim(($case->primary_adviser_first_name ?? '').' '.($case->primary_adviser_last_name ?? '')) ?: 'Unassigned' }}</p><p>Helper: {{ trim($case->helper_first_name.' '.$case->helper_last_name) ?: 'Unassigned' }}</p>
        @if($case->helper_id)<p>Support: {{ $case->support_status === 'helper_assigned' && !$case->helper_accepted_at ? 'Awaiting Helper acceptance' : ucwords(str_replace('_', ' ', $case->support_status ?? 'Status unavailable')) }}</p>@endif
        <p>Responsible emergency coordinator: <strong>{{ trim($case->adviser_first_name.' '.$case->adviser_last_name) ?: 'Awaiting coordination' }}</strong></p>
        @if($role === 'adviser' && (int)auth()->user()->adviser?->id !== (int)$case->adviser_id && (int)auth()->user()->adviser?->id !== (int)$case->primary_adviser_id)<p class="cm-muted">Shared coordination view. This case is assigned to the coordinator shown above.</p>@endif
        @if($terminal)<p>This emergency has concluded. Assignment is no longer available.</p>@if($case->resolved_at)<p>Resolved {{ \Illuminate\Support\Carbon::parse($case->resolved_at)->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT. Clinical resolution notes remain restricted to authorized reviewers.</p>@endif
        @elseif(!$current)<p>This record has been superseded by a newer emergency report. Open the Active tab for the current incident.</p>
        @elseif($case->helper_id)<p>Helper assignment is recorded. Acceptance and support progress remain visible in the operational session list.</p>
        @elseif($canAssign)
            <p>Assign an eligible Helper for temporary peer support. Emergency coordination continues separately.</p>
            <button type="button" class="cm-button cm-primary" data-dialog-open="emergency-assignment">Assign Helper</button>
            <dialog id="emergency-assignment" class="workflow-dialog" aria-labelledby="assignment-title">
            <header class="workflow-dialog__header"><div><small>Emergency #{{ $case->id }}</small><h2 id="assignment-title">Assign Helper</h2><p>Readiness, duty, capacity and risk suitability are rechecked when you confirm.</p></div><button type="button" data-dialog-close aria-label="Close assignment">&times;</button></header>
            <form method="POST" data-helper-options="{{ route($role.'.emergency.helpers', [$case->source, $case->id]) }}" action="{{ route($role.'.emergency.assign', [$case->source, $case->id]) }}">
                @csrf
                <div class="workflow-dialog__body">
                <label>Find a Helper<input type="search" data-helper-search placeholder="Search eligible Helpers" autocomplete="off"></label>
                <div class="cm-field"><label for="emergencyHelper">Eligible Helper</label>
                <select name="helper_id" id="emergencyHelper" required><option value="">Select a Helper</option>
                    @foreach($helpers as $helper)<option value="{{ $helper['id'] }}">{{ $helper['label'] }}</option>@endforeach
                </select></div>
                <p data-helper-reason role="status">{{ $helperChoices['reason'] }}</p>
                @include('partials.helper-unavailable', ['choices'=>$helperChoices])
                </div>
                <footer class="workflow-dialog__footer"><button type="button" class="cm-button" data-dialog-close>Cancel</button><button class="cm-button cm-primary" type="submit" @disabled($helpers->isEmpty())>Confirm assignment</button></footer>
            </form>
            </dialog>
            @if($helpers->isEmpty())<p>No eligible Helper is available for assignment. Adviser coordination does not require waiting 15 minutes.</p>@endif
            <form method="POST" action="{{ route($role.'.emergency.escalation', [$case->source, $case->id]) }}">@csrf<button class="cm-button cm-primary" type="submit">Request Adviser Escalation</button></form>
        @else<p>No queued assignment is actionable for this record. Review the current request state with the responsible Adviser.</p>
            <form method="POST" action="{{ route($role.'.emergency.escalation', [$case->source, $case->id]) }}">@csrf<button class="cm-button" type="submit">Request Adviser Escalation</button></form>
        @endif
        @if($role === 'adviser' && $case->source === 'emergency_alerts' && in_array((int)auth()->user()->adviser?->id, [(int)$case->adviser_id, (int)$case->primary_adviser_id], true))<p><a class="cm-button" href="{{ route('adviser.emergencies.show', $case->id) }}">Open authorized Adviser review and Professional handoff</a></p>@endif
    </section>
</div>
<script src="{{ asset('js/helper-candidates.js') }}?v={{ filemtime(public_path('js/helper-candidates.js')) }}" defer></script>
@endsection
