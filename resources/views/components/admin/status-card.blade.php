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

    <span class="eyebrow">{{ $label }}</span>
    <strong class="status-value">{{ $value }}</strong>
    <span class="status-detail">

        {{ $detail }}
    </span>
</article>
