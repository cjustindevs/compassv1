@extends('layouts.app')
@section('title', 'Emergency Review - COMPASS')
@section('content')
@php
    $terminal = in_array($alert->status, ['resolved', 'closed']);
    $risk = strtolower($alert->risk_level ?? '');
    $history = \Illuminate\Support\Facades\DB::table('emergency_review_actions')->where('emergency_alert_id', $alert->id)->orderByDesc('id')->get();
@endphp
<div class="er-page">
    <x-ui.page-header title="Emergency review" description="Review, coordinate and resolve this emergency case" :back-href="route('adviser.emergencies')" back-label="Back to Emergencies">
        <x-slot:actions>
            @if(!$terminal)<a class="btn btn-outline" href="{{ route('adviser.emergency.coordinate', ['emergency_alerts', $alert->id]) }}">Open Helper assignment</a>@endif
            <a class="btn btn-primary" href="#emergencyProfessionalHandoff">Professional referral</a>
        </x-slot:actions>
    </x-ui.page-header>
    @if(session('success'))<div class="er-alert" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="er-alert er-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    <x-ui.card :title="'Case #'.$alert->id" :subtitle="$alert->session?->seeker?->generated_alias ? 'Seeker '.$alert->session->seeker->generated_alias : null" class="er-summary">
        <x-slot:actions>
            <x-ui.badge :tone="$terminal ? 'green' : 'amber'">{{ ucwords(str_replace('_', ' ', $alert->status)) }}</x-ui.badge>
            @if($risk)<x-ui.badge :tone="in_array($risk, ['emergency','critical','high']) ? 'red' : ($risk === 'moderate' ? 'amber' : 'green')">{{ ucfirst($risk) }} risk</x-ui.badge>@endif
        </x-slot:actions>
        <dl class="er-facts">
            <div><dt>Seeker alias</dt><dd>{{ $alert->session?->seeker?->generated_alias ?? 'Anonymous' }}</dd></div>
            <div><dt>Helper</dt><dd>{{ $alert->session?->helper?->full_name ?? 'Unassigned' }}</dd></div>
            <div><dt>Emergency coordinator</dt><dd>{{ $alert->adviser?->full_name ?? 'Unassigned' }}</dd></div>
            <div><dt>Detected</dt><dd>{{ $alert->triggered_at ? $alert->triggered_at->copy()->timezone('Asia/Manila')->format('M d, g:i A').' PHT' : 'Not recorded' }}</dd></div>
            <div><dt>Acknowledged</dt><dd>{{ $alert->acknowledged_at ? $alert->acknowledged_at->copy()->timezone('Asia/Manila')->format('M d, g:i A').' PHT' : 'Not yet' }}</dd></div>
        </dl>
    </x-ui.card>

    <div class="er-grid">
        {{-- Case information: what happened and where the referral stands. --}}
        <div class="er-stack">
            <section class="ui-card" aria-labelledby="caseTitle">
                <h2 id="caseTitle" class="er-card-title">Case overview</h2>
                <dl class="er-facts">
                    <div><dt>Primary Adviser</dt><dd>{{ $alert->session?->helper?->adviser?->full_name ?? 'Unassigned' }}</dd></div>
                    <div><dt>Support status</dt><dd>{{ ucwords(str_replace('_', ' ', $alert->session?->session_status ?? 'Unassigned')) }}</dd></div>
                    <div><dt>Reported risk</dt><dd>{{ ucfirst($alert->risk_level ?? 'Not recorded') }}</dd></div>
                </dl>
                <div class="er-reason"><h3>Reason for escalation</h3><p class="er-message">{{ $alert->trigger_reason }}</p></div>
            </section>
            @include('adviser.partials.emergency-handoff')
            @include('adviser.partials.emergency-screening-summary')
            <details class="ui-card er-collapsible" aria-labelledby="documentationTitle">
                <summary id="documentationTitle">Supporting documentation</summary>
                <p class="er-message">{{ $alert->session?->report?->session_summary ?? 'No session summary submitted yet.' }}</p>
                @if($alert->session)<a class="btn btn-outline" href="{{ route('adviser.session.show', $alert->session_id) }}">Review session documentation</a>@endif
            </details>
        </div>

        {{-- Everything the Adviser can do, in one place. Each action opens its form in a dialog. --}}
        <aside class="er-stack er-side">
            @if(!$terminal)
                <section class="ui-card er-actions" aria-labelledby="actionsTitle">
                    <h2 id="actionsTitle" class="er-card-title">Actions</h2>
                    <div class="er-action">
                        <p>{{ $alert->acknowledged_at ? 'Document instructions or coordination for this case.' : 'Acknowledge the escalation, then record next steps.' }}</p>
                        <section data-focus-panel data-focus-label="{{ $alert->acknowledged_at ? 'Record follow-up' : 'Acknowledge / record action' }}" data-focus-open="{{ old('action') && old('action') !== 'rejected' ? 'true' : 'false' }}" aria-labelledby="recordTitle">
                            <h2 id="recordTitle">Record a review action</h2><p class="er-help">Acknowledge the report or document instructions and coordination.</p>
                            <form method="POST" action="{{ route('adviser.emergencies.action',$alert->id) }}">@csrf
                                <div class="er-field"><label for="reviewAction">Action</label><select id="reviewAction" name="action">@if(!$alert->acknowledged_at)<option value="acknowledged">Acknowledge escalation</option>@endif<option value="instruction" @selected(old('action')==='instruction')>Document instructions</option><option value="coordination" @selected(old('action')==='coordination')>Document coordination</option></select></div>
                                <div class="er-field"><label for="reviewNotes">Action notes</label><textarea id="reviewNotes" name="notes" required maxlength="2000" rows="3" placeholder="Describe your response and next steps">{{ old('action') !== 'rejected' ? old('notes') : '' }}</textarea></div>
                                <button type="submit" class="btn btn-primary">Record action</button>
                            </form>
                        </section>
                    </div>
                    <div class="er-action is-primary">
                        <p>Close the emergency with the outcome and follow-up plan.</p>
                        <section data-focus-panel data-focus-label="Resolve emergency" data-focus-open="{{ old('resolution_notes') ? 'true' : 'false' }}" aria-labelledby="resolveTitle">
                            <h2 id="resolveTitle">Resolve emergency</h2><p class="er-help">Record the actions taken, coordination completed, and follow-up plan before closing this emergency.</p>
                            <form method="POST" action="{{ route('adviser.emergencies.resolve', $alert->id) }}">@csrf
                                <div class="er-field"><label for="resolutionNotes">Resolution summary</label><textarea id="resolutionNotes" name="resolution_notes" required rows="3" maxlength="1000" placeholder="Document the outcome and follow-up plan">{{ old('resolution_notes') }}</textarea></div>
                                <button type="submit" class="btn btn-primary">Mark resolved</button>
                            </form>
                        </section>
                    </div>
                    <div class="er-action">
                        <p>{{ $alert->review_decision === 'rejected' ? 'Returned to the Helper for follow-up.' : 'Ask the Helper to clarify. This does not resolve the emergency.' }}</p>
                        <details data-focus-panel data-focus-label="Return to Helper" data-focus-open="{{ old('action') === 'rejected' ? 'true' : 'false' }}" aria-labelledby="returnTitle" @if(old('action') === 'rejected') open @endif >
                            <summary id="returnTitle">Return to Helper</summary><p class="er-help">Request clarification or follow-up. This does not resolve the emergency.</p>
                            @if($alert->review_decision === 'rejected')<div class="er-alert"><strong>Returned for follow-up</strong><p class="er-message">{{ $alert->rejection_reason }}</p></div>@else
                            <form method="POST" action="{{ route('adviser.emergencies.action',$alert->id) }}">@csrf<input type="hidden" name="action" value="rejected">
                                <div class="er-field"><label for="rejectionReason">Reason and next steps</label><textarea id="rejectionReason" name="notes" required maxlength="2000" rows="3" placeholder="Explain what the Helper needs to clarify">{{ old('action') === 'rejected' ? old('notes') : '' }}</textarea></div>
                                <button type="submit" class="btn btn-outline">Return to Helper</button>
                            </form>@endif
                        </details>
                    </div>
                    <div class="er-action">
                        <p>Coordinator: <strong>{{ app(\App\Services\EmergencyCoordinator::class)->name($alert->adviser) }}</strong>. Hand this case to another Adviser.</p>
                        <section data-focus-panel data-focus-label="Transfer coordinator" data-focus-open="{{ old('handoff_reason') ? 'true' : 'false' }}">
                            <p>Current coordinator: <strong>{{ app(\App\Services\EmergencyCoordinator::class)->name($alert->adviser) }}</strong></p>
                            <form method="POST" action="{{ route('adviser.emergencies.coordinator', $alert->id) }}">@csrf
                                <input type="hidden" name="expected_adviser_id" value="{{ $alert->adviser_id }}">
                                <div class="er-field"><label for="coordinatorId">New responsible coordinator</label><select id="coordinatorId" name="adviser_id" required><option value="">Select an Adviser</option>@foreach($coordinators as $coordinator)<option value="{{ $coordinator->id }}" @selected((string)old('adviser_id') === (string)$coordinator->id)>{{ app(\App\Services\EmergencyCoordinator::class)->name($coordinator) }}</option>@endforeach</select></div>
                                <div class="er-field"><label for="handoffReason">Handoff reason</label><textarea id="handoffReason" name="handoff_reason" required minlength="10" maxlength="1000">{{ old('handoff_reason') }}</textarea></div>
                                <button type="submit" class="btn btn-primary" @disabled($coordinators->isEmpty())>Transfer and notify</button>
                            </form>
                        </section>
                    </div>
                </section>
            @else
                <section class="ui-card er-outcome"><h2 class="er-card-title">{{ ucfirst($alert->status) }}</h2><p class="er-message">{{ $alert->resolution_notes }}</p></section>
            @endif

            <section class="ui-card" aria-labelledby="historyTitle">
                <div class="er-card-head"><h2 id="historyTitle" class="er-card-title">Action history</h2><span class="er-meta">Latest first · PHT</span></div>
                @forelse($history as $action)
                    <article class="er-history-item"><div class="er-history-head"><strong>{{ ucwords(str_replace('_', ' ', $action->action)) }}</strong><time>{{ \Illuminate\Support\Carbon::parse($action->created_at)->timezone('Asia/Manila')->format('M d, g:i A') }}</time></div>@if($action->notes)<p class="er-message">{{ $action->notes }}</p>@endif</article>
                @empty<div class="er-empty">No actions recorded yet.</div>@endforelse
            </section>
        </aside>
    </div>
