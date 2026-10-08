@props(['overview'])
<section class="co-overview" aria-label="Operational overview">
    <header class="co-heading"><h2>At a glance</h2><p>Current totals, six-month trends, and recent recorded activity. Times are Philippine Time.</p></header>
    <div class="co-kpis">@foreach($overview['cards'] as $card)<article class="co-card"><h3>{{ $card['label'] }}</h3><strong>{{ $card['value'] }}</strong><p>{{ $card['definition'] }}</p></article>@endforeach</div>
    <div class="co-charts">@foreach($overview['charts'] as $chart)
        <article class="co-card"><h3>{{ $chart['title'] }}</h3><p>{{ $chart['definition'] }}</p>
        @if(array_sum($chart['values']) > 0)
            @php($maximum = max($chart['values']))
            <ul class="co-bars" aria-label="{{ $chart['title'] }}: counts">
            @foreach($chart['values'] as $label => $value)<li><div><span>{{ $label }}</span><strong>{{ $value }}</strong></div><div class="co-track" aria-hidden="true"><span style="width:{{ $maximum ? round($value / $maximum * 100, 2) : 0 }}%"></span></div></li>@endforeach
            </ul><p class="co-unit">Unit: records. Bars start at zero; longest bar = {{ $maximum }}.</p>
        @else<p class="co-empty">No data for this summary yet.</p>@endif
        </article>
    @endforeach</div>
    <article class="co-card co-recent"><h3>Recent case activity</h3><ul>@forelse($overview['recent'] as $item)<li><span>{{ $item['label'] }}</span><time>{{ $item['at'] }}</time></li>@empty<li>No recorded case activity yet.</li>@endforelse</ul></article>
</section>
@once
<style>
.co-overview{margin:20px 0 28px;color:#163b2d;font-family:inherit}.co-heading{margin-bottom:16px}.co-heading h2{font-size:18px;font-weight:700;margin:0 0 6px}.co-heading p,.co-card p{font-size:12px;color:#6b7280;line-height:1.6;margin:6px 0 12px}.co-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:18px}.co-card{background:#fff;border:1px solid #e1e8e4;border-radius:16px;padding:18px;min-width:0}.co-card h3{font-size:14px;font-weight:600;margin:0 0 10px}.co-kpis .co-card>strong{display:block;font-size:26px;color:var(--green-600,#087341);line-height:1.3}.co-charts{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px}.co-bars{list-style:none;padding:0;margin:16px 0}.co-bars li{margin-bottom:12px}.co-bars li>div:first-child{display:flex;justify-content:space-between;gap:12px;font-size:12px;margin-bottom:5px}.co-bars span{overflow-wrap:anywhere}.co-track{height:7px;background:#edf5f0;border-radius:8px;overflow:hidden}.co-track>span{display:block;height:100%;background:var(--green-500,#04a052);border-radius:8px}.co-recent{margin-top:16px}.co-recent ul{list-style:none;padding:0;margin:0}.co-recent li{display:flex;justify-content:space-between;gap:16px;border-top:1px solid #edf0ee;padding:12px 0;font-size:13px}.co-recent time{font-size:12px;color:#6b7280}.co-empty{padding:20px 0}.co-unit{margin-bottom:0!important}@media(max-width:600px){.co-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.co-charts{grid-template-columns:1fr}.co-kpis .co-card{padding:14px}.co-kpis .co-card>strong{font-size:22px}.co-recent li{flex-direction:column;gap:4px}}
</style>
@endonce
