<x-admin.layout title="System Overview" :admin="$admin" :search-query="$searchQuery" :search-action="route('admin.users')" search-placeholder="Search user accounts...">
    @vite('resources/js/dashboard-analytics.js')
    <header class="page-heading admin-section-heading">
        <div><h1>System Overview</h1><p>Account administration and recent platform activity.</p></div>
        <a class="admin-button admin-button-secondary" href="{{ route('admin.users') }}">Manage users</a>
    </header>
        <x-analytics-period :action="route('admin.dashboard')" :dates="$analyticsDates" :report-url="route('admin.reports', $analyticsDates)" />
    <x-performance-cards :cards="$primaryStats" />
    <p class="analytics-period-caption">Monitoring: {{ $technical['health'] }} | Backups: {{ $technical['backup'] }}. These integration states are not uptime or backup-success measurements.</p>
    <p class="analytics-period-caption">Total Users: {{ $technical['total'] }} | Inactive accounts: {{ $technical['inactive'] }} | Recorded failed Admin logins: {{ $technical['failed_admin_logins'] }} in the selected period. Other failed logins are not fully audited and are not represented as zero. Historical uptime is not monitored.</p>
    <section class="analytics-section" aria-labelledby="admin-analytics-heading">
        <div class="analytics-section-heading"><div><h2 id="admin-analytics-heading">Platform activity</h2><p>Account registrations, audit events and current account distribution.</p></div></div>

        @if($analytics)
            <div class="analytics-chart-grid">
                <x-analytics-chart id="admin-registration-trend" title="Account registrations" :description="$analytics['registrations']['interval'].' registrations in the selected period.'" :chart="['type'=>'line','labels'=>$analytics['registrations']['labels'],'datasets'=>[['label'=>'Registrations','data'=>$analytics['registrations']['values']]]]" :link="route('admin.reports', $analyticsDates + ['section'=>'registrations'])" />
                <x-analytics-chart id="admin-audit-trend" title="Administrative audit activity" :description="$technical['audit']['interval'].' recorded technical, security and account events in the selected period.'" :chart="['type'=>'line','labels'=>$technical['audit']['labels'],'datasets'=>[['label'=>'Audit events','data'=>$technical['audit']['values']]]]" :link="route('admin.audit-logs')" />
                <x-analytics-chart id="admin-role-distribution" title="Accounts by role" description="Current account population, including enabled and disabled accounts. Independent of the date filter." :chart="['type'=>'bar','labels'=>$analytics['roles']['labels'],'datasets'=>[['label'=>'Accounts','data'=>$analytics['roles']['values']]]]" :link="route('admin.users')" />
            </div>
            <details class="analytics-definitions"><summary>How these totals are counted</summary><p>Registrations use account creation dates. Each support case counts once as a request, using its submission date or legacy creation date. Completions include completed and evaluated sessions with recorded end dates. Archived records remain in historical totals; current account distribution is not period-filtered.</p>@if($analytics['missing_completion_dates'])<p>{{ number_format($analytics['missing_completion_dates']) }} historical completed/evaluated records have no end date and cannot be placed on the completion timeline.</p>@endif<p><a class="analytics-button" href="{{ route('admin.reports', $analyticsDates + ['activity_date'=>'completed']) }}">View completed session records</a></p></details>
        @else
            <div class="analytics-feedback" role="alert">Unable to load analytics. <a class="analytics-button" href="{{ route('admin.dashboard', $analyticsDates) }}">Retry</a></div>
        @endif
    </section>
    <section class="dashboard-card admin-recent-activity" aria-labelledby="admin-activity-heading">
        <header class="admin-section-heading">
            <div><h2 id="admin-activity-heading">Recent administrative activity</h2><p>Latest account, authentication, system, and backup events.</p></div>
            <a class="admin-text-link" href="{{ route('admin.audit-logs') }}">View all audit logs</a>
        </header>
        <div class="admin-table-scroll" tabindex="0" role="region" aria-label="Recent administrative activity">
            <table class="admin-activity-table">
                <thead><tr><th scope="col">Activity</th><th scope="col">Administrator / actor</th><th scope="col">Date (Philippine Time)</th></tr></thead>
                <tbody>
                    @forelse ($recentLogs as $log)
                        <tr><td>{{ Str::headline($log->action) }}</td><td>{{ $log->actor?->name ?? 'System' }}</td><td><time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->timezone('Asia/Manila')->format('M d, Y g:i A') }}</time></td></tr>
                    @empty
                        <tr><td colspan="3" class="admin-table-empty">No administrative events have been recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-admin.layout>
