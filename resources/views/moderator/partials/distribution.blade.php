{{-- One chart per distribution: a donut for a small status mix, horizontal bars for longer lists. --}}
@php
    $labels = array_map('strval', array_keys($values));
    $type = ($donutOnly ?? false) || count($values) <= 5 ? 'donut' : 'bar';
    $spec = ['type' => $type, 'labels' => $labels, 'datasets' => [['label' => 'Records', 'data' => array_values($values)]]];
@endphp
<x-analytics-chart class="mo-chart" :title="$chartTitle" :chart="$spec" />
