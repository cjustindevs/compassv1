@props([
    'title',
    'subtitle',
    'type' => 'line',
    'chart',
])

@php
    $width = 420;
    $height = 245;
    $padding = ['left' => 48, 'right' => 18, 'top' => 18, 'bottom' => 38];
    $plotWidth = $width - $padding['left'] - $padding['right'];
    $plotHeight = $height - $padding['top'] - $padding['bottom'];
    $labels = $chart['labels'];
    $maxValue = $chart['max'];
    $seriesCount = count($chart['series']);
    $labelCount = max(count($labels), 1);
    $gradientId = 'chart-gradient-'.md5($title);

    $linePoints = function (array $values) use ($padding, $plotWidth, $plotHeight, $maxValue): array {
        $count = max(count($values) - 1, 1);

        return collect($values)->map(function ($value, $index) use ($padding, $plotWidth, $plotHeight, $maxValue, $count) {
            return [
                'x' => $padding['left'] + ($plotWidth * $index / $count),
                'y' => $padding['top'] + $plotHeight - (($value / $maxValue) * $plotHeight),
            ];
        })->all();
    };

    $pointsString = fn (array $points): string => collect($points)
        ->map(fn ($point) => number_format($point['x'], 2, '.', '').','.number_format($point['y'], 2, '.', ''))
        ->implode(' ');
@endphp

<article class="dashboard-card chart-card">
    <header class="card-heading">
        <h2>{{ $title }}</h2>
        <p>{{ $subtitle }}</p>
    </header>

    <div class="chart-wrap">
        <svg class="dashboard-chart" viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ $title }} chart">
            @if ($type === 'area')
                <defs>
                    <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#83afff" stop-opacity="0.48" />
                        <stop offset="100%" stop-color="#dfeaff" stop-opacity="0.04" />
                    </linearGradient>
                </defs>
            @endif

            @foreach ($chart['ticks'] as $tick)
                @php
                    $tickY = $padding['top'] + $plotHeight - (($tick / $maxValue) * $plotHeight);
                @endphp
                <line
                    x1="{{ $padding['left'] }}"
                    x2="{{ $width - $padding['right'] }}"
                    y1="{{ $tickY }}"
                    y2="{{ $tickY }}"
                    class="chart-grid-line"
                />
                <text x="{{ $padding['left'] - 9 }}" y="{{ $tickY + 4 }}" text-anchor="end" class="chart-axis-label">{{ $tick }}</text>
            @endforeach

            @if ($type === 'bar')
                @php
                    $groupWidth = $plotWidth / $labelCount;
                    $barWidth = min(17, ($groupWidth - 10) / max($seriesCount, 1));
                @endphp

                @foreach ($labels as $labelIndex => $label)
                    @php
                        $groupCenter = $padding['left'] + ($groupWidth * $labelIndex) + ($groupWidth / 2);
                        $barsTotalWidth = ($barWidth * $seriesCount) + (4 * max($seriesCount - 1, 0));
                        $groupStart = $groupCenter - ($barsTotalWidth / 2);
                    @endphp

                    @foreach ($chart['series'] as $seriesIndex => $series)
                        @php
                            $barHeight = ($series['values'][$labelIndex] / $maxValue) * $plotHeight;
                            $barX = $groupStart + (($barWidth + 4) * $seriesIndex);
                            $barY = $padding['top'] + $plotHeight - $barHeight;
                        @endphp
                        <rect
                            x="{{ $barX }}"
                            y="{{ $barY }}"
                            width="{{ $barWidth }}"
                            height="{{ max($barHeight, 2) }}"
                            rx="{{ min(6, $barWidth / 2) }}"
                            fill="{{ $series['color'] }}"
                        />
                    @endforeach

                    <text x="{{ $groupCenter }}" y="{{ $height - 12 }}" text-anchor="middle" class="chart-axis-label">{{ $label }}</text>
                @endforeach
            @else
                @foreach ($chart['series'] as $seriesIndex => $series)
                    @php
                        $seriesPoints = $linePoints($series['values']);
                        $seriesPointString = $pointsString($seriesPoints);
                        $areaPoints = $padding['left'].','.($padding['top'] + $plotHeight).' '.$seriesPointString.' '.($width - $padding['right']).','.($padding['top'] + $plotHeight);
                    @endphp

                    @if (($series['fill'] ?? false) && $type === 'area')
                        <polygon points="{{ $areaPoints }}" fill="url(#{{ $gradientId }})" />
                    @endif

                    <polyline
                        points="{{ $seriesPointString }}"
                        fill="none"
                        stroke="{{ $series['color'] }}"
                        stroke-width="3"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                @endforeach

                @php
                    $visibleLabels = collect($labels)->filter(fn ($label) => $label !== '');
                    $lastLabelIndex = max($labelCount - 1, 1);
                @endphp
                @foreach ($labels as $index => $label)
                    @continue($label === '')
                    @php
                        $labelX = $padding['left'] + ($plotWidth * $index / $lastLabelIndex);
                    @endphp
                    <text x="{{ $labelX }}" y="{{ $height - 12 }}" text-anchor="middle" class="chart-axis-label">{{ $label }}</text>
                @endforeach
            @endif
        </svg>
    </div>

    @if (count($chart['series']) > 1)
        <div class="chart-legend" aria-label="Chart legend">
            @foreach ($chart['series'] as $series)
                <span><i style="--legend-color: {{ $series['color'] }}"></i>{{ $series['name'] }}</span>
            @endforeach
        </div>
    @endif
</article>
