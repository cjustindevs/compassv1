@props(['items' => [], 'title' => 'Needs your attention'])
@php $items = collect($items)->filter(fn ($item) => (int) ($item['count'] ?? 0) > 0)->values(); @endphp
@if($items->isNotEmpty())
    <section class="ui-attention" aria-label="{{ $title }}">
        <h2 class="ui-attention-title">{{ $title }}</h2>
        <div class="ui-attention-grid">
            @foreach($items as $item)
                <a href="{{ $item['href'] }}" class="ui-attention-item ui-attention-item--{{ $item['tone'] ?? 'neutral' }}">
                    <strong class="ui-attention-count">{{ (int) $item['count'] }}</strong>
                    <span class="ui-attention-label">{{ $item['label'] }}</span>
                    <span class="ui-attention-arrow" aria-hidden="true">&rarr;</span>
                </a>
            @endforeach
        </div>
    </section>
@endif
