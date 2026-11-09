@extends('layouts.app')
@section('title', 'Emergency Review - COMPASS')
@section('content')
@include('partials.management-ui-styles')
@php
    $terminal = in_array($alert->status, ['resolved', 'closed']);
    $risk = strtolower($alert->risk_level ?? '');
    $history = \Illuminate\Support\Facades\DB::table('emergency_review_actions')->where('emergency_alert_id', $alert->id)->orderByDesc('id')->get();
@endphp
<div class="cm-page er-page">
    <a class="er-back" href="{{ route('adviser.emergencies') }}">&larr; Back to emergencies</a>
    <header class="er-header">
        <div class="er-title">
            <h1>Emergency review</h1>
            <div class="er-chips">
                <span class="er-chip {{ $terminal ? 'is-done' : 'is-open' }}">{{ ucwords(str_replace('_', ' ', $alert->status)) }}</span>
                @if($risk)<span class="er-chip is-risk-{{ $risk }}">{{ ucfirst($risk) }} risk</span>@endif
                <span class="er-meta">Seeker {{ $alert->session?->seeker?->generated_alias ?? 'Anonymous' }}@if($alert->triggered_at) · Raised {{ $alert->triggered_at->copy()->timezone('Asia/Manila')->format('M d, g:i A') }} PHT @endif</span>
            </div>
        </div>
        <div class="er-header-actions">
            @if(!$terminal)<a class="cm-button" href="{{ route('adviser.emergency.coordinate', ['emergency_alerts', $alert->id]) }}">Open Helper assignment</a>@endif
            <a class="cm-button cm-primary" href="#emergencyProfessionalHandoff">Professional referral</a>
        </div>
    </header>
    @if(session('success'))<div class="cm-alert" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="cm-alert cm-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <div class="er-grid">
        {{-- Case information: what happened and where the referral stands. --}}
        <div class="er-stack">
            <section class="cm-card" aria-labelledby="caseTitle">
                <h2 id="caseTitle" class="er-card-title">Case overview</h2>
                <dl class="er-facts">
                    <div><dt>Seeker alias</dt><dd>{{ $alert->session?->seeker?->generated_alias ?? 'Anonymous' }}</dd></div>
                    <div><dt>Helper</dt><dd>{{ $alert->session?->helper?->full_name ?? 'Unassigned' }}</dd></div>
                    <div><dt>Primary Adviser</dt><dd>{{ $alert->session?->helper?->adviser?->full_name ?? 'Unassigned' }}</dd></div>
                    <div><dt>Emergency coordinator</dt><dd>{{ $alert->adviser?->full_name ?? 'Unassigned' }}</dd></div>
                    <div><dt>Support status</dt><dd>{{ ucwords(str_replace('_', ' ', $alert->session?->session_status ?? 'Unassigned')) }}</dd></div>
                    <div><dt>Reported risk</dt><dd>{{ ucfirst($alert->risk_level ?? 'Not recorded') }}</dd></div>
                </dl>
                <div class="er-reason"><h3>Reason for escalation</h3><p class="cm-message">{{ $alert->trigger_reason }}</p></div>
            </section>
            @include('adviser.partials.emergency-handoff')
            @include('adviser.partials.emergency-screening-summary')
            <details class="cm-card er-collapsible" aria-labelledby="documentationTitle">
                <summary id="documentationTitle">Supporting documentation</summary>
                <p class="cm-message">{{ $alert->session?->report?->session_summary ?? 'No session summary submitted yet.' }}</p>
                @if($alert->session)<a class="cm-button" href="{{ route('adviser.session.show', $alert->session_id) }}">Review session documentation</a>@endif
            </details>
        </div>

        {{-- Everything the Adviser can do, in one place. Each action opens its form in a dialog. --}}
        <aside class="er-stack er-side">
            @if(!$terminal)
                <section class="cm-card er-actions" aria-labelledby="actionsTitle">
                    <h2 id="actionsTitle" class="er-card-title">Actions</h2>
                    <div class="er-action">
                        <p>{{ $alert->acknowledged_at ? 'Document instructions or coordination for this case.' : 'Acknowledge the escalation, then record next steps.' }}</p>
                        <section data-focus-panel data-focus-label="{{ $alert->acknowledged_at ? 'Record follow-up' : 'Acknowledge / record action' }}" data-focus-open="{{ old('action') && old('action') !== 'rejected' ? 'true' : 'false' }}" aria-labelledby="recordTitle">
                            <h2 id="recordTitle">Record a review action</h2><p class="cm-help">Acknowledge the report or document instructions and coordination.</p>
                            <form method="POST" action="{{ route('adviser.emergencies.action',$alert->id) }}">@csrf
                                <div class="cm-field"><label for="reviewAction">Action</label><select id="reviewAction" name="action">@if(!$alert->acknowledged_at)<option value="acknowledged">Acknowledge escalation</option>@endif<option value="instruction" @selected(old('action')==='instruction')>Document instructions</option><option value="coordination" @selected(old('action')==='coordination')>Document coordination</option></select></div>
                                <div class="cm-field"><label for="reviewNotes">Action notes</label><textarea id="reviewNotes" name="notes" required maxlength="2000" rows="3" placeholder="Describe your response and next steps">{{ old('action') !== 'rejected' ? old('notes') : '' }}</textarea></div>
                                <button type="submit" class="cm-button cm-primary">Record action</button>
                            </form>
                        </section>
                    </div>
                    <div class="er-action is-primary">
                        <p>Close the emergency with the outcome and follow-up plan.</p>
                        <section data-focus-panel data-focus-label="Resolve emergency" data-focus-open="{{ old('resolution_notes') ? 'true' : 'false' }}" aria-labelledby="resolveTitle">
                            <h2 id="resolveTitle">Resolve emergency</h2><p class="cm-help">Record the actions taken, coordination completed, and follow-up plan before closing this emergency.</p>
                            <form method="POST" action="{{ route('adviser.emergencies.resolve', $alert->id) }}">@csrf
                                <div class="cm-field"><label for="resolutionNotes">Resolution summary</label><textarea id="resolutionNotes" name="resolution_notes" required rows="3" maxlength="1000" placeholder="Document the outcome and follow-up plan">{{ old('resolution_notes') }}</textarea></div>
                                <button type="submit" class="cm-button cm-primary">Mark resolved</button>
                            </form>
                        </section>
                    </div>
                    <div class="er-action">
                        <p>{{ $alert->review_decision === 'rejected' ? 'Returned to the Helper for follow-up.' : 'Ask the Helper to clarify. This does not resolve the emergency.' }}</p>
                        <details data-focus-panel data-focus-label="Return to Helper" data-focus-open="{{ old('action') === 'rejected' ? 'true' : 'false' }}" aria-labelledby="returnTitle" @if(old('action') === 'rejected') open @endif >
                            <summary id="returnTitle">Return to Helper</summary><p class="cm-help">Request clarification or follow-up. This does not resolve the emergency.</p>
                            @if($alert->review_decision === 'rejected')<div class="cm-alert"><strong>Returned for follow-up</strong><p class="cm-message">{{ $alert->rejection_reason }}</p></div>@else
                            <form method="POST" action="{{ route('adviser.emergencies.action',$alert->id) }}">@csrf<input type="hidden" name="action" value="rejected">
                                <div class="cm-field"><label for="rejectionReason">Reason and next steps</label><textarea id="rejectionReason" name="notes" required maxlength="2000" rows="3" placeholder="Explain what the Helper needs to clarify">{{ old('action') === 'rejected' ? old('notes') : '' }}</textarea></div>
                                <button type="submit" class="cm-button">Return to Helper</button>
                            </form>@endif
                        </details>
                    </div>
                    <div class="er-action">
                        <p>Coordinator: <strong>{{ app(\App\Services\EmergencyCoordinator::class)->name($alert->adviser) }}</strong>. Hand this case to another Adviser.</p>
                        <section data-focus-panel data-focus-label="Transfer coordinator" data-focus-open="{{ old('handoff_reason') ? 'true' : 'false' }}">
                            <p>Current coordinator: <strong>{{ app(\App\Services\EmergencyCoordinator::class)->name($alert->adviser) }}</strong></p>
                            <form method="POST" action="{{ route('adviser.emergencies.coordinator', $alert->id) }}">@csrf
                                <input type="hidden" name="expected_adviser_id" value="{{ $alert->adviser_id }}">
                                <div class="cm-field"><label for="coordinatorId">New responsible coordinator</label><select id="coordinatorId" name="adviser_id" required><option value="">Select an Adviser</option>@foreach($coordinators as $coordinator)<option value="{{ $coordinator->id }}" @selected((string)old('adviser_id') === (string)$coordinator->id)>{{ app(\App\Services\EmergencyCoordinator::class)->name($coordinator) }}</option>@endforeach</select></div>
                                <div class="cm-field"><label for="handoffReason">Handoff reason</label><textarea id="handoffReason" name="handoff_reason" required minlength="10" maxlength="1000">{{ old('handoff_reason') }}</textarea></div>
                                <button type="submit" class="cm-button cm-primary" @disabled($coordinators->isEmpty())>Transfer and notify</button>
                            </form>
                        </section>
                    </div>
                </section>
            @else
                <section class="cm-card er-outcome"><h2 class="er-card-title">{{ ucfirst($alert->status) }}</h2><p class="cm-message">{{ $alert->resolution_notes }}</p></section>
            @endif

            <section class="cm-card" aria-labelledby="historyTitle">
                <div class="er-card-head"><h2 id="historyTitle" class="er-card-title">Action history</h2><span class="er-meta">Latest first · PHT</span></div>
                @forelse($history as $action)
                    <article class="er-history-item"><div class="er-history-head"><strong>{{ ucwords(str_replace('_', ' ', $action->action)) }}</strong><time>{{ \Illuminate\Support\Carbon::parse($action->created_at)->timezone('Asia/Manila')->format('M d, g:i A') }}</time></div>@if($action->notes)<p class="cm-message">{{ $action->notes }}</p>@endif</article>
                @empty<div class="er-empty">No actions recorded yet.</div>@endforelse
            </section>
        </aside>
    </div>
