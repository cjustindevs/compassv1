@props([
    'label',
    'value',
    'detail',
    'icon',
    'tone' => 'green',
    'trend' => null,
    'link' => null,
])

<article class="stat-card">
    <div class="stat-card-copy">
        <span class="eyebrow">{{ $label }}</span>
        <strong class="stat-value">{{ $value }}</strong>

    </div>
</article>
