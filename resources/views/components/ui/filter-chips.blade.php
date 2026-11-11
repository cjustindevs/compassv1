@props(['chips' => [], 'resetHref' => null])
@if(count($chips))
    <div {{ $attributes->merge(['class' => 'ui-chips']) }}>
        @foreach($chips as $chip)
            <a class="ui-chip" href="{{ $chip['href'] }}" aria-label="Remove filter {{ $chip['label'] }}">{{ $chip['label'] }} <span aria-hidden="true">&times;</span></a>
        @endforeach
        @if(count($chips) >= 2 && $resetHref)
            <a class="ui-chip-clear" href="{{ $resetHref }}">Clear all</a>
        @endif
    </div>
@endif
