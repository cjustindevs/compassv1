@props([
    'label',
    'value',
    'detail',
    'icon',
    'tone' => 'green',
    'trend' => null,
])

<article class="status-card status-card-{{ $tone }}">
    <span class="status-dot" aria-hidden="true"></span>
    <span class="status-icon" aria-hidden="true">
        <x-admin.icon :name="$icon" :size="27" />
    </span>
    <span class="eyebrow">{{ $label }}</span>
    <strong class="status-value">{{ $value }}</strong>
    <span class="status-detail">
        @if ($trend === 'up')
            <x-admin.icon name="arrow-up" :size="13" :stroke-width="2.2" />
        @endif
        {{ $detail }}
    </span>
</article>
