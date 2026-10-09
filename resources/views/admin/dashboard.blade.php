<x-admin.layout title="System Overview" :admin="$admin" :search-query="$searchQuery" :search-action="route('admin.users')" search-placeholder="Search user accounts...">
    <header class="page-heading admin-section-heading">
        <div><h1>System Overview</h1><p>Account administration and recent platform activity.</p></div>
        <a class="admin-button admin-button-secondary" href="{{ route('admin.users') }}">Manage users</a>
    </header>
    <section class="admin-account-stats" aria-label="Account overview">
        @foreach ($primaryStats as $stat)
            <x-admin.stat-card :label="$stat['label']" :value="$stat['value']" :detail="$stat['detail']" :icon="$stat['icon']" />
        @endforeach
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
