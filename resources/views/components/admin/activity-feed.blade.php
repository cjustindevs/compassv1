@props(['activities'])

<article class="dashboard-card activity-card">
    <header class="card-heading card-heading-row">
        <span>
            <h2>Today's activity</h2>
            <p>Live event stream</p>
        </span>
        <span class="prepared-link" aria-disabled="true">
            View all
            <x-admin.icon name="arrow-right" :size="16" />
        </span>
    </header>

    <ol class="activity-list">
        @foreach ($activities as $activity)
            <li>
                <span class="activity-icon activity-icon-{{ $activity['tone'] }}" aria-hidden="true">
                    <x-admin.icon :name="$activity['icon']" :size="18" />
                </span>
                <span class="activity-message">{{ $activity['message'] }}</span>
                <time>
                    <x-admin.icon name="clock" :size="14" />
                    {{ $activity['time'] }}
                </time>
            </li>
        @endforeach
    </ol>
</article>
