@props(['incidents'])

<div class="health-incident-list">
    @forelse ($incidents as $incident)
        <article class="health-incident health-incident-{{ $incident['severity'] }}">

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

            <strong>No recent warnings or errors</strong>
        </div>
    @endforelse
</div>
