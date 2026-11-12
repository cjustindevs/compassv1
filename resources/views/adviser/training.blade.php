@extends('layouts.app')
@section('title','Training recommendations - COMPASS')
@section('content')
<div class="{{ $isAdviser ? 'adviser-page-content' : 'helper-page-content' }} training-page">
<x-ui.page-header title="Training recommendations" description="Documented learning activities, completion evidence, and Adviser follow-up." />
@if($isAdviser && $selectedHelper)<p class="ui-card-sub" style="margin:0 0 16px">Showing training for {{ $selectedHelper->full_name }}. <a class="btn-secondary" href="{{ route('adviser.training') }}">View all Helpers</a></p>@endif
@if(session('success'))<p role="status" class="ui-card">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert" class="ui-notice">{{ $errors->first() }}</p>@endif
@if($isAdviser)
<details class="ui-card training-assign" @if($selectedEvaluation || $errors->any()) open @endif><summary class="ui-card-title">Assign a learning activity</summary>
@if($evaluations->isEmpty())<p class="ui-card-sub" style="margin-top:12px">Complete a documented competency evaluation before assigning training.</p>@else
<form method="POST" action="{{ route('adviser.training.store') }}" class="training-form">@csrf
<x-ui.field label="Evaluation evidence" for="trainingEvaluation"><select id="trainingEvaluation" name="evaluation_id" data-control="select" required>@foreach($evaluations as $evaluation)<option value="{{ $evaluation->id }}" @selected((string) old('evaluation_id', $selectedEvaluation?->id) === (string) $evaluation->id)>{{ $evaluation->helper->full_name }} | Evaluation #{{ $evaluation->id }} | Score {{ $evaluation->overall_score }} (1–5)</option>@endforeach</select></x-ui.field>
<x-ui.field label="Competency gap" for="trainingCriterion"><select id="trainingCriterion" name="criterion" data-control="select" required>@foreach(\App\Services\CompetencyRubric::CRITERIA as $key=>$criterion)<option value="{{ $key }}" @selected(old('criterion', $selectedCriterion) === $key)>{{ $criterion[0] }}</option>@endforeach</select></x-ui.field>
<x-ui.field label="Reason and supporting evidence" for="trainingReason"><textarea id="trainingReason" name="reason" required minlength="10" maxlength="2000" rows="3">{{ old('reason') }}</textarea></x-ui.field>
<x-ui.field label="Learning material or activity" for="trainingActivity"><textarea id="trainingActivity" name="activity" required maxlength="2000" rows="3">{{ old('activity') }}</textarea></x-ui.field>
<x-ui.field label="Priority" for="trainingPriority"><select id="trainingPriority" name="priority" data-control="select"><option value="normal" @selected(old('priority', 'normal') === 'normal')>Normal</option><option value="high" @selected(old('priority') === 'high')>High</option></select></x-ui.field>
<x-ui.field label="Due date (optional)" for="trainingDue"><input id="trainingDue" name="due_date" type="date" data-control="date" value="{{ old('due_date') }}"></x-ui.field>
<div class="training-submit"><button class="btn-primary">Assign recommendation</button></div></form>@endif</details>
@endif
@forelse($tasks as $task)
<x-ui.card class="training-task">
<div class="ui-card-head"><h2 class="ui-card-title">{{ $task->helper->full_name }} | {{ \App\Services\CompetencyRubric::CRITERIA[$task->criterion][0] ?? $task->criterion }}</h2><x-ui.badge :tone="in_array($task->status,['reviewed','completed']) ? 'green' : ($task->status==='cancelled' ? 'neutral' : 'amber')">{{ ucfirst(str_replace('_',' ',$task->status)) }}</x-ui.badge></div>
<p class="ui-card-sub">Evaluation #{{ $task->evaluation_id }} | {{ ucfirst($task->priority) }} priority | Due {{ $task->due_date?->format('M d, Y') ?? 'No deadline' }}</p>
<p class="ui-prose" style="margin-top:12px"><strong>Reason:</strong> {{ $task->reason }}</p><p class="ui-prose" style="margin-top:8px"><strong>Activity:</strong> {{ $task->activity }}</p>
@if($task->completion_evidence)<p class="ui-prose" style="margin-top:8px"><strong>Completion evidence:</strong> {{ $task->completion_evidence }}</p>@endif
@if($task->review_notes)<p class="ui-prose" style="margin-top:8px"><strong>Adviser review:</strong> {{ $task->review_notes }}</p>@endif
@if(!in_array($task->status,['reviewed','cancelled']) && ($isAdviser || in_array($task->status,['assigned','in_progress'])))
<form method="POST" action="{{ route($isAdviser ? 'adviser.training.update' : 'helper.training.update',$task) }}" class="training-update">@csrf @method('PATCH')
@if($isAdviser)
<x-ui.field label="Review notes" for="reviewNotes{{ $task->id }}"><textarea id="reviewNotes{{ $task->id }}" name="review_notes" required maxlength="2000" rows="3"></textarea></x-ui.field>
<div class="training-buttons">@if($task->status==='completed')<button name="status" value="reviewed" class="btn-primary">Confirm reviewed</button><button name="status" value="in_progress" class="btn-secondary">Return for follow-up</button>@endif
<button name="status" value="cancelled" class="btn-secondary training-danger">Cancel recommendation</button></div>
@elseif($task->status==='assigned')<div class="training-buttons"><button name="status" value="in_progress" class="btn-primary">Acknowledge and start</button></div>
@else<x-ui.field label="Completion evidence" for="completionEvidence{{ $task->id }}"><textarea id="completionEvidence{{ $task->id }}" name="completion_evidence" required maxlength="3000" rows="3"></textarea></x-ui.field><div class="training-buttons"><button name="status" value="completed" class="btn-primary">Submit for Adviser review</button></div>@endif
</form>@endif
<x-supervision-history :record="$task" />
</x-ui.card>
@empty<x-ui.empty title="No training recommendations yet" text="Recommendations appear here once an evaluation identifies a competency gap." />@endforelse
{{ $tasks->links('vendor.pagination.compass', ['noun' => 'recommendations']) }}
</div>
<style>
.training-page .training-assign,.training-page .training-task{margin-bottom:16px}
.training-page .training-assign>summary{cursor:pointer}
.training-page .training-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin-top:16px}
.training-page .training-form .training-submit{grid-column:1/-1}
.training-page .ui-field{min-width:0}
.training-page .ui-field textarea{padding:12px;font-size:15px;border:1px solid var(--compass-border,#e5e7eb);border-radius:12px;background:#fff;resize:vertical;font-family:inherit}
.training-page .ui-field textarea:focus-visible{outline:none;box-shadow:var(--ring,0 0 0 3px rgba(4,160,82,.25))}
.training-page .training-update{display:grid;gap:12px;margin-top:16px}
.training-page .training-buttons{display:flex;flex-wrap:wrap;gap:8px}
.training-page .training-danger{color:#b91c1c;border-color:#fecaca}
</style>
@endsection
