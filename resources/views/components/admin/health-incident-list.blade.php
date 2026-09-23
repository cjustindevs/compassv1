@props(['incidents'])

<div class="health-incident-list">
    @forelse ($incidents as $incident)
        <article class="health-incident health-incident-{{ $incident['severity'] }}">
            <span class="health-incident-icon" aria-hidden="true">
                <x-admin.icon :name="$incident['severity'] === 'critical' ? 'alert-circle' : 'warning'" :size="18" />
            </span>
            <div>
                <header>
                    <strong>{{ $incident['service'] }}</strong>
                    <span>{{ strtoupper($incident['severity']) }}</span>
                </header>
                <p>{{ $incident['message'] }}</p>
                <time>{{ $incident['time'] }}</time>
            </div>
        </article>
    @empty
        <div class="health-incidents-empty">
            <x-admin.icon name="check-circle" :size="25" />
            <strong>No recent warnings or errors</strong>
        </div>
    @endforelse
</div>
