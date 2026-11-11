@props(['groups' => [], 'charts' => [], 'heading' => 'Charts', 'caption' => null])
{{--
    Report chart grid. $groups is the RoleActivityReport shape ([title => [label => count]]);
    the chart type follows the data's job: trends -> area, small status/priority mixes -> donut,
    longer category lists -> horizontal bars. $charts adds ready-made x-analytics-chart specs
    ([title, description?, chart, definition?]) after the groups.
--}}
@php
    $pretty = fn ($label) => $label === null || $label === '' ? 'Unrecorded' : ucwords(str_replace('_', ' ', (string) $label));
    $cards = [];
    foreach ($groups as $title => $values) {
        $lower = strtolower($title);
        $isTrend = str_contains($lower, 'trend');
        if ($isTrend && $values && collect(array_keys($values))->every(fn ($k) => preg_match('/^\d{4}-\d{2}$/', (string) $k))) {
            // Monthly counts: a month with no records is a true zero, so fill gaps between the first and last month.
            ksort($values);
            $filled = [];
            for ($m = \Carbon\Carbon::createFromFormat('!Y-m', array_key_first($values)), $last = \Carbon\Carbon::createFromFormat('!Y-m', array_key_last($values)); $m->lte($last); $m->addMonth()) {
                $filled[$m->format('Y-m')] = (int) ($values[$m->format('Y-m')] ?? 0);
            }
            $values = $filled;
        }
        if ($isTrend) {
            $labels = array_map(function ($key) {
                return preg_match('/^\d{4}-\d{2}$/', (string) $key) ? \Carbon\Carbon::createFromFormat('Y-m', $key)->format('M Y') : (string) $key;
            }, array_keys($values));
        } else {
            arsort($values);
            $labels = array_map($pretty, array_keys($values));
        }
        $small = count($values) <= 5 && (str_contains($lower, 'status') || str_contains($lower, 'priority') || str_contains($lower, 'role') || str_contains($lower, 'risk'));
        $cards[] = [
            'title' => $title,
            'description' => $isTrend ? 'Per period · Philippine Time' : 'Records in the selected period',
            'chart' => ['type' => $isTrend ? 'area' : ($small ? 'donut' : 'bar'), 'labels' => $labels, 'datasets' => [['label' => 'Records', 'data' => array_values($values)]]],
        ];
    }
    foreach ($charts as $chart) $cards[] = $chart;
@endphp
@if(count($cards))
<section class="report-charts" aria-label="{{ $heading }}">
    <header class="report-charts-heading"><h2>{{ $heading }}</h2>@if($caption)<p>{{ $caption }}</p>@endif</header>
    <div class="analytics-chart-grid">
        @foreach($cards as $card)
            <x-analytics-chart :title="$card['title']" :description="$card['description'] ?? null" :chart="$card['chart']" :definition="$card['definition'] ?? null" />
        @endforeach
    </div>
</section>
@once
<style>
.report-charts{margin:16px 0 20px;min-width:0}
.report-charts-heading{display:flex;align-items:baseline;justify-content:space-between;gap:8px 16px;flex-wrap:wrap;margin-bottom:12px}
.report-charts-heading h2{margin:0;font-size:16px;font-weight:700;color:var(--compass-text,#163b2d)}
.report-charts-heading p{margin:0;font-size:13px;color:var(--compass-muted,#64748b)}
@media (max-width:1150px){.report-charts .analytics-chart-grid{grid-template-columns:minmax(0,1fr)}}
</style>
@endonce
@endif
