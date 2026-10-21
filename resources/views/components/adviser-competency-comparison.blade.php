@props(['comparison'])
<section class="ac-comparison" id="competency-comparison" aria-labelledby="ac-heading">
    <header class="ac-header">
        <div><h2 id="ac-heading">Helper competency comparison</h2><p>Find skill gaps and plan training for your supervised Helpers.</p></div>
        <a class="ac-link" href="{{ route('adviser.training') }}">Training recommendations</a>
    </header>
    <div class="ac-summary" aria-label="Competency coverage in the selected period">
        <span><strong>{{ $comparison['evaluated'] }}</strong> evaluated</span>
        <span><strong>{{ $comparison['needs_attention'] }}</strong> with a skill below 2.5</span>
        <span><strong>{{ $comparison['not_evaluated'] }}</strong> not evaluated</span>
    </div>
    <form class="ac-filters" action="{{ route('adviser.dashboard') }}#competency-comparison" method="GET">
        @foreach($comparison['dates'] as $key => $value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
        <label>Compare<select name="competency_skill">@foreach($comparison['options'] as $key => $label)<option value="{{ $key }}" @selected($comparison['skill'] === $key)>{{ $label }}</option>@endforeach</select></label>
        <label>Sort<select name="competency_sort">@foreach(['lowest' => 'Lowest score first', 'highest' => 'Highest score first', 'name' => 'Helper name'] as $key => $label)<option value="{{ $key }}" @selected($comparison['sort'] === $key)>{{ $label }}</option>@endforeach</select></label>
        <button type="submit" class="ac-button">Apply comparison</button>
    </form>
    <p class="ac-caption">{{ $comparison['options'][$comparison['skill']] }} · Average rating / 5 · Current rubric · {{ $comparison['dates']['from'] }} to {{ $comparison['dates']['to'] }} (PHT)</p>
    <div class="ac-axis" aria-hidden="true"><span></span><div>@foreach(range(0, 5) as $tick)<span>{{ $tick }}</span>@endforeach</div><span>/ 5</span></div>
    <div class="ac-rows" role="list" aria-label="{{ $comparison['options'][$comparison['skill']] }} by Helper">
        @forelse($comparison['rows'] as $row)
            @php
                $helper = $row['helper'];
                $level = $row['score'] === null ? 'Not evaluated' : \App\Services\CompetencyRubric::level($row['score']);
                $tone = $row['score'] === null ? 'empty' : ($row['score'] < 2.5 ? 'attention' : ($row['score'] < 3.5 ? 'developing' : 'strong'));
            @endphp
            <article class="ac-row ac-{{ $tone }}" role="listitem">
                <div class="ac-bar-row">
                    <div class="ac-helper"><a href="{{ route('adviser.helper.show', $helper->id) }}">{{ $helper->full_name ?: 'Helper' }}</a><small>n={{ $row['count'] }} evaluations</small></div>
                    <div class="ac-track" role="img" aria-label="{{ $helper->full_name }}: {{ $row['score'] === null ? 'Not evaluated' : number_format($row['score'], 2).' out of 5, '.$level }}">@if($row['score'] !== null)<span style="width:{{ $row['score'] * 20 }}%"></span>@endif</div>
                    <div class="ac-rating"><strong>{{ $row['score'] === null ? '—' : number_format($row['score'], 2).'/5' }}</strong><span>{{ $level }}</span></div>
                </div>
                @if($row['count'])
                    <details class="ac-detail"><summary>View details</summary>                    <div class="ac-row-meta"><span>Based on {{ $row['count'] }} {{ \Illuminate\Support\Str::plural('evaluation', $row['count']) }} · Latest {{ $row['latest']->copy()->timezone('Asia/Manila')->format('M d, Y') }}</span><span>Lowest skill: <strong>{{ $comparison['options'][$row['weakest']] }}</strong> ({{ number_format($row['scores'][$row['weakest']], 2) }}/5)</span></div>
                    @if($row['needs_attention'])<p class="ac-attention-label">Training review suggested: at least one skill is below 2.5/5.</p>@endif

                        <dl class="ac-skills">@foreach(\App\Services\CompetencyRubric::CRITERIA as $key => [$label, $weight])<div><dt>{{ $label }} <small>{{ $weight }}%</small></dt><dd>{{ number_format($row['scores'][$key], 2) }}/5 @if($row['scores'][$key] < 2.5)<span>Needs attention</span>@endif</dd></div>@endforeach</dl>
                        <p class="ac-caption">Overall average: {{ number_format($row['overall'], 2) }}/5. Showing the five most recent evaluations in this period.</p>
                        <ul class="ac-evaluations">@foreach($row['evidence']->take(5) as $evaluation)<li><span>{{ $evaluation->evaluation_date->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT · {{ number_format($evaluation->overall_score, 2) }}/5</span><a class="ac-link" href="{{ route('adviser.evaluate', $evaluation->report_id) }}">View evaluation #{{ $evaluation->id }}</a></li>@endforeach</ul>
                        <a class="ac-link" href="{{ route('adviser.helper.show', $helper->id) }}">View all competency history</a>
                    <div class="ac-actions">
                        @if($row['has_training'])
                            <a class="ac-link" href="{{ route('adviser.training', ['helper_id' => $helper->id]) }}">Review existing training for {{ $comparison['options'][$row['criterion']] }}</a>
                        @elseif($row['criterion'] && $row['scores'][$row['criterion']] < 2.5)
                            <a class="ac-button ac-button-outline" href="{{ route('adviser.training', ['helper_id' => $helper->id, 'evaluation_id' => $row['training_evaluation']->id, 'criterion' => $row['criterion']]) }}">Recommend training: {{ $comparison['options'][$row['criterion']] }}</a>
                        @endif
                        @if($row['training']->isNotEmpty())<span class="ac-caption">{{ $row['training']->count() }} training {{ \Illuminate\Support\Str::plural('activity', $row['training']->count()) }} ongoing or awaiting Adviser review.</span>@endif
                    </div>
                    @if($row['count'] === 1)<p class="ac-caption">One evaluation: limited evidence.</p>@endif
                    </details>
                @else
                    <p class="ac-row-meta">No valid, finalized evaluation using the current rubric in this period.</p>
                    <a class="ac-link" href="{{ route('adviser.helper.show', $helper->id) }}">View Helper history</a>
                @endif
            </article>
        @empty
            <div class="ac-empty">No supervised Helpers to compare yet.</div>
        @endforelse
    </div>
    @if($comparison['rows']->total())<footer class="ac-pagination"><span>Showing {{ $comparison['rows']->firstItem() }}–{{ $comparison['rows']->lastItem() }} of {{ $comparison['rows']->total() }} Helpers</span><nav aria-label="Competency comparison pages">@if($comparison['rows']->previousPageUrl())<a class="ac-button ac-button-outline" rel="prev" href="{{ $comparison['rows']->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $comparison['rows']->currentPage() }} of {{ $comparison['rows']->lastPage() }}</span>@if($comparison['rows']->nextPageUrl())<a class="ac-button ac-button-outline" rel="next" href="{{ $comparison['rows']->nextPageUrl() }}">Next</a>@endif</nav></footer>@endif
    <details class="ac-definition"><summary>How scores and training indicators work</summary><p>Each session evaluation uses Active Listening 25%, Empathy 25%, Respect and Professionalism 20%, Ethical Practices 20%, and Referral Judgment 10%. Bars show the mean of finalized evaluations in the selected Philippine dates. Corrections replace earlier results; historical correction snapshots are not counted again.</p><p>Ratings below 2.5 are Needs Improvement or Unsatisfactory under the existing rubric. Training indicators support Adviser judgment. One evaluation is limited evidence; a high overall score can still contain a weaker skill. A missing evaluation is not a zero.</p><p>{{ $comparison['legacy_count'] }} legacy or other-rubric evaluations in this period are excluded from this comparison and remain available in Helper history. Incomplete or inconsistent evaluations are also excluded. The comparison and competency KPI use the same finalized current-rubric evidence; legacy history remains separate.</p></details>
</section>
@once<link rel="stylesheet" href="{{ asset('css/adviser-competency.css') }}?v={{ filemtime(public_path('css/adviser-competency.css')) }}">@endonce
