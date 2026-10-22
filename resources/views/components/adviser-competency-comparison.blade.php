@props(['comparison'])
<section class="ac-comparison ac-redesign" id="competency-comparison" aria-labelledby="ac-heading">
<header class="ac-header"><h2 id="ac-heading">Helper competency comparison</h2><a class="ac-link" href="{{ route('adviser.training') }}">Training recommendations</a></header>
<form class="ac-filters" action="{{ route('adviser.dashboard') }}#competency-comparison" method="GET">
@foreach($comparison['dates'] as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
<label>Skill<select name="competency_skill">@foreach($comparison['options'] as $key=>$label)<option value="{{ $key }}" @selected($comparison['skill']===$key)>{{ $label }}</option>@endforeach</select></label>
<label>Sort<select name="competency_sort">@foreach(['lowest'=>'Lowest score first','highest'=>'Highest score first','name'=>'Helper name'] as $key=>$label)<option value="{{ $key }}" @selected($comparison['sort']===$key)>{{ $label }}</option>@endforeach</select></label><button type="submit" class="ac-button">Apply</button>
</form>
@php($chartRows = $comparison['rows']->getCollection())
@if($chartRows->isNotEmpty())
<div class="ac-chart-scroll" tabindex="0" aria-label="{{ $comparison['options'][$comparison['skill']] }} comparison, scale zero to five">
<svg class="ac-chart" viewBox="0 0 820 {{ 42+$chartRows->count()*38 }}" role="img" aria-labelledby="ac-chart-title"><title id="ac-chart-title">{{ $comparison['options'][$comparison['skill']] }} by Helper, out of five</title>
@foreach(range(0,5) as $tick)<line x1="{{ 210+$tick*104 }}" x2="{{ 210+$tick*104 }}" y1="24" y2="{{ 30+$chartRows->count()*38 }}" stroke="#e5eee8"/><text x="{{ 210+$tick*104 }}" y="16" text-anchor="middle" fill="#68796e" font-size="11">{{ $tick }}</text>@endforeach
@foreach($chartRows as $row)@php($y=47+$loop->index*38)
<text x="0" y="{{ $y }}" fill="#344c3f" font-size="13">{{ \Illuminate\Support\Str::limit($row['helper']->full_name,26) }}</text>
@if($row['score']!==null)<rect x="210" y="{{ $y-13 }}" width="{{ $row['score']*104 }}" height="18" rx="4" fill="{{ $row['needs_attention'] ? '#c58532' : '#168355' }}"><title>{{ number_format($row['score'],2) }}/5</title></rect>@endif
<text x="800" y="{{ $y }}" text-anchor="end" fill="#173b2c" font-size="13" font-weight="600">{{ $row['score']===null ? '—' : number_format($row['score'],2).'/5' }}</text>
@endforeach</svg></div>
@endif
<div class="compact-table-wrap"><table class="compact-data-table"><thead><tr><th>Helper</th><th>Score /5</th><th>Evaluations</th><th>Skill needing attention</th><th><span class="sr-only">Action</span></th></tr></thead><tbody>
@forelse($comparison['rows'] as $row)
@php($helper=$row['helper'])
<tr><td><a class="ac-helper-name" href="{{ route('adviser.helper.show',$helper->id) }}">{{ $helper->full_name ?: 'Helper' }}</a></td><td>{{ $row['score']===null ? '—' : number_format($row['score'],2) }}</td><td>{{ $row['count'] }}</td><td>{{ $row['needs_attention'] && $row['weakest'] ? $comparison['options'][$row['weakest']] : '—' }}</td><td>
@if($row['count'])<section data-focus-panel data-focus-label="View evaluation" data-focus-title="{{ $helper->full_name }}">
<p><strong>Based on {{ $row['count'] }} {{ \Illuminate\Support\Str::plural('evaluation',$row['count']) }}</strong> · {{ $row['latest']->copy()->timezone('Asia/Manila')->format('M d, Y') }}</p>
@if($row['count']===1)<p>One evaluation: limited evidence.</p>@endif
@if($row['needs_attention'])<p>Training review suggested: at least one skill is below 2.5/5.</p>@endif
<dl class="ac-skills">@foreach(\App\Services\CompetencyRubric::CRITERIA as $key=>[$label,$weight])<div><dt>{{ $label }}</dt><dd>{{ number_format($row['scores'][$key],2) }}/5</dd></div>@endforeach</dl>
<p>Overall average: {{ number_format($row['overall'],2) }}/5</p>
<ul class="ac-evaluations">@foreach($row['evidence']->take(5) as $evaluation)<li><span>{{ $evaluation->evaluation_date->copy()->timezone('Asia/Manila')->format('M d, Y') }} · {{ number_format($evaluation->overall_score,2) }}/5</span><a class="ac-link" href="{{ route('adviser.evaluate',$evaluation->report_id) }}">View evaluation #{{ $evaluation->id }}</a></li>@endforeach</ul>
<div class="ac-actions">@if($row['has_training'])<a class="ac-button ac-button-outline" href="{{ route('adviser.training',['helper_id'=>$helper->id]) }}">Review existing training for {{ $comparison['options'][$row['criterion']] }}</a>@elseif($row['criterion'] && $row['scores'][$row['criterion']]<2.5)<a class="ac-button ac-button-outline" href="{{ route('adviser.training',['helper_id'=>$helper->id,'evaluation_id'=>$row['training_evaluation']->id,'criterion'=>$row['criterion']]) }}">Recommend training: {{ $comparison['options'][$row['criterion']] }}</a>@endif<a class="ac-link" href="{{ route('adviser.helper.show',$helper->id) }}">View Helper history</a></div>
</section>@else<span class="ac-muted">Not evaluated</span>@endif
</td></tr>
@empty<tr><td colspan="5">No supervised Helpers to compare yet.</td></tr>@endforelse
</tbody></table></div>
@if($comparison['rows']->total())<footer class="ac-pagination"><span>{{ $comparison['rows']->firstItem() }}–{{ $comparison['rows']->lastItem() }} of {{ $comparison['rows']->total() }} Helpers</span><nav aria-label="Competency comparison pages">@if($comparison['rows']->previousPageUrl())<a class="ac-button ac-button-outline" rel="prev" href="{{ $comparison['rows']->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $comparison['rows']->currentPage() }} of {{ $comparison['rows']->lastPage() }}</span>@if($comparison['rows']->nextPageUrl())<a class="ac-button ac-button-outline" rel="next" href="{{ $comparison['rows']->nextPageUrl() }}">Next</a>@endif</nav></footer>@endif
</section>
@once<link rel="stylesheet" href="{{ asset('css/adviser-competency.css') }}?v={{ filemtime(public_path('css/adviser-competency.css')) }}">@endonce
