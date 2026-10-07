@props(['service'])

<article class="health-service-card health-service-{{ $service['status'] }}">
    <span class="health-service-dot" aria-hidden="true"></span>

    <h2>{{ $service['name'] }}</h2>
    <span class="health-service-badge">{{ $service['statusLabel'] }}</span>
    <p>{{ $service['metric'] }}</p>
</article>
