@props(['overview', 'showRecent' => true])
@php
    $primaryLabels = auth()->user()?->role === 'admin'
        ? ['Total users', 'Active accounts', 'Available Helpers', 'Matching success rate']
        : (auth()->user()?->role === 'moderator'
            ? ['Active emergencies', 'Resolved emergencies', 'Pending / unassigned']
            : ['Active cases', 'Resolved cases', 'Pending cases', 'Escalated cases']);
    $primary = collect($overview['cards'])->filter(fn($c) => in_array($c['label'], $primaryLabels));
    $secondary = collect($overview['cards'])->reject(fn($c) => in_array($c['label'], $primaryLabels));
    $formatValue = function ($value) {
        if (!is_string($value) || !str_ends_with($value, ' min')) return $value;
        $minutes = (float) $value;
        if ($minutes >= 1440) return round($minutes / 1440, 1).' days';
        if ($minutes >= 60) return round($minutes / 60, 1).' hr';
        return $value;
    };
    $orderedCharts = collect($overview['charts'])->sortBy(fn($c) => str_contains(strtolower($c['title']), 'trend') ? 0 : 1);
@endphp
<section class="co-overview" aria-label="Dashboard summaries">
    <div class="co-kpis">@foreach($primary as $card)<article class="stat-card dashboard-card co-kpi"><h3 title="{{ $card['definition'] }}">{{ $card['label'] }}</h3><strong @if(auth()->user()?->role === 'moderator' && $card['label'] === 'Active emergencies') id="statEmergency" @endif>{{ $formatValue($card['value']) }}</strong></article>@endforeach</div>
    <div class="co-secondary">@foreach($secondary as $card)<div><span title="{{ $card['definition'] }}">{{ $card['label'] }}</span><strong title="{{ $card['value'] }}">{{ $formatValue($card['value']) }}</strong></div>@endforeach</div>
    <details class="co-definitions"><summary>Metric definitions</summary><dl>@foreach($overview['cards'] as $card)<dt>{{ $card['label'] }}: {{ $card['value'] }}</dt><dd>{{ $card['definition'] }}</dd>@endforeach</dl></details>
    <div class="co-charts">@foreach($orderedCharts as $chart)<x-dashboard-chart :chart="$chart" />@endforeach</div>
    @if($showRecent)<article class="card dashboard-card co-recent"><header class="card-header"><h3>Recent case activity</h3></header><ul>@forelse($overview['recent'] as $item)<li><span>{{ $item['label'] }}</span><time>{{ $item['at'] }}</time></li>@empty<li>No recorded case activity yet.</li>@endforelse</ul></article>@endif
</section>
@once
<link rel="stylesheet" href="{{ asset('css/dashboard-overview.css') }}?v={{ filemtime(public_path('css/dashboard-overview.css')) }}">
@endonce
