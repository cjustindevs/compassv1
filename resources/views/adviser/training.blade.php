@extends('layouts.app')
@section('title','Training recommendations - COMPASS')
@section('content')
<div class="{{ $isAdviser ? 'adviser-page-content' : 'helper-page-content' }} space-y-5">
<header><h1 class="text-2xl font-bold text-gray-800">Training recommendations</h1><p class="text-sm text-gray-500 mt-1">Documented learning activities, completion evidence, and Adviser follow-up.</p></header>
@if(session('success'))<p role="status" class="rounded-xl bg-green-50 text-green-800 p-3">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert" class="rounded-xl bg-red-50 text-red-800 p-3">{{ $errors->first() }}</p>@endif
@if($isAdviser)
<details class="bg-white rounded-2xl border border-gray-200 p-5"><summary class="font-semibold cursor-pointer">Assign a learning activity</summary>
@if($evaluations->isEmpty())<p class="text-sm text-gray-500 mt-3">Complete a documented competency evaluation before assigning training.</p>@else
<form method="POST" action="{{ route('adviser.training.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">@csrf
<label class="text-sm">Evaluation evidence<select name="evaluation_id" class="block w-full rounded-lg border-gray-300 mt-1" required>@foreach($evaluations as $evaluation)<option value="{{ $evaluation->id }}">{{ $evaluation->helper->public_alias }} ? Evaluation #{{ $evaluation->id }} ? {{ $evaluation->overall_score }}/5</option>@endforeach</select></label>
<label class="text-sm">Competency gap<select name="criterion" required class="block w-full rounded-lg border-gray-300 mt-1">@foreach(\App\Services\CompetencyRubric::CRITERIA as $key=>$criterion)<option value="{{ $key }}">{{ $criterion[0] }}</option>@endforeach</select></label>
<label class="text-sm">Reason and supporting evidence<textarea name="reason" required minlength="10" maxlength="2000" class="block w-full rounded-lg border-gray-300 mt-1"></textarea></label>
<label class="text-sm">Learning material or activity<textarea name="activity" required maxlength="2000" class="block w-full rounded-lg border-gray-300 mt-1"></textarea></label>
<label class="text-sm">Priority<select name="priority" class="block w-full rounded-lg border-gray-300 mt-1"><option value="normal">Normal</option><option value="high">High</option></select></label>
<label class="text-sm">Due date (optional)<input name="due_date" type="date" class="block w-full rounded-lg border-gray-300 mt-1"></label>
<button class="bg-green-600 text-white rounded-xl px-4 py-2 text-sm font-semibold">Assign recommendation</button></form>@endif</details>
@endif
@forelse($tasks as $task)
<section class="bg-white rounded-2xl border border-gray-200 p-5">
<div class="flex flex-wrap items-center justify-between gap-3"><h2 class="font-semibold">{{ $task->helper->public_alias }} ? {{ \App\Services\CompetencyRubric::CRITERIA[$task->criterion][0] ?? $task->criterion }}</h2><span class="text-sm bg-green-50 text-green-800 rounded-full px-3 py-1">{{ ucfirst(str_replace('_',' ',$task->status)) }}</span></div>
<p class="text-sm text-gray-500 mt-2">Evaluation #{{ $task->evaluation_id }} ? {{ ucfirst($task->priority) }} priority ? Due {{ $task->due_date?->format('M d, Y') ?? 'No deadline' }}</p>
<p class="text-sm whitespace-pre-wrap mt-3"><strong>Reason:</strong> {{ $task->reason }}</p><p class="text-sm whitespace-pre-wrap mt-3"><strong>Activity:</strong> {{ $task->activity }}</p>
@if($task->completion_evidence)<p class="text-sm whitespace-pre-wrap mt-3"><strong>Completion evidence:</strong> {{ $task->completion_evidence }}</p>@endif
@if($task->review_notes)<p class="text-sm whitespace-pre-wrap mt-3"><strong>Adviser review:</strong> {{ $task->review_notes }}</p>@endif
@if(!in_array($task->status,['reviewed','cancelled']) && ($isAdviser || in_array($task->status,['assigned','in_progress'])))
<form method="POST" action="{{ route($isAdviser ? 'adviser.training.update' : 'helper.training.update',$task) }}" class="mt-4 space-y-3">@csrf @method('PATCH')
@if($isAdviser)
<label class="block text-sm">Review notes<textarea name="review_notes" required maxlength="2000" class="block w-full rounded-lg border-gray-300 mt-1"></textarea></label>
@if($task->status==='completed')<button name="status" value="reviewed" class="bg-green-600 text-white rounded-xl px-4 py-2 text-sm">Confirm reviewed</button><button name="status" value="in_progress" class="border rounded-xl px-4 py-2 text-sm">Return for follow-up</button>@endif
<button name="status" value="cancelled" class="border border-red-200 text-red-700 rounded-xl px-4 py-2 text-sm">Cancel recommendation</button>
@elseif($task->status==='assigned')<button name="status" value="in_progress" class="bg-green-600 text-white rounded-xl px-4 py-2 text-sm">Acknowledge and start</button>
@else<label class="block text-sm">Completion evidence<textarea name="completion_evidence" required maxlength="3000" class="block w-full rounded-lg border-gray-300 mt-1"></textarea></label><button name="status" value="completed" class="bg-green-600 text-white rounded-xl px-4 py-2 text-sm">Submit for Adviser review</button>@endif
</form>@endif
<x-supervision-history :record="$task" />
</section>
@empty<section class="bg-white rounded-2xl border border-gray-200 p-5 text-sm text-gray-500">No training recommendations yet.</section>@endforelse
{{ $tasks->links() }}
</div>
@endsection
