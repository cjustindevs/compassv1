@props(['service'])

<article class="health-service-card health-service-{{ $service['status'] }}">
    <span class="health-service-dot" aria-hidden="true"></span>
    <span class="health-service-icon" aria-hidden="true">
        <x-admin.icon :name="$service['icon']" :size="21" />
    </span>
    <h2>{{ $service['name'] }}</h2>
    <span class="health-service-badge">{{ $service['statusLabel'] }}</span>
    <p>{{ $service['metric'] }}</p>
</article>
