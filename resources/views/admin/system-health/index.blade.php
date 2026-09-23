<x-admin.layout
    title="System Health"
    page-title="System Health"
    page-subtitle="Real-time infrastructure and service monitoring"
    active-nav="system-health"
    search-placeholder="Search referrals, cases, users, reports..."
    :admin="$admin"
>
    <section class="system-health-page">
        <header class="health-page-heading">
            <div>
                <h1>System Health</h1>
                <p>Real-time infrastructure and service monitoring.</p>
            </div>

            <div class="health-page-actions">
                <span class="health-overall health-overall-{{ $health['overallStatus']['key'] }}">
                    <x-admin.icon name="shield" :size="17" />
                    Overall status: {{ $health['overallStatus']['label'] }}
                </span>
                <a class="admin-button admin-button-secondary" href="#health-incidents">
                    Incidents ({{ $health['activeIncidentCount'] }})
                </a>
            </div>
        </header>

        @if ($loadFailed)
            <section class="health-load-error" role="alert">
                <span><x-admin.icon name="alert-circle" :size="29" /></span>
                <h2>Unable to retrieve system health</h2>
                <p>Some monitoring information may be unavailable. Please try again.</p>
                <a class="admin-button admin-button-secondary" href="{{ route('admin.system-health') }}">
                    <x-admin.icon name="undo" :size="17" /> Retry
                </a>
            </section>
        @else
            @if ($health['source'] === 'preview')
                <div class="health-preview-notice" role="note">
                    <x-admin.icon name="alert-circle" :size="17" />
                    Preview metrics are shown because an infrastructure monitoring provider is not configured yet.
                </div>
            @endif

            <section class="health-service-grid" aria-label="Service health statuses">
                @foreach ($health['services'] as $service)
                    <x-admin.service-health-card :service="$service" />
                @endforeach
            </section>

            <section class="health-charts-grid" aria-label="Infrastructure usage charts">
                <x-admin.chart-card
                    title="CPU Usage (24h)"
                    subtitle="Aggregated across 6 workers"
                    type="area"
                    :chart="$health['charts']['cpu']"
                    summary="Preview CPU usage ranged from 7% to 41% over the displayed period."
                />
                <x-admin.chart-card
                    title="Memory Usage (24h)"
                    subtitle="Percentage of allocated RAM"
                    type="area"
                    :chart="$health['charts']['memory']"
                    summary="Preview memory usage ranged from 28% to 57% over the displayed period."
                />
                <x-admin.chart-card
                    title="API Response Time"
                    subtitle="p50 / p95 in ms"
                    type="line"
                    :chart="$health['charts']['api']"
                    summary="Preview API response time includes p50 and p95 series from approximately 90 to 1360 milliseconds."
                />
            </section>

            <section class="health-secondary-grid" aria-label="Storage, uptime, and incidents">
                <article class="health-detail-card health-storage-card">
                    <header class="card-heading">
                        <h2>Storage Usage</h2>
                        <p>
                            {{ number_format($health['storage']['usedGb']) }} GB of
                            {{ $health['storage']['provisionedLabel'] }} provisioned
                        </p>
                    </header>

                    <div class="health-storage-list">
                        @foreach ($health['storage']['categories'] as $category)
                            <div class="health-storage-item">
                                <div>
                                    <strong>{{ $category['label'] }}</strong>
                                    <span>{{ number_format($category['usedGb']) }} GB</span>
                                </div>
                                <div
                                    class="health-progress health-progress-{{ $category['tone'] }}"
                                    role="progressbar"
                                    aria-label="{{ $category['label'] }} storage usage"
                                    aria-valuemin="0"
                                    aria-valuemax="{{ collect($health['storage']['categories'])->max('usedGb') }}"
                                    aria-valuenow="{{ $category['usedGb'] }}"
                                >
                                    <span style="--health-progress: {{ $category['percentage'] }}%"></span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>

                <article class="health-detail-card health-uptime-card">
                    <header class="card-heading">
                        <h2>System Uptime</h2>
                        <p>Rolling 90 days</p>
                    </header>

                    <strong class="health-uptime-value">{{ number_format($health['uptime']['percentage'], 2) }}%</strong>
                    <p class="health-sla">
                        Target {{ number_format($health['uptime']['target'], 1) }}% SLA ·
                        <span>{{ $health['uptime']['onTrack'] ? 'On track' : 'Below target' }}</span>
                    </p>

                    <div class="health-uptime-history" aria-label="60-day uptime history">
                        @foreach ($health['uptime']['days'] as $index => $day)
                            <span
                                class="uptime-day uptime-day-{{ $day }}"
                                title="Day {{ $index + 1 }}: {{ ucfirst($day) }}"
                            ></span>
                        @endforeach
                    </div>
                    <div class="health-uptime-labels" aria-hidden="true">
                        <span>60d ago</span>
                        <span>Today</span>
                    </div>

                    <p class="sr-only">
                        The preview history contains
                        {{ collect($health['uptime']['days'])->where(fn ($day) => $day === 'degraded')->count() }} degraded days and
                        {{ collect($health['uptime']['days'])->where(fn ($day) => $day === 'outage')->count() }} outage day.
                    </p>
                </article>

                <article class="health-detail-card health-incidents-card" id="health-incidents" tabindex="-1">
                    <header class="card-heading-row">
                        <div class="card-heading">
                            <h2>Recent errors &amp; warnings</h2>
                        </div>
                        <a href="#health-incidents">View all</a>
                    </header>

                    <x-admin.health-incident-list :incidents="$health['incidents']" />
                </article>
            </section>
        @endif
    </section>
</x-admin.layout>
