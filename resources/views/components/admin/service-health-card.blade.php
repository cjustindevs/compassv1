@props(['service'])
{{-- One status line per service (QA Nov 24: simpler System Health). --}}
<div class="health-service-row health-service-{{ $service['status'] }}" role="listitem">
    <span class="health-service-dot" aria-hidden="true"></span>
    <strong class="health-service-name">{{ $service['name'] }}</strong>
    <span class="health-service-badge">{{ $service['statusLabel'] }}</span>
    <span class="health-service-metric">{{ $service['metric'] }}</span>
</div>
