@props(['items', 'label'])
{{-- One tab bar style everywhere: items = [['label', 'href', 'active' => bool, 'count' => optional]]. --}}
<nav {{ $attributes->merge(['class' => 'ui-tabs']) }} aria-label="{{ $label }}">
@foreach($items as $item)
<a href="{{ $item['href'] }}" class="ui-tab{{ ! empty($item['active']) ? ' is-active' : '' }}" @if(! empty($item['active'])) aria-current="page" @endif>{{ $item['label'] }}@isset($item['count']) <span>{{ $item['count'] }}</span>@endisset</a>
@endforeach
</nav>
