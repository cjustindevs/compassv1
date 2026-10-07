@props(['activities'])

<article class="dashboard-card activity-card">
    <header class="card-heading card-heading-row">
        <span>
            <h2>Today's activity</h2>
            <p>Live event stream</p>
        </span>
        <span class="prepared-link" aria-disabled="true">
            View all

        </span>
    </header>

    <ol class="activity-list">
        @foreach ($activities as $activity)
            <li>

                <span class="activity-message">{{ $activity['message'] }}</span>
                <time>

                    {{ $activity['time'] }}
                </time>
            </li>
        @endforeach
    </ol>
</article>
