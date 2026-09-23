<x-admin.layout
    title="System Overview"
    :admin="$admin"
    :search-query="$searchQuery"
>
    <header class="page-heading">
        <h1>System Overview</h1>
        <p>Current account and session totals from COMPASS. Refresh to update.</p>
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
        <article class="dashboard-card"><header class="card-heading"><h2>Infrastructure monitoring</h2><p>No live telemetry provider is configured.</p></header><p>System Health contains clearly labeled layout previews.</p><a href="{{ route('admin.system-health') }}">Open System Health</a></article>
        <article class="dashboard-card"><header class="card-heading"><h2>Access and security</h2><p>Account activity is recorded in the audit log.</p></header><a href="{{ route('admin.audit-logs') }}">Review audit events</a></article>
    </section>

    <section class="dashboard-bottom-grid" aria-label="Recent platform activity">
        <x-admin.activity-feed :activities="$activities" />
        <x-admin.recent-logs :logs="$recentLogs" />
    </section>
</x-admin.layout>
