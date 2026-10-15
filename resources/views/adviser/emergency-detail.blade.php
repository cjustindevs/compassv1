@extends('layouts.app')
@section('title', 'Emergency Review - COMPASS')
@section('content')
@include('partials.management-ui-styles')
@php($terminal = in_array($alert->status, ['resolved', 'closed']))
<div class="cm-page er-page">
    @if(!$terminal)<a class="cm-button" href="{{ route('adviser.emergency.coordinate', ['emergency_alerts', $alert->id]) }}">Open Helper assignment</a>@endif
    <header class="cm-header">
        <div><h1>Emergency review</h1><p class="cm-muted">Review the report, document your response, and coordinate follow-up.</p></div>
        <div class="er-header-actions"><a class="cm-button cm-primary" href="#emergencyProfessionalHandoff">Professional referral</a><a class="cm-button" href="{{ route('adviser.emergencies') }}">Back to emergencies</a></div>
    </header>
    @if(session('success'))<div class="cm-alert" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="cm-alert cm-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="er-grid">
        <div class="er-stack">
            <section class="cm-card" aria-labelledby="caseTitle">
                <div class="cm-list-head"><h2 id="caseTitle">Case overview</h2><span class="cm-badge {{ $terminal ? 'off' : '' }}">{{ ucfirst($alert->status) }}</span></div>
                <dl class="er-facts">
                    <div><dt>Seeker alias</dt><dd>{{ $alert->session?->seeker?->generated_alias ?? 'Anonymous' }}</dd></div>
                    <div><dt>Helper</dt><dd>{{ $alert->session?->helper?->full_name ?? 'Unassigned' }}</dd></div>
                    <div><dt>Reported risk</dt><dd>{{ ucfirst($alert->risk_level ?? 'Not recorded') }}</dd></div>
                </dl>
                <div class="er-reason"><h2>Reason for escalation</h2><p class="cm-message">{{ $alert->trigger_reason }}</p></div>
            </section>
            <details class="cm-card" aria-labelledby="documentationTitle">
                <summary id="documentationTitle">Supporting documentation</summary>
                <p class="cm-message">{{ $alert->session?->report?->session_summary ?? 'No session summary submitted yet.' }}</p>
                @if($alert->session)<a class="cm-button" href="{{ route('adviser.session.show', $alert->session_id) }}">Review session documentation</a>@endif
            </details>
            <details class="cm-card" aria-labelledby="historyTitle">
                <summary id="historyTitle">Action history</summary><p class="cm-help">Latest actions first. Times are Philippine Time.</p>
                @forelse(\Illuminate\Support\Facades\DB::table('emergency_review_actions')->where('emergency_alert_id',$alert->id)->orderByDesc('id')->get() as $action)
                    <article class="cm-entry"><h2>{{ ucfirst($action->action) }}</h2><p class="cm-muted">{{ \Illuminate\Support\Carbon::parse($action->created_at)->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT</p><p class="cm-message">{{ $action->notes }}</p></article>
                @empty<div class="er-empty">No actions recorded yet. Your saved actions will appear here.</div>@endforelse
            </details>
        </div>
        <div class="er-stack">
            @include('adviser.partials.emergency-handoff')
            @include('adviser.partials.emergency-screening-summary')
            @if(!$terminal)
                <section class="cm-card" aria-labelledby="recordTitle">
                    <h2 id="recordTitle">Record a review action</h2><p class="cm-help">Acknowledge the report or document instructions and coordination.</p>
                    <form method="POST" action="{{ route('adviser.emergencies.action',$alert->id) }}">@csrf
                        <div class="cm-field"><label for="reviewAction">Action</label><select id="reviewAction" name="action">@if(!$alert->acknowledged_at)<option value="acknowledged">Acknowledge escalation</option>@endif<option value="instruction" @selected(old('action')==='instruction')>Document instructions</option><option value="coordination" @selected(old('action')==='coordination')>Document coordination</option></select></div>
                        <div class="cm-field"><label for="reviewNotes">Action notes</label><textarea id="reviewNotes" name="notes" required maxlength="2000" rows="3" placeholder="Describe your response and next steps">{{ old('action') !== 'rejected' ? old('notes') : '' }}</textarea></div>
                        <button type="submit" class="cm-button cm-primary">Record action</button>
                    </form>
                </section>
                <details class="cm-card" aria-labelledby="returnTitle" @if(old('action') === 'rejected') open @endif >
                    <summary id="returnTitle">Return to Helper</summary><p class="cm-help">Request clarification or follow-up. This does not resolve the emergency.</p>
                    @if($alert->review_decision === 'rejected')<div class="cm-alert"><strong>Returned for follow-up</strong><p class="cm-message">{{ $alert->rejection_reason }}</p></div>@else
                    <form method="POST" action="{{ route('adviser.emergencies.action',$alert->id) }}">@csrf<input type="hidden" name="action" value="rejected">
                        <div class="cm-field"><label for="rejectionReason">Reason and next steps</label><textarea id="rejectionReason" name="notes" required maxlength="2000" rows="3" placeholder="Explain what the Helper needs to clarify">{{ old('action') === 'rejected' ? old('notes') : '' }}</textarea></div>
                        <button type="submit" class="cm-button">Return to Helper</button>
                    </form>@endif
                </details>
                <section class="cm-card" aria-labelledby="resolveTitle">
                    <h2 id="resolveTitle">Resolve emergency</h2><p class="cm-help">Record the actions taken, coordination completed, and follow-up plan before closing this emergency.</p>
                    <form method="POST" action="{{ route('adviser.emergencies.resolve', $alert->id) }}">@csrf
                        <div class="cm-field"><label for="resolutionNotes">Resolution summary</label><textarea id="resolutionNotes" name="resolution_notes" required rows="3" maxlength="1000" placeholder="Document the outcome and follow-up plan">{{ old('resolution_notes') }}</textarea></div>
                        <button type="submit" class="cm-button cm-primary">Mark resolved</button>
                    </form>
                </section>
            @else
                <section class="cm-card"><h2>Review concluded</h2><p class="cm-help">This emergency is {{ $alert->status }}. Its documentation and action history remain available for review.</p></section>
            @endif
        </div>
    </div>
</div>
<style>
.er-header-actions{display:flex;gap:8px;flex-wrap:wrap}.er-professional-handoff{border-top:3px solid #049b50;scroll-margin-top:20px}.er-professional-handoff button:disabled{opacity:.6;cursor:not-allowed}.er-grid{display:grid;grid-template-columns:minmax(0,1.15fr) minmax(0,1fr);gap:12px;align-items:start}.er-stack{display:grid;gap:12px;min-width:0}.er-facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:12px 0}.er-facts dt{font-size:12px;color:#6b7b74;margin-bottom:5px}.er-facts dd{margin:0;font-weight:600;overflow-wrap:anywhere}.er-reason{border-left:3px solid #d36154;background:#fff6f4;padding:16px;border-radius:8px}.er-reason h2{color:#913a30;font-size:14px}.er-reason p{margin-bottom:0}.er-empty{background:#f7faf8;border-radius:10px;padding:18px;color:#6b7b74;font-size:13px}.er-page .cm-message{line-height:1.5}.er-page textarea{min-height:76px}@media(max-width:1000px){.er-grid{grid-template-columns:1fr}}@media(max-width:600px){.er-facts{grid-template-columns:1fr;gap:12px}.er-page .cm-button{width:100%}}
.er-page .cm-card{padding:16px}.er-page summary{cursor:pointer;font-weight:700;font-size:14px}.er-page details[open]>summary{margin-bottom:12px}.er-page .cm-entry{padding:10px 0}.er-page .cm-field{margin-bottom:10px}.er-page .cm-help{margin:6px 0 12px}
</style>
@endsection
