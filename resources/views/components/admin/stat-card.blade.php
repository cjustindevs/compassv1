@props([
    'label',
    'value',
    'detail',
    'icon',
    'tone' => 'green',
    'trend' => null,
])

<article class="stat-card">
    <div class="stat-card-copy">
        <span class="eyebrow">{{ $label }}</span>
        <strong class="stat-value">{{ $value }}</strong>
        <span class="stat-detail">
            @if ($trend)
                <span class="trend-pill">
                    <x-admin.icon name="arrow-up" :size="12" :stroke-width="2.2" />
                    {{ $trend }}
                </span>
            @endif
            {{ $detail }}
        </span>
    </div>

    <span class="stat-icon stat-icon-{{ $tone }}" aria-hidden="true">
        <x-admin.icon :name="$icon" :size="24" />
    </span>
</article>
