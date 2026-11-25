@props(['overview', 'showRecent' => true, 'showDefinitions' => true, 'smoothLines' => false, 'part' => 'all'])
{{-- part: 'all' (default), 'summary' (period, KPIs, task links, supporting metrics) or 'analytics' (comparison, charts, recent). --}}
@php
    $primaryLabels = auth()->user()?->role === 'admin'
        ? ['Total users', 'Active accounts', 'Available Helpers', 'Matching success rate']
        : (auth()->user()?->role === 'moderator'
            ? ['Waiting requests', 'Available Helpers', 'Active sessions', 'Average waiting time', 'Priority requests', 'Pending assignments']
            : ['Average Helper Response Time', 'Average waiting time', 'Total sessions', 'Completed sessions', 'Active Helpers', 'Average satisfaction rating (/5)']);
    // KPI cards in QA's order for each role; everything else goes to the supporting metrics.
    $primary = collect($overview['cards'])->filter(fn($c) => in_array($c['label'], $primaryLabels))->sortBy(fn($c) => array_search($c['label'], $primaryLabels))->values();
    $secondary = collect($overview['cards'])->reject(fn($c) => in_array($c['label'], $primaryLabels));
    $formatValue = function ($value) {
        if ($value === 'No data') return '—';
        if (!is_string($value) || !str_ends_with($value, ' min')) return $value;
        $minutes = (float) $value;
        if ($minutes >= 1440) return round($minutes / 1440, 1).' days';
        if ($minutes >= 60) return round($minutes / 60, 1).' hr';
        return $value;
    };
    $orderedCharts = collect($overview['charts'])->sortBy(fn($c) => str_contains(strtolower($c['title']), 'trend') ? 0 : 1);
@endphp
<section class="co-overview" aria-label="Dashboard summaries">
    @if($part !== 'analytics')
    @if(in_array(auth()->user()?->role, ['adviser','moderator'], true))
        <x-analytics-period :action="route(auth()->user()->role.'.dashboard')" :dates="\App\Services\AnalyticsPeriod::fromRequest(request())->dates()" :filters="$overview['competency_comparison']['filters'] ?? []" />
    @endif
    @php $kpiIcons = ['Waiting requests'=>'hourglass','Available Helpers'=>'user-check','Active sessions'=>'message','Average waiting time'=>'clock','Priority requests'=>'flag','Pending assignments'=>'user-plus','Completed sessions'=>'check-circle','Total sessions'=>'list','Active Helpers'=>'users','Average Helper Response Time'=>'bolt','Average satisfaction rating (/5)'=>'star']; @endphp
    <div class="co-kpis">@foreach($primary as $card)<article class="co-kpi">@isset($kpiIcons[$card['label']])<span class="co-kpi-icon" aria-hidden="true"><x-ui-icon :name="$kpiIcons[$card['label']]" /></span>@endisset<h3 title="{{ $card['definition'] }}">{{ $card['label'] }}</h3>
    <strong @if(auth()->user()?->role === 'moderator') @if($card['label'] === 'Waiting requests') id="statQueue" @elseif($card['label'] === 'Active sessions') id="statSessions" @elseif($card['label'] === 'Available Helpers') id="statHelpers" @elseif($card['label'] === 'Active emergencies') id="statEmergency" @endif @endif>{{ array_key_exists('raw',$card) ? \App\Services\SessionPerformance::number($card['raw'], $card['unit'] ?? '') : ($card['value'] === 'No data' ? '—' : $formatValue($card['value'])) }}</strong>
    @unless(str_starts_with($card['definition'] ?? '', 'Live'))<small class="co-kpi-period">Selected period</small>@endunless
    </article>@endforeach</div>
    @if(auth()->user()?->role === 'adviser')
        {{-- Evaluations, screenings, referrals and emergencies are already in "Needs your attention" above;
             only the items it does not cover show here, and only when there is something to open. --}}
        @php
            $atCapacity = (int) (collect($overview['cards'])->firstWhere('label', 'Helpers at capacity')['value'] ?? 0);
            $liveNow = auth()->user()->adviser ? \App\Models\Session::where('session_status','active')->whereIn('session_type',['chat','voice'])->where('is_test_record',false)->whereHas('helper', fn ($q) => $q->where('adviser_id', auth()->user()->adviser->id))->count() : 0;
        @endphp
        @if($atCapacity || $liveNow)
        <nav class="co-task-links" aria-label="Live support actions">
            @if($liveNow)<a href="{{ route('adviser.live.index') }}"><strong>{{ $liveNow }}</strong> Live {{ \Illuminate\Support\Str::plural('session', $liveNow) }} to watch or listen to</a>@endif
            @if($atCapacity)<a href="{{ route('adviser.schedule') }}"><strong>{{ $atCapacity }}</strong> {{ \Illuminate\Support\Str::plural('Helper', $atCapacity) }} at capacity</a>@endif
        </nav>
        @endif
    @endif
    @unless(auth()->user()?->role === 'moderator')<details class="co-definitions"><summary>Operational status and supporting metrics</summary><div class="co-secondary">@foreach($secondary as $card)<div><span title="{{ $card['definition'] }}">{{ $card['label'] }}</span><strong title="{{ $card['definition'] }}" @if(auth()->user()?->role === 'moderator' && $card['label'] === 'Active emergencies') id="statEmergency" @endif>{{ array_key_exists('raw',$card) ? \App\Services\SessionPerformance::number($card['raw'], $card['unit'] ?? '') : $formatValue($card['value']) }}</strong>@if(isset($card['samples']))<small>{{ $card['samples'] }} cases measured</small>@endif</div>@endforeach</div></details>@endunless

    @endif
    @if($part !== 'summary')
    @if($overview['competency_comparison'] ?? null)
        <x-adviser-competency-comparison :comparison="$overview['competency_comparison']" />
    @endif
    <div class="co-charts">@foreach($orderedCharts as $chart)<x-dashboard-chart :chart="$chart" :smooth="$smoothLines" />@endforeach</div>
    @if($showRecent)<article class="card dashboard-card co-recent"><header class="card-header"><h3>{{ auth()->user()?->role === 'moderator' ? 'Recent emergency activity' : 'Recent case activity' }}</h3></header><ul>@forelse($overview['recent'] as $item)<li><span>{{ $item['label'] }}</span><time>{{ $item['at'] }}</time></li>@empty<li>No recorded case activity yet.</li>@endforelse</ul></article>@endif
    @endif
</section>
@once
<link rel="stylesheet" href="{{ asset('css/dashboard-overview.css') }}?v={{ filemtime(public_path('css/dashboard-overview.css')) }}">
@endonce
