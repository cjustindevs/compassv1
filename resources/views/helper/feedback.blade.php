@extends('layouts.helper')
@section('title','Feedback')
@section('heading','Feedback')
@section('subheading','Adviser and help-seeker evaluations from your peer-support sessions.')
@section('content')
<div class="hf-page">
<form method="GET" action="{{ route('helper.feedback') }}" class="hf-filter hf-filter-top">
<x-helper-date-range :from="$range['from']" :to="$range['to']" />
<input type="hidden" name="adviser_search" value="{{ request('adviser_search') }}">
<input type="hidden" name="seeker_search" value="{{ request('seeker_search') }}">
<button class="btn btn-secondary">Apply</button>
<a class="btn btn-secondary" href="{{ route('helper.feedback') }}">Reset</a>
</form>
<section class="card">
<header class="card-header">
<h3>Adviser Feedback</h3>
<form method="GET" action="{{ route('helper.feedback') }}" class="hf-actions">
<input type="hidden" name="date_range" value="{{ $range['from'] }} to {{ $range['to'] }}">
<input type="hidden" name="seeker_search" value="{{ request('seeker_search') }}">
<input class="form-control" style="width:220px;max-width:100%" name="adviser_search" value="{{ request('adviser_search') }}" placeholder="Search adviser feedback..." maxlength="100" aria-label="Search adviser feedback">
<button class="btn btn-secondary btn-sm">Search</button>
</form>
</header>
<div class="table-container">
<table>
<thead>
<tr>
<th>Date</th>
<th>Session</th>
<th>Rating</th>
<th>Feedback</th>
<th>Level</th>
<th>Action</th>
</tr>
</thead>
<tbody>
@forelse($adviserFeedback as $feedback)
<tr>
<td>{{ ($feedback->created_date ?? $feedback->created_at)?->copy()->timezone('Asia/Manila')->format('M d, Y') }}</td>
<td>{{ $feedback->report?->session?->reference_number ?? 'Unrecorded' }}</td>
<td>{{ $feedback->competency_rating!==null ? number_format($feedback->competency_rating > 5 ? $feedback->competency_rating/20 : $feedback->competency_rating,1).' / 5' : 'Not recorded' }}</td>
<td>{{ \Illuminate\Support\Str::limit($feedback->feedback_text ?: 'No written feedback',150) }}</td>
<td>
<span class="hf-status">{{ ucwords(str_replace('_',' ',$feedback->competency_level ?? 'Not recorded')) }}</span>
</td>
<td>
<a class="btn btn-secondary btn-sm" href="{{ route('helper.feedback.view',$feedback->id) }}">View</a>
</td>
</tr>
@empty<tr>
<td colspan="6" class="empty-state">No Adviser feedback matches these filters.</td>
</tr>
@endforelse
</tbody>
</table>
</div>
<div class="hf-record-count">Showing {{ $adviserFeedback->firstItem() ?? 0 }}-{{ $adviserFeedback->lastItem() ?? 0 }} of {{ $adviserFeedback->total() }} records</div>{{ $adviserFeedback->links() }}</section>
<section class="card">
<header class="card-header">
<h3>Help-Seeker Feedback</h3>
<form method="GET" action="{{ route('helper.feedback') }}" class="hf-actions">
<input type="hidden" name="date_range" value="{{ $range['from'] }} to {{ $range['to'] }}">
<input type="hidden" name="adviser_search" value="{{ request('adviser_search') }}">
<input class="form-control" style="width:220px;max-width:100%" name="seeker_search" value="{{ request('seeker_search') }}" placeholder="Search seeker feedback..." maxlength="100" aria-label="Search seeker feedback">
<button class="btn btn-secondary btn-sm">Search</button>
</form>
</header>
<div class="table-container">
<table>
<thead>
<tr>
<th>Date</th>
<th>Session</th>
<th>Rating</th>
<th>Feedback</th>
<th>Action</th>
</tr>
</thead>
<tbody>
@forelse($seekerFeedback as $evaluation)
<tr>
<td>{{ ($evaluation->submitted_at ?? $evaluation->created_at)?->copy()->timezone('Asia/Manila')->format('M d, Y') }}</td>
<td>{{ $evaluation->session?->reference_number ?? 'Unrecorded' }}</td>
<td>{{ $evaluation->overall_score!==null ? number_format($evaluation->overall_score,1).' / 10' : 'Not recorded' }}</td>
<td>{{ \Illuminate\Support\Str::limit($evaluation->comments ?: 'No written comments',150) }}</td>
<td>
<details>
<summary class="link" style="cursor:pointer">View</summary>
<p class="hf-muted" style="min-width:180px;margin-top:10px">{{ $evaluation->comments ?: 'No written comments' }}</p>
</details>
</td>
</tr>
@empty<tr>
<td colspan="5" class="empty-state">No help-seeker feedback matches these filters.</td>
</tr>
@endforelse
</tbody>
</table>
</div>
<div class="hf-record-count">Showing {{ $seekerFeedback->firstItem() ?? 0 }}-{{ $seekerFeedback->lastItem() ?? 0 }} of {{ $seekerFeedback->total() }} records</div>{{ $seekerFeedback->links() }}</section>
</div>
@endsection
