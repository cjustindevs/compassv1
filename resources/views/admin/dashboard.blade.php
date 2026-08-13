<x-admin.layout
    title="System Overview"
    :admin="$admin"
    :search-query="$searchQuery"
>
    <header class="page-heading">
        <h1>System Overview</h1>
        <p>Real-time operational health, activity and platform metrics.</p>
    </header>

    @if ($searchQuery !== '')
        <div class="search-notice" role="status">
            Search is prepared for future integration. No indexed results are available yet for
            <strong>“{{ $searchQuery }}”</strong>.
            <a href="{{ route('admin.dashboard') }}">Clear search</a>
        </div>
    @endif

    <section class="stats-grid" aria-label="Platform statistics">
        @foreach ($primaryStats as $stat)
            <x-admin.stat-card
                :label="$stat['label']"
                :value="$stat['value']"
                :detail="$stat['detail']"
                :icon="$stat['icon']"
                :tone="$stat['tone']"
                :trend="$stat['trend'] ?? null"
            />
        @endforeach
    </section>

    <section class="status-grid" aria-label="System status">
        @foreach ($systemStatuses as $status)
            <x-admin.status-card
                :label="$status['label']"
                :value="$status['value']"
                :detail="$status['detail']"
                :icon="$status['icon']"
                :tone="$status['tone']"
                :trend="$status['trend'] ?? null"
            />
        @endforeach
    </section>

    <section class="charts-grid" aria-label="Platform charts">
        <x-admin.chart-card
            title="User Growth"
            subtitle="Cumulative user accounts"
            type="area"
            :chart="$charts['userGrowth']"
        />
        <x-admin.chart-card
            title="System Usage"
            subtitle="CPU / Memory over 24h"
            type="line"
            :chart="$charts['systemUsage']"
        />
        <x-admin.chart-card
            title="Login Statistics"
            subtitle="Weekly success vs. failed"
            type="bar"
            :chart="$charts['loginStatistics']"
        />
    </section>

    <section class="dashboard-bottom-grid" aria-label="Recent platform activity">
        <x-admin.activity-feed :activities="$activities" />
        <x-admin.recent-logs :logs="$recentLogs" />
    </section>
</x-admin.layout>
