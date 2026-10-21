@props(['cards'])
<div class="performance-kpis" aria-label="Role dashboard indicators">
@foreach($cards as $card)
<article class="performance-kpi">
    <h3>{{ $card['label'] }}</h3><strong @if(!empty($card['id'])) id="{{ $card['id'] }}" @endif>{{ $card['value'] }}</strong>
    <p>{{ $card['detail'] ?? 'Selected period' }}</p>
    @if(isset($card['samples']))<small>{{ $card['samples'] }} records measured</small>@endif
    @if(!empty($card['link']))<a href="{{ $card['link'] }}">{{ $card['action'] ?? 'View records' }}</a>@endif
</article>
@endforeach
</div>
@once
<style>
.performance-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:16px;margin:18px 0 24px}
.performance-kpi{background:#fff;border:1px solid #dce6df;border-top:3px solid #169568;border-radius:14px;padding:20px;min-width:0;display:flex;flex-direction:column;gap:6px}
.performance-kpi h3{font-size:13px;font-weight:600;color:#53665a;margin:0;line-height:1.5}
.performance-kpi>strong{display:block;font-size:clamp(28px,2.8vw,38px);line-height:1.2;font-weight:750;color:#173b2c;font-variant-numeric:tabular-nums;overflow-wrap:anywhere;margin:6px 0}
.performance-kpi p,.performance-kpi small{display:block;font-size:12px;line-height:1.5;color:#64748b;margin:0}
.performance-kpi a{font-size:12px;color:#087341;margin-top:auto;padding-top:8px}.performance-kpi a:focus-visible{outline:2px solid #087341;outline-offset:3px}
@media(min-width:1250px){.performance-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}.performance-kpis:has(>article:nth-child(4):last-child){grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:600px){.performance-kpis{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.performance-kpi{padding:16px}.performance-kpi>strong{font-size:28px}}
@media(max-width:350px){.performance-kpis{grid-template-columns:1fr}}
</style>
@endonce
