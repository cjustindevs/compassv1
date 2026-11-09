@props(['chart', 'smooth' => false])
{{-- Legacy dashboard chart shape ({title, values, definition, unit?}) mapped onto x-analytics-chart. --}}
@php
    $title = $chart['title'];
    $lower = strtolower($title);
    $values = $chart['values'];
    $isTrend = str_contains($lower, 'trend');
    $isScore = in_array($chart['unit'] ?? '', ['Rating / 5', 'Scale 1–5'], true) || str_contains($lower, 'competency');
    $isDistribution = str_contains($lower, 'status') || str_contains($lower, 'role') || str_contains($lower, 'severity') || str_contains($lower, 'risk');
    $type = match (true) { $isTrend => 'area', $isDistribution => 'donut', default => 'bar' };
    $series = $isScore ? 'Average score (1–5)' : 'Records';
    $caption = match (true) {
        $isTrend && $isScore => 'Monthly average · Scale 1–5',
        $isTrend => 'Selected period · Philippine Time',
        $isScore => 'Average score · Scale 1–5',
        str_contains($lower, 'categor') => 'Selected period',
        default => 'Current recorded status',
    };
    $spec = [
        'type' => $type,
        'labels' => array_map('strval', array_keys($values)),
        'datasets' => [['label' => $series, 'data' => array_values($values)]],
        'unit' => '',
    ];
    if ($isScore) $spec['scale'] = $type === 'bar' ? [0, 5] : [1, 5];
@endphp
<x-analytics-chart class="co-chart {{ $isTrend ? 'co-trend' : '' }}" :title="$title" :description="$caption" :chart="$spec" :definition="$chart['definition'] ?? null" />
