@props(['overview', 'showRecent' => true, 'showDefinitions' => true, 'smoothLines' => false])
@php
    $primaryLabels = auth()->user()?->role === 'admin'
        ? ['Total users', 'Active accounts', 'Available Helpers', 'Matching success rate']
        : (auth()->user()?->role === 'moderator'
            ? ['Waiting requests', 'Available Helpers', 'Active sessions', 'Average waiting time']
            : ['Completed sessions', 'Average Helper Response Time', 'Satisfaction rating', 'Average competency score']);
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
    @if(in_array(auth()->user()?->role, ['adviser','moderator'], true))
        <x-analytics-period :action="route(auth()->user()->role.'.dashboard')" :dates="\App\Services\AnalyticsPeriod::fromRequest(request())->dates()" />
    @endif
    @if(auth()->user()?->role === 'adviser')
        <nav class="co-task-links" aria-label="Live support actions">
            <a href="{{ route('adviser.emergencies') }}">Live emergency cases: <strong>{{ collect($overview['cards'])->firstWhere('label','Escalated cases')['value'] ?? 0 }}</strong></a>
            <a href="{{ route('adviser.referrals') }}">Open referral reviews</a>
            <a href="{{ route('adviser.evaluations') }}">Open documentation reviews</a>
        </nav>
    @endif
    <div class="co-kpis">@foreach($primary as $card)<article class="stat-card dashboard-card co-kpi"><h3 title="{{ $card['definition'] }}">{{ $card['label'] }}</h3><strong @if(auth()->user()?->role === 'moderator') @if($card['label'] === 'Waiting requests') id="statQueue" @elseif($card['label'] === 'Active sessions') id="statSessions" @elseif($card['label'] === 'Available Helpers') id="statHelpers" @endif @endif>{{ $formatValue($card['value']) }}</strong>@if(isset($card['samples']))<small>{{ $card['samples'] }} records measured</small>@endif @if(isset($card['link']))<a class="co-metric-link" href="{{ $card['link'] }}">View records</a>@endif</article>@endforeach</div>
    <details class="co-definitions" @if(auth()->user()?->role === 'moderator') open @endif><summary>Operational status and supporting metrics</summary><div class="co-secondary">@foreach($secondary as $card)<div><span title="{{ $card['definition'] }}">{{ $card['label'] }}</span><strong title="{{ $card['definition'] }}" @if(auth()->user()?->role === 'moderator' && $card['label'] === 'Active emergencies') id="statEmergency" @endif>{{ $formatValue($card['value']) }}</strong>@if(isset($card['samples']))<small>{{ $card['samples'] }} cases measured</small>@endif</div>@endforeach</div></details>
    @if($showDefinitions)<details class="co-definitions"><summary>Metric definitions</summary><dl>@foreach($overview['cards'] as $card)<dt>{{ $card['label'] }}: {{ $card['value'] }}</dt><dd>{{ $card['definition'] }}</dd>@endforeach</dl></details>@endif
    <div class="co-charts">@foreach($orderedCharts as $chart)<x-dashboard-chart :chart="$chart" :smooth="$smoothLines" />@endforeach</div>
    @if($showRecent)<article class="card dashboard-card co-recent"><header class="card-header"><h3>{{ auth()->user()?->role === 'moderator' ? 'Recent emergency activity' : 'Recent case activity' }}</h3></header><ul>@forelse($overview['recent'] as $item)<li><span>{{ $item['label'] }}</span><time>{{ $item['at'] }}</time></li>@empty<li>No recorded case activity yet.</li>@endforelse</ul></article>@endif
</section>
@once
<link rel="stylesheet" href="{{ asset('css/dashboard-overview.css') }}?v={{ filemtime(public_path('css/dashboard-overview.css')) }}">
@endonce