</div>
<style>
.er-page{min-width:0}
.er-card-title{margin:0 0 12px;font-size:20px;font-weight:700;color:var(--compass-text,#1f2937)}
.er-card-head{display:flex;align-items:baseline;justify-content:space-between;flex-wrap:wrap;gap:8px}
.er-meta{font-size:13px;color:var(--compass-muted,#6b7280)}
.er-alert{padding:12px 16px;border:1px solid #bde3cb;background:#effaf3;color:#24643d;border-radius:12px;margin-bottom:16px;font-size:14px}.er-alert p{margin:0}
.er-error{border-color:#ecc8c8;background:#fff4f4;color:#9b3030}
.er-grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(300px,1fr);gap:16px;align-items:start}
.er-stack{display:grid;gap:16px;min-width:0}.er-stack>.ui-card{margin-bottom:0}
.er-side{position:sticky;top:calc(16px + env(safe-area-inset-top,0px))}
.er-facts{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px 24px;margin:0 0 14px}
.er-summary .er-facts{margin-bottom:0}
.er-facts dt{font-size:13px;color:var(--compass-muted,#6b7280);margin-bottom:4px}.er-facts dd{margin:0;font-weight:600;overflow-wrap:anywhere}
.er-facts-2{grid-template-columns:repeat(2,minmax(0,1fr))}
.er-reason{border-left:3px solid #d36154;background:#fff6f4;padding:12px 14px;border-radius:8px}
.er-reason h3{color:#913a30;font-size:14px;font-weight:700;margin:0 0 4px}.er-reason p{margin:0}
.er-actions .er-action{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-top:1px solid var(--compass-divider,#e5e7eb)}
.er-actions .er-action:first-of-type{border-top:0;padding-top:0}
.er-action>p{margin:0;font-size:14px;line-height:1.45;color:var(--compass-muted,#6b7280);flex:1;min-width:0}
.er-action>[data-focus-panel]{flex:none;margin:0;padding:0;border:0;background:none;box-shadow:none}
.er-action .focus-panel-button{min-height:44px;padding:8px 16px;border-radius:999px;border:1px solid #bfd9c9;background:#fff;color:#05683a;font-weight:600;font-size:14px;white-space:nowrap;cursor:pointer}
.er-action .focus-panel-button:hover{background:#f1f8f4}
.er-action.is-primary .focus-panel-button{background:#078749;border-color:#078749;color:#fff}.er-action.is-primary .focus-panel-button:hover{background:#05683a}
.er-history-item{padding:10px 0;border-top:1px solid var(--compass-divider,#e5e7eb)}.er-history-item:first-of-type{border-top:0;padding-top:0}
.er-history-head{display:flex;justify-content:space-between;gap:8px;font-size:14px}.er-history-head time{color:var(--compass-muted,#6b7280);font-size:13px;white-space:nowrap}
.er-history-item p{margin:4px 0 0;font-size:14px}
.er-status{margin:0 0 12px;padding:10px 12px;border-radius:8px;background:#f4f7f5;color:#40564b;font-size:14px}
.er-report{display:grid;gap:12px;margin:0}.er-report dt{font-size:13px;color:var(--compass-muted,#6b7280);margin-bottom:4px}.er-report dd{margin:0}
.er-empty{background:#f7faf8;border-radius:12px;padding:14px;color:var(--compass-muted,#6b7280);font-size:14px}
.er-page details.ui-card>summary{cursor:pointer;font-weight:700;font-size:16px;color:var(--compass-text,#1f2937)}
.er-page details[open]>summary{margin-bottom:12px}
.er-professional-handoff{border-top:3px solid #049b50;scroll-margin-top:20px}.er-professional-handoff button:disabled{opacity:.6;cursor:not-allowed}
.er-message{white-space:pre-wrap;overflow-wrap:anywhere;margin:8px 0;line-height:1.5}
.er-help{margin:6px 0 12px;color:var(--compass-muted,#6b7280);font-size:14px}
.er-field{margin-bottom:12px}.er-field label{display:block;font-size:14px;font-weight:600;margin-bottom:6px}
.er-field :is(input,select,textarea){width:100%;max-width:100%;min-height:44px;border:1px solid #d1d5db;border-radius:12px;padding:10px 12px;font:inherit;background:#fff;color:inherit}
.er-field textarea{min-height:76px;resize:vertical}
.er-page :is(input,select,textarea,button,summary,a):focus-visible{outline:2px solid #049b50;outline-offset:3px}
@media(max-width:1100px){.er-grid{grid-template-columns:1fr}.er-side{position:static}}
@media(max-width:600px){.er-actions .er-action{flex-direction:column;align-items:stretch}.er-action .focus-panel-button{width:100%}}
</style>
@endsection
