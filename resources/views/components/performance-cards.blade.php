@props(['cards'])
<div class="performance-kpis" aria-label="Role dashboard indicators">
@foreach($cards as $card)
<article class="performance-kpi">
    <h3>{{ $card['label'] }}</h3><strong>{{ $card['value'] }}</strong>
    <p>{{ $card['detail'] ?? 'Selected period' }}</p>
    @if(isset($card['samples']))<small>{{ $card['samples'] }} records measured</small>@endif
    @if(!empty($card['link']))<a href="{{ $card['link'] }}">{{ $card['action'] ?? 'View records' }}</a>@endif
</article>
@endforeach
</div>
@once
<style>
.performance-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:14px 0 20px}.performance-kpi{background:var(--bg-card,#fff);border:1px solid var(--border-color,#e1e8e4);border-radius:14px;padding:16px;min-width:0}.performance-kpi h3{font-size:13px;font-weight:500;color:var(--text-secondary,#64748b);margin:0 0 8px}.performance-kpi>strong{display:block;font-size:24px;line-height:1.3;color:var(--text-primary,#163b2d);overflow-wrap:anywhere}.performance-kpi p,.performance-kpi small{display:block;font-size:12px;color:var(--text-secondary,#64748b);margin:6px 0}.performance-kpi a,.co-metric-link{display:inline-block;margin-top:8px;font-size:12px;color:#087341;text-decoration:underline}@media(max-width:1000px){.performance-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:480px){.performance-kpi{padding:12px}.performance-kpi>strong{font-size:20px}}
</style>
@endonce
