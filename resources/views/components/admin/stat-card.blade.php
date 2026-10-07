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

                    {{ $trend }}
                </span>
            @endif
            {{ $detail }}
        </span>
    </div>
</article>
