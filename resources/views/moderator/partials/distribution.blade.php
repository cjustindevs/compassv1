@php
    $total = array_sum($values);
    $palette = ['#00ad72', '#ffca3a', '#4285f4', '#ff5d68', '#a8b3c2', '#8571d6', '#29a6aa'];
    $semantic = ['completed'=>'#00ad72','resolved'=>'#00ad72','closed'=>'#00ad72','pending'=>'#ffca3a','scheduled'=>'#4285f4','active'=>'#4285f4','cancelled'=>'#ff5d68','no show'=>'#a8b3c2','emergency'=>'#ff353e','high'=>'#ff8800','moderate'=>'#ffb300','low'=>'#00ad72','escalated'=>'#8571d6','responding'=>'#4285f4','awaiting acknowledgment'=>'#ffca3a','archived'=>'#a8b3c2'];
    $colors = [];
    foreach (array_keys($values) as $i => $label) $colors[$label] = $semantic[strtolower($label)] ?? $palette[$i % count($palette)];
@endphp
<div class="{{ ($donutOnly ?? false) ? '' : 'mo-chart-pair' }}">
@if(!($donutOnly ?? false))
<div class="mo-chart-panel">
@if($total > 0 && count($values) <= 7)
    @php
        $maximum = max(4, (int) ceil(max($values) / 4) * 4);
        $step = 460 / max(count($values), 1);
    @endphp
    <svg class="mo-bars" viewBox="0 0 540 270" role="img" aria-label="{{ $chartTitle }}: record counts, axis starts at zero">
        @foreach(range(0,4) as $tick)
            @php($y = 208 - $tick * 43)
            <line x1="42" x2="518" y1="{{ $y }}" y2="{{ $y }}" stroke="#edf1f5" />
            <text x="32" y="{{ $y+4 }}" text-anchor="end">{{ $maximum / 4 * $tick }}</text>
        @endforeach
        @foreach($values as $label=>$count)
            @php($x = 52 + $loop->index * $step + $step / 2)
            @php($height = $count / $maximum * 172)
            <rect x="{{ $x - min(44, $step*.55)/2 }}" y="{{ 208-$height }}" width="{{ min(44,$step*.55) }}" height="{{ $height }}" rx="3" fill="{{ $colors[$label] }}"><title>{{ $label }}: {{ $count }} records</title></rect>
            <text x="{{ $x }}" y="{{ 199-$height }}" text-anchor="middle" class="mo-chart-count">{{ $count }}</text>
            <text x="{{ $x }}" y="230" text-anchor="middle">
            @foreach(explode("\n",wordwrap($label,14,"\n")) as $line)
                <tspan x="{{ $x }}" dy="{{ $loop->first ? 0 : 14 }}">{{ $line }}</tspan>
            @endforeach
            </text>
        @endforeach
    </svg>
@elseif($total > 0)
    <div class="mo-category-bars" aria-label="{{ $chartTitle }} record counts">
    @foreach($values as $label=>$count)
        <div><span>{{ $label }}</span><strong>{{ $count }}</strong><div class="mo-track"><span style="width:{{ $count/max($values)*100 }}%;background:{{ $colors[$label] }}"></span></div></div>
    @endforeach
    </div>
@else
    <p class="mo-empty">No records match the selected filters.</p>
@endif
</div>
@endif
<div class="mo-chart-panel mo-distribution">
    <svg class="mo-donut" viewBox="0 0 180 180" role="img" aria-label="{{ $chartTitle }}: {{ $total }} total records">
        <circle cx="90" cy="90" r="65" fill="none" stroke="#edf2f0" stroke-width="24" />
        @php($offset=0)
        @foreach($values as $label=>$count)
            @php($portion=$total ? $count/$total*100 : 0)
            @if($count > 0)
                <circle cx="90" cy="90" r="65" pathLength="100" fill="none" stroke="{{ $colors[$label] }}" stroke-width="24" stroke-dasharray="{{ $portion }} {{ 100-$portion }}" stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 90 90)"><title>{{ $label }}: {{ $count }} ({{ round($portion,1) }}%)</title></circle>
            @endif
            @php($offset+=$portion)
        @endforeach
        <text x="90" y="89" text-anchor="middle" class="mo-donut-total">{{ $total }}</text><text x="90" y="110" text-anchor="middle">Total</text>
    </svg>
    <ul class="mo-chart-legend" aria-label="{{ $chartTitle }} counts and percentages">
    @forelse($values as $label=>$count)
        <li><span class="mo-chart-key" style="background:{{ $colors[$label] }}" aria-hidden="true"></span><span>{{ $label }}</span><strong>{{ $count }}</strong>
        @if($showPercent ?? true)<span>{{ $total ? round($count/$total*100,1) : 0 }}%</span>
        @endif
        </li>
    @empty
        <li>No recorded data in this period.</li>
    @endforelse
    </ul>
</div>
</div>
