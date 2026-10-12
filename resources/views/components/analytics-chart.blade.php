@props(['id', 'title', 'description', 'chart', 'link' => null])
@php
    $hasData = collect($chart['datasets'])->contains(fn ($dataset) => array_sum($dataset['data']) > 0);
@endphp
<section class="analytics-chart-card" data-analytics-chart aria-labelledby="{{ $id }}-title">
    <header class="analytics-card-heading">
        <div><h3 id="{{ $id }}-title">{{ $title }}</h3><p>{{ $description }}</p></div>
        @if($link)<a href="{{ $link }}" class="analytics-button">View records</a>@endif
    </header>
    <p class="analytics-chart-summary">@foreach($chart['datasets'] as $dataset)<span>{{ number_format(array_sum($dataset['data'])) }} {{ strtolower($dataset['label']) }}</span>@endforeach</p>
    @if($hasData)
        <div class="analytics-chart-frame" data-chart-frame hidden><canvas id="{{ $id }}" role="img" aria-label="{{ $title }}. Exact values are available in the data table below."></canvas></div>
        <p class="analytics-chart-state" data-chart-state role="status">Loading chart. Exact values are available below.</p>
        <script type="application/json" data-chart-data>@json($chart)</script>
    @else
        <div class="analytics-chart-empty"><strong>No activity to display</strong><p>No matching records were found. Try a different date range.</p></div>
    @endif
    <details class="analytics-chart-data"><summary>View exact values</summary><div class="analytics-table-scroll" tabindex="0" role="region" aria-label="{{ $title }} values"><table><thead><tr><th scope="col">{{ $chart['type'] === 'bar' ? 'Category' : 'Period' }}</th>@foreach($chart['datasets'] as $dataset)<th scope="col">{{ $dataset['label'] }}</th>@endforeach</tr></thead><tbody>@foreach($chart['labels'] as $index=>$label)<tr><th scope="row">{{ $label }}</th>@foreach($chart['datasets'] as $dataset)<td>{{ number_format($dataset['data'][$index] ?? 0) }}</td>@endforeach</tr>@endforeach</tbody></table></div></details>
</section>
