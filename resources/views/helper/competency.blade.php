@extends('layouts.helper')
@section('title','Competency')
@section('heading','Competency')
@section('subheading','Your competency evaluations and growth over time.')
@section('header-actions')
<form method="GET" action="{{ route('helper.competency') }}" class="hf-filter hf-filter-top">
<x-helper-date-range :from="$range['from']" :to="$range['to']" />
<button class="btn btn-primary" type="submit">Apply</button>
</form>
@endsection
@section('content')
<div class="hf-page">
<div class="hf-score">
<section class="card hf-overall">
<h3>Overall Competency Score</h3>
@if($latest)
<div class="hf-score-value">{{ number_format($latest->normalized_score,1) }}</div>
<span class="hf-status">{{ $latest->level_label }}</span>
<p class="hf-muted">{{ $latest->evaluation_date?->copy()->timezone('Asia/Manila')->format('M d, Y') }} · {{ $latest->adviser?->full_name ?? 'Adviser' }}</p>
@else<div class="empty-state">
<h3>No evaluations yet</h3>
<p>Your score will appear when your Adviser evaluates a session.</p>
</div>
@endif</section>
<section class="card">
<header class="card-header">
<h3>Skill Breakdown</h3>
</header>
@if($latest)@foreach($latest->score_breakdown as $key=>$score)
@php
    $displayScore=$score>5 ? $score/20 : $score;
@endphp
<div class="hf-skill">
    <span>{{ ucwords(str_replace('_',' ',$key)) }}</span>
    @if($latest->getAttribute($key.'_score')!==null)
    <progress value="{{ max(0,min(5,$displayScore)) }}" max="5" aria-label="{{ ucwords(str_replace('_',' ',$key)) }}: {{ $displayScore }} on a 1–5 scale">
</progress>
    <strong>{{ number_format($displayScore,1) }}</strong>
    @else<span class="hf-muted">Not recorded</span>
@endif
</div>
@endforeach @else<div class="empty-state">Skill scores have not been recorded yet.</div>
@endif</section>
</div>
@php
    $trendChart = ['type' => 'area', 'scale' => [0, 5], 'unit' => '', 'labels' => $trend->pluck('label')->values()->all(),
        'datasets' => [['label' => 'Competency score (1–5)', 'data' => $trend->pluck('score')->map(fn ($v) => $v === null ? null : (float) $v)->values()->all()]]];
@endphp
<x-analytics-chart class="card" title="Score Trend" description="" :chart="$trendChart" />
@if(auth()->user()?->helper)<x-rating-explainer id="how-ratings-work" :helper="auth()->user()->helper" :from="$range['from']" :to="$range['to']" />@endif
<section class="card" id="evaluation-history">
<header class="card-header">
<h3>Recent Evaluations</h3>
@if($totalEvaluations > 0)<span class="hf-muted">{{ $totalEvaluations }} {{ \Illuminate\Support\Str::plural('evaluation', $totalEvaluations) }}</span>@endif
</header>
<div class="table-container">
<table>
<thead>
<tr>
<th>Date</th>
<th>Evaluator</th>
<th>Overall Score</th>
<th>Result</th>
<th>Action</th>
</tr>
</thead>
<tbody>
@forelse($history as $record)
<tr>
<td>{{ $record->evaluation_date?->copy()->timezone('Asia/Manila')->format('M d, Y') }}</td>
<td>{{ $record->adviser?->full_name ?? 'Adviser' }}</td>
<td>{{ number_format($record->normalized_score,1) }}</td>
<td>
<span class="hf-status">{{ $record->level_label }}</span>
</td>
<td>
<a class="btn btn-secondary btn-sm" href="{{ route('helper.competency.view',$record->id) }}">View</a>
</td>
</tr>
@empty<tr>
<td colspan="5" class="empty-state">No evaluations match this date range.</td>
</tr>
@endforelse</tbody>
</table>
</div>
{{ $history->links('vendor.pagination.compass', ['noun' => 'evaluations']) }}</section>
</div>
@endsection
