@props(['chart'])
@php
    $values = $chart['values'];
    $total = array_sum($values);
    $isTrend = str_contains(strtolower($chart['title']), 'trend');
    $isDistribution = str_contains(strtolower($chart['title']), 'status') || str_contains(strtolower($chart['title']), 'role');
    $palette = ['#04a052', '#649b83', '#b4cfc1', '#d49545', '#6285a0', '#916c98'];
@endphp
<article class="card dashboard-card co-chart {{ $isTrend ? 'co-trend' : '' }}">
    <header class="card-header"><div><h3>{{ $chart['title'] }}</h3><p class="co-caption">{{ $isTrend ? 'Last six months | Philippine Time' : 'Current recorded totals' }}</p></div></header>
    @if($total > 0)
        @if($isTrend)
            @php
                $maximum = max(4, (int) ceil(max($values) / 4) * 4);
                $labels = array_keys($values);
                $points = [];
                foreach (array_values($values) as $i => $value) $points[] = [52 + $i * (500 / max(count($values)-1,1)), 222 - $value / $maximum * 190];
                $line = implode(' ', array_map(fn($p) => implode(',', $p), $points));
            @endphp
            <svg class="co-line" viewBox="0 0 580 270" role="img" aria-label="{{ $chart['title'] }}: monthly record counts, zero-based axis">
                @foreach(range(0,4) as $tick)
                    @php($y = 222 - $tick * 47.5)
                    <line x1="52" x2="552" y1="{{ $y }}" y2="{{ $y }}" stroke="#e8eeeb" />
                    <text x="40" y="{{ $y+4 }}" text-anchor="end">{{ $maximum / 4 * $tick }}</text>
                @endforeach
                <polygon points="52,222 {{ $line }} 552,222" fill="#04a052" fill-opacity=".07" />
                <polyline points="{{ $line }}" fill="none" stroke="#04a052" stroke-width="3" stroke-linejoin="round" />
                @foreach($points as $i => $point)
                    <circle cx="{{ $point[0] }}" cy="{{ $point[1] }}" r="4" fill="#fff" stroke="#04a052" stroke-width="2"><title>{{ $labels[$i] }}: {{ array_values($values)[$i] }} records</title></circle>
                    <text x="{{ $point[0] }}" y="250" text-anchor="middle">{{ $labels[$i] }}</text>
                @endforeach
                <text x="52" y="16">Records</text>
            </svg>
        @elseif($isDistribution)
            <div class="co-distribution">
                <svg viewBox="0 0 160 160" class="co-donut" role="img" aria-label="{{ $chart['title'] }}: {{ $total }} records">
                    <circle cx="80" cy="80" r="57" fill="none" stroke="#eef3f0" stroke-width="19" />
                    @php($offset = 0)
                    @foreach($values as $label => $value)
                        @php($portion = $value / $total * 100)
                        @if($value > 0)<circle cx="80" cy="80" r="57" pathLength="100" fill="none" stroke="{{ $palette[$loop->index % count($palette)] }}" stroke-width="19" stroke-dasharray="{{ $portion }} {{ 100-$portion }}" stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 80 80)"><title>{{ $label }}: {{ $value }} records</title></circle>@endif
                        @php($offset += $portion)
                    @endforeach
                    <text x="80" y="78" text-anchor="middle" class="co-donut-total">{{ $total }}</text><text x="80" y="97" text-anchor="middle">records</text>
                </svg>
                <ul class="co-legend">@foreach($values as $label => $value)<li><span class="co-key" style="background:{{ $palette[$loop->index % count($palette)] }}" aria-hidden="true"></span><span>{{ $label }}</span><strong>{{ $value }}</strong></li>@endforeach</ul>
            </div>
        @else
            @php($maximum = max($values))
            <div class="co-category-bars" role="list" aria-label="{{ $chart['title'] }} record counts">
            @foreach($values as $label => $value)<div role="listitem"><span class="co-category-label" title="{{ $label }}">{{ $label }}</span><div class="co-track" aria-hidden="true"><span style="width:{{ $value / $maximum * 100 }}%"></span></div><strong>{{ $value }}</strong></div>@endforeach
            </div>
        @endif
        <details class="co-data"><summary>View chart data and definition</summary><p>{{ $chart['definition'] }}</p><table><caption class="sr-only">{{ $chart['title'] }} counts</caption><thead><tr><th>Category / month</th><th>Records</th></tr></thead><tbody>@foreach($values as $label=>$value)<tr><td>{{ $label }}</td><td>{{ $value }}</td></tr>@endforeach</tbody></table></details>
    @else
        <div class="co-empty"><strong>No data yet</strong><p>{{ $chart['definition'] }}</p></div>
    @endif
</article>
