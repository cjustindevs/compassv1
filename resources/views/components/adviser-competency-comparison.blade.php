@props(['comparison'])
<section class="ac-comparison ac-redesign" id="competency-comparison" aria-labelledby="ac-heading">
<header class="ac-header"><h2 id="ac-heading">Helper competency comparison</h2><a class="ac-link" href="{{ route('adviser.training') }}">Training recommendations</a></header>
<form class="ac-filters" action="{{ route('adviser.dashboard') }}#competency-comparison" method="GET">
@foreach($comparison['dates'] as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
<label>Skill<select name="competency_skill">@foreach($comparison['options'] as $key=>$label)<option value="{{ $key }}" @selected($comparison['skill']===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Sort<select name="competency_sort">@foreach(['lowest'=>'Lowest score first','highest'=>'Highest score first','name'=>'Helper name'] as $key=>$label)<option value="{{ $key }}" @selected($comparison['sort']===$key)>{{ $label }}</option>@endforeach</select></label><button type="submit" class="ac-button">Apply</button>
</form>
<?php $chartRows = $comparison['rows']->getCollection(); ?>
@if($chartRows->contains(fn ($row) => $row['score'] !== null))
@php
    $skillLabel = $comparison['options'][$comparison['skill']];
    $comparisonChart = [
        'type' => 'bar', 'scale' => [0, 5], 'unit' => '',
        'labels' => $chartRows->map(fn ($row) => \Illuminate\Support\Str::limit($row['helper']->full_name ?: 'Helper', 26))->values()->all(),
        'samples' => $chartRows->map(fn ($row) => $row['count'])->values()->all(),
        'datasets' => [[
            'label' => $skillLabel.' (1–5)',
            'data' => $chartRows->map(fn ($row) => $row['score'] === null ? null : round($row['score'], 2))->values()->all(),
            'colors' => $chartRows->map(fn ($row) => $row['needs_attention'] ? '#c58532' : '#078749')->values()->all(),
        ]],
    ];
@endphp
<x-analytics-chart class="ac-chart-card" :title="$skillLabel.' by Helper'" description="Average score on a 1–5 scale. Amber bars mark a skill below 2.5." :chart="$comparisonChart" />
@endif
<div class="compact-table-wrap"><table class="compact-data-table"><thead><tr><th>Helper</th><th>Score (1–5)</th><th>Evaluations</th><th>Skill needing attention</th><th><span class="sr-only">Action</span></th></tr></thead><tbody>
@forelse($comparison['rows'] as $row)
<?php $helper=$row['helper']; ?>
<tr><td><a class="ac-helper-name" href="{{ route('adviser.helper.show',$helper->id) }}">{{ $helper->full_name ?: 'Helper' }}</a></td><td>{{ $row['score']===null ? '—' : number_format($row['score'],2) }}</td><td>{{ $row['count'] }}</td><td>{{ $row['needs_attention'] && $row['weakest'] ? $comparison['options'][$row['weakest']] : '—' }}</td><td>
@if($row['count'])<section data-focus-panel data-focus-label="View evaluation" data-focus-title="{{ $helper->full_name }}">
<p><strong>Based on {{ $row['count'] }} {{ \Illuminate\Support\Str::plural('evaluation',$row['count']) }}</strong> · {{ $row['latest']->copy()->timezone('Asia/Manila')->format('M d, Y') }}</p>
@if($row['count']===1)<p>One evaluation: limited evidence.</p>@endif
@if($row['needs_attention'])<p>Training review suggested: at least one skill is below 2.5.</p>@endif
<dl class="ac-skills">@foreach(\App\Services\CompetencyRubric::CRITERIA as $key=>[$label,$weight])<div><dt>{{ $label }}</dt><dd>{{ number_format($row['scores'][$key],2) }}</dd></div>@endforeach</dl>
<p>Overall average: {{ number_format($row['overall'],2) }} (scale 1–5)</p>
<ul class="ac-evaluations">@foreach($row['evidence']->take(5) as $evaluation)<li><span>{{ $evaluation->evaluation_date->copy()->timezone('Asia/Manila')->format('M d, Y') }} · {{ number_format($evaluation->overall_score,2) }}</span><a class="ac-link" href="{{ route('adviser.evaluate',$evaluation->report_id) }}">View evaluation #{{ $evaluation->id }}</a></li>@endforeach</ul>
<div class="ac-actions">@if($row['has_training'])<a class="ac-button ac-button-outline" href="{{ route('adviser.training',['helper_id'=>$helper->id]) }}">Review existing training for {{ $comparison['options'][$row['criterion']] }}</a>@elseif($row['criterion'] && $row['scores'][$row['criterion']]<2.5)<a class="ac-button ac-button-outline" href="{{ route('adviser.training',['helper_id'=>$helper->id,'evaluation_id'=>$row['training_evaluation']->id,'criterion'=>$row['criterion']]) }}">Recommend training: {{ $comparison['options'][$row['criterion']] }}</a>@endif<a class="ac-link" href="{{ route('adviser.helper.show',$helper->id) }}">View Helper history</a></div>
</section>@else<span class="ac-muted">Not evaluated</span>@endif
</td></tr>
@empty<tr><td colspan="5">No supervised Helpers to compare yet.</td></tr>@endforelse
</tbody></table></div>
@if($comparison['rows']->total())<footer class="ac-pagination"><span>{{ $comparison['rows']->firstItem() }}–{{ $comparison['rows']->lastItem() }} of {{ $comparison['rows']->total() }} Helpers</span><nav aria-label="Competency comparison pages">@if($comparison['rows']->previousPageUrl())<a class="ac-button ac-button-outline" rel="prev" href="{{ $comparison['rows']->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $comparison['rows']->currentPage() }} of {{ $comparison['rows']->lastPage() }}</span>@if($comparison['rows']->nextPageUrl())<a class="ac-button ac-button-outline" rel="next" href="{{ $comparison['rows']->nextPageUrl() }}">Next</a>@endif</nav></footer>@endif
</section>
@once<link rel="stylesheet" href="{{ asset('css/adviser-competency.css') }}?v={{ filemtime(public_path('css/adviser-competency.css')) }}">@endonce
