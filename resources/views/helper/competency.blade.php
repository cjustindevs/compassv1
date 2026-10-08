@extends('layouts.helper')
@section('title','Competency')
@section('heading','Competency')
@section('subheading','Your competency evaluations and growth over time.')
@section('content')
<div class="hf-page">
<form method="GET" action="{{ route('helper.competency') }}" class="hf-filter hf-filter-top">
<x-helper-date-range :from="$range['from']" :to="$range['to']" />
<button class="btn btn-secondary" type="submit">Apply</button>
</form>
<div class="hf-score">
<section class="card hf-overall">
<h3>Overall Competency Score</h3>
@if($latest)
<div class="hf-score-value">{{ number_format($latest->normalized_score,1) }}<small> / 5</small>
</div>
<span class="hf-status">{{ $latest->level_label }}</span>
<p class="hf-muted">Current score from your latest recorded evaluation<br>{{ $latest->evaluation_date?->copy()->timezone('Asia/Manila')->format('M d, Y') }}<br>Evaluator: {{ $latest->adviser?->full_name ?? 'Adviser' }}</p>
@else<div class="empty-state">
<h3>No evaluations yet</h3>
<p>Your score will appear when your Adviser evaluates a session.</p>
</div>
@endif</section>
<section class="card">
<header class="card-header">
<h3>Skill Breakdown</h3>
<span class="hf-muted">Latest evaluation</span>
</header>
@if($latest)@foreach($latest->score_breakdown as $key=>$score)
@php
    $displayScore=$score>5 ? $score/20 : $score;
@endphp
<div class="hf-skill">
    <span>{{ ucwords(str_replace('_',' ',$key)) }}</span>
    @if($latest->getAttribute($key.'_score')!==null)
    <progress value="{{ max(0,min(5,$displayScore)) }}" max="5" aria-label="{{ ucwords(str_replace('_',' ',$key)) }}: {{ $displayScore }} out of 5">
</progress>
    <strong>{{ number_format($displayScore,1) }} / 5</strong>
    @else<span class="hf-muted">Not recorded</span>
@endif
</div>
@endforeach @else<div class="empty-state">Skill scores have not been recorded yet.</div>
@endif</section>
</div>
<section class="card">
<header class="card-header">
<h3>Score Trend</h3>
<span class="hf-muted">Latest six evaluations in the selected range</span>
</header>
@if($trend->isNotEmpty())
@php
$points=[];
foreach($trend as $i=>$point) $points[]=[60+$i*(720/max($trend->count()-1,1)),220-min(5,max(0,$point['score']))/5*180];
$line=implode(' ',array_map(fn($p)=>implode(',',$p),$points));
@endphp
<div class="table-container">
<svg class="hf-chart" style="min-width:540px" viewBox="0 0 850 270" role="img" aria-label="Recorded competency scores out of five, by evaluation date">
@foreach(range(0,5) as $tick)@php($y=220-$tick*36)
<line x1="60" x2="780" y1="{{ $y }}" y2="{{ $y }}" stroke="#edf1ee"/>
<text x="40" y="{{ $y+4 }}">{{ $tick }}</text>
@endforeach
<polygon points="{{ $points[0][0] }},220 {{ $line }} {{ $points[count($points)-1][0] }},220" fill="#009b50" fill-opacity=".09"/>
<polyline points="{{ $line }}" fill="none" stroke="#009b50" stroke-width="3"/>
@foreach($points as $i=>$point)
<circle cx="{{ $point[0] }}" cy="{{ $point[1] }}" r="5" fill="#009b50">
<title>{{ $trend[$i]['label'] }}: {{ $trend[$i]['score'] }} / 5</title>
</circle>
<text x="{{ $point[0] }}" y="{{ $point[1]-14 }}" text-anchor="middle">{{ $trend[$i]['score'] }}</text>
<text x="{{ $point[0] }}" y="248" text-anchor="middle">{{ $trend[$i]['label'] }}</text>
@endforeach
</svg>
</div>
@else<div class="empty-state">No evaluations were recorded in this date range.</div>
@endif</section>
<section class="card" id="evaluation-history">
<header class="card-header">
<h3>Recent Evaluations</h3>
<span class="hf-muted">{{ $totalEvaluations }} evaluation(s) in this range</span>
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
<td>{{ number_format($record->normalized_score,1) }} / 5</td>
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
<div class="hf-record-count">Showing {{ $history->firstItem() ?? 0 }}-{{ $history->lastItem() ?? 0 }} of {{ $history->total() }} evaluations</div>{{ $history->links() }}</section>
</div>
@endsection