</div>
<style>
.er-page{width:100%;max-width:none;padding:20px 32px 32px}
.er-back{display:inline-block;font-size:13px;font-weight:600;color:#047a3e;text-decoration:none;margin-bottom:8px}.er-back:hover{text-decoration:underline}
.er-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px 24px;flex-wrap:wrap;margin-bottom:16px}
.er-title h1{margin:0 0 8px;font-size:24px;font-weight:700;color:#163b2d}
.er-chips{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.er-chip{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;border:1px solid transparent}
.er-chip.is-open{background:#fff4e0;color:#8a5300;border-color:#f3d7a6}.er-chip.is-done{background:#e7f6ee;color:#05683a;border-color:#bfe5cf}
.er-chip.is-risk-emergency,.er-chip.is-risk-critical{background:#fdecec;color:#a32626;border-color:#f5c2c2}.er-chip.is-risk-high{background:#fff0e8;color:#9a3f12;border-color:#f6cdb5}.er-chip.is-risk-moderate{background:#fff8e1;color:#7a5a00;border-color:#f1dea0}.er-chip.is-risk-low{background:#e7f6ee;color:#05683a;border-color:#bfe5cf}
.er-meta{font-size:12px;color:#6b7b74}
.er-header-actions{display:flex;gap:8px;flex-wrap:wrap}
.er-grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(300px,1fr);gap:16px;align-items:start}
.er-stack{display:grid;gap:16px;min-width:0}
.er-side{position:sticky;top:calc(16px + env(safe-area-inset-top,0px))}
.er-page .cm-card{padding:18px}
.er-card-title{margin:0 0 12px;font-size:16px;font-weight:700;color:#163b2d}
.er-card-head{display:flex;align-items:baseline;justify-content:space-between;gap:8px}
.er-facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px 16px;margin:0 0 14px}
.er-facts dt{font-size:12px;color:#6b7b74;margin-bottom:4px}.er-facts dd{margin:0;font-weight:600;overflow-wrap:anywhere}
.er-reason{border-left:3px solid #d36154;background:#fff6f4;padding:12px 14px;border-radius:8px}
.er-reason h3{color:#913a30;font-size:13px;font-weight:700;margin:0 0 4px}.er-reason p{margin:0}
.er-actions .er-action{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-top:1px solid #e6eee9}
.er-actions .er-action:first-of-type{border-top:0;padding-top:0}
.er-action>p{margin:0;font-size:13px;line-height:1.45;color:#40564b;flex:1;min-width:0}
.er-action>[data-focus-panel]{flex:none;margin:0;padding:0;border:0;background:none;box-shadow:none}
.er-action .focus-panel-button{min-height:38px;padding:8px 14px;border-radius:8px;border:1px solid #bfd9c9;background:#fff;color:#05683a;font-weight:600;font-size:13px;white-space:nowrap;cursor:pointer}
.er-action .focus-panel-button:hover{background:#f1f8f4}
.er-action.is-primary .focus-panel-button{background:#078749;border-color:#078749;color:#fff}.er-action.is-primary .focus-panel-button:hover{background:#05683a}
.er-history-item{padding:10px 0;border-top:1px solid #e6eee9}.er-history-item:first-of-type{border-top:0;padding-top:0}
.er-history-head{display:flex;justify-content:space-between;gap:8px;font-size:13px}.er-history-head time{color:#6b7b74;font-size:12px;white-space:nowrap}
.er-history-item p{margin:4px 0 0;font-size:13px}
.er-facts-2{grid-template-columns:repeat(2,minmax(0,1fr))}
.er-status{margin:0 0 12px;padding:10px 12px;border-radius:8px;background:#f4f7f5;color:#40564b;font-size:13px}
.er-report{display:grid;gap:12px;margin:0}.er-report dt{font-size:12px;color:#6b7b74;margin-bottom:4px}.er-report dd{margin:0}
.er-empty{background:#f7faf8;border-radius:10px;padding:14px;color:#6b7b74;font-size:13px}
.er-collapsible>summary,.er-page details.cm-card>summary{cursor:pointer;font-weight:700;font-size:15px;color:#163b2d}
.er-page details[open]>summary{margin-bottom:12px}
.er-professional-handoff{border-top:3px solid #049b50;scroll-margin-top:20px}.er-professional-handoff button:disabled{opacity:.6;cursor:not-allowed}
.er-page .cm-message{line-height:1.5}.er-page textarea{min-height:76px}.er-page .cm-field :is(select,textarea){max-width:100%}.er-page .cm-button{min-height:40px}.er-page .cm-field{margin-bottom:10px}.er-page .cm-help{margin:6px 0 12px}
@media(max-width:1100px){.er-grid{grid-template-columns:1fr}.er-side{position:static}}
@media(max-width:600px){.er-page{padding:16px}.er-facts{grid-template-columns:1fr 1fr}.er-header-actions,.er-header-actions .cm-button{width:100%}.er-actions .er-action{flex-direction:column;align-items:stretch}.er-action .focus-panel-button{width:100%}}
</style>
@endsection
