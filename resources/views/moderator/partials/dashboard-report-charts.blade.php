{{-- Case overview, Helper workload and competency charts (moved here from Moderator Reports; QA: Reports are tables). --}}
<x-ui.card title="Case overview" subtitle="Actual records in the selected Philippine Time date range. Each record is counted once.">
    <div class="analytics-chart-grid report-chart-grid">
    @foreach(['status','categories','emergencies','referrals'] as $key)
        @if(isset($caseCharts[$key]))
            @include('moderator.partials.distribution',['values'=>$caseCharts[$key]['values'],'chartTitle'=>$caseCharts[$key]['title']])
        @endif
    @endforeach
    </div>
</x-ui.card>
<x-ui.card title="Helpers and competency">
    <div class="analytics-chart-grid report-chart-grid">
        <x-analytics-chart title="Current Helper workload" description="Active and assigned sessions per Helper right now; independent of the date filter." :chart="['type'=>'bar','labels'=>collect($workload)->pluck('helper')->all(),'datasets'=>[['label'=>'Open sessions','data'=>collect($workload)->pluck('count')->all()]]]" />
        {{-- QA Nov 24: a sorted bar chart (weakest skill first) reads more easily than a radar. --}}
        @php
            $skillScores = collect($radar['labels'])->values()->map(fn ($label, $i) => ['label' => $label, 'value' => $radar['values'][$i] ?? null])
                ->filter(fn ($row) => $row['value'] !== null)->sortBy('value')->values();
        @endphp
        <x-analytics-chart title="Competency by skill" description="Average of current-rubric evaluations in the selected period, on a 1–5 scale. Lowest first." :chart="['type'=>'bar','scale'=>[0,5],'unit'=>'','labels'=>$skillScores->pluck('label')->all(),'datasets'=>[['label'=>'Average score (1–5)','data'=>$skillScores->pluck('value')->all()]]]" />
    </div>
</x-ui.card>
