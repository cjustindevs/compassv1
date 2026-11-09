@props(['id' => null, 'title', 'description' => null, 'chart', 'link' => null, 'linkLabel' => 'View records', 'caption' => null, 'definition' => null])
{{--
    Single chart entry point (Chart.js, themed in resources/js/compass-charts.js).
    $chart: type (line|area|bar|column|stacked|donut|gauge|radar), labels, datasets[{label,data}],
            optional unit (' min', '%', ''), scale [min,max], samples [n], max (gauge), caption.
    No data shows an empty state instead of drawing zeros; an exact-values table is always rendered.
--}}
@php
    $id = $id ?: 'chart-'.\Illuminate\Support\Str::slug($title).'-'.substr(md5(json_encode($chart)), 0, 6);
    $type = $chart['type'] ?? 'line';
    $unit = $chart['unit'] ?? '';
    $measured = ! empty($chart['scale']) || $unit !== '' || $type === 'gauge';
    $values = collect($chart['datasets'])->flatMap(fn ($d) => $d['data'])->filter(fn ($v) => $v !== null);
    $hasData = $values->isNotEmpty() && ($measured || $values->sum() > 0);
    $fmt = function ($v) use ($unit) {
        if ($v === null) return '—';
        $n = (float) $v;
        $text = floor($n) == $n ? number_format($n) : number_format($n, ($unit === '%' || abs($n) < 10) ? 1 : 0);
        return $text.($unit === '%' ? '%' : $unit);
    };
    $firstColumn = in_array($type, ['line', 'area', 'stacked'], true) ? 'Period' : 'Category';
    $height = match ($type) { 'gauge' => 150, 'donut' => 220, 'radar' => 280, 'bar' => max(160, min(420, 36 * count($chart['labels']) + 40)), default => 240 };
@endphp
<section {{ $attributes->merge(['class' => 'analytics-chart-card']) }} data-analytics-chart data-chart-type="{{ $type }}" aria-labelledby="{{ $id }}-title">
    <header class="analytics-card-heading">
        <div><h3 id="{{ $id }}-title">{{ $title }}</h3>@if($description)<p>{{ $description }}</p>@endif</div>
        @if($link)<a href="{{ $link }}" class="analytics-button">{{ $linkLabel }}</a>@endif
    </header>
    @if($hasData && ! $measured && ! in_array($type, ['donut', 'radar'], true))
        <p class="analytics-chart-summary">@foreach($chart['datasets'] as $dataset)<span>{{ number_format(array_sum(array_map('floatval', array_filter($dataset['data'], fn ($v) => $v !== null)))) }} {{ strtolower($dataset['label']) }}</span>@endforeach</p>
    @elseif($caption)
        <p class="analytics-chart-summary analytics-chart-caption">{{ $caption }}</p>
    @endif
    @if($hasData)
        <div class="analytics-chart-frame" data-chart-frame style="height:{{ $height }}px" hidden><canvas id="{{ $id }}" role="img" aria-label="{{ $title }}. Exact values are in the table below."></canvas></div>
        <p class="analytics-chart-state" data-chart-state role="status">Loading chart…</p>
        <script type="application/json" data-chart-data>@json($chart)</script>
    @else
        <div class="analytics-chart-empty"><strong>No data for this period</strong><p>{{ $definition ?: 'Nothing was recorded for the selected filters.' }}</p></div>
    @endif
    @if($hasData)
    <details class="analytics-chart-data"><summary>View exact values</summary>@if($definition)<p class="analytics-chart-definition">{{ $definition }}</p>@endif<div class="analytics-table-scroll" tabindex="0" role="region" aria-label="{{ $title }} values"><table><thead><tr><th scope="col">{{ $firstColumn }}</th>@foreach($chart['datasets'] as $dataset)<th scope="col">{{ $dataset['label'] }}</th>@endforeach @if(! empty($chart['samples']))<th scope="col">Records measured</th>@endif</tr></thead><tbody>@foreach($chart['labels'] as $index => $label)<tr><th scope="row">{{ $label }}</th>@foreach($chart['datasets'] as $dataset)<td>{{ $fmt($dataset['data'][$index] ?? null) }}</td>@endforeach @if(! empty($chart['samples']))<td>{{ $chart['samples'][$index] ?? '—' }}</td>@endif</tr>@endforeach</tbody></table></div></details>
    @endif
</section>
@once
    @vite('resources/js/compass-charts.js')
@endonce
