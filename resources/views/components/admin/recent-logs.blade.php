@props(['logs'])

<article class="dashboard-card logs-card">
    <header class="card-heading card-heading-row">
        <span>
            <h2>Recent logs</h2>
            <p>Last audit entries</p>
        </span>
        <span class="prepared-link" aria-disabled="true">Open logs</span>
    </header>

    <ol class="logs-list">
        @foreach ($logs as $log)
            <li>
                <span class="log-main">
                    <strong>{{ $log['title'] }}</strong>
                    <span>{{ $log['actor'] }} · {{ $log['area'] }}</span>
                    <small>{{ $log['datetime'] }} · {{ $log['ip'] }}</small>
                </span>
                <span class="status-badge status-badge-{{ $log['tone'] }}">{{ $log['status'] }}</span>
            </li>
        @endforeach
    </ol>
</article>
