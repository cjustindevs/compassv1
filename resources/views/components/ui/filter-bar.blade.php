@props(['action' => null])
<form method="GET" @if($action) action="{{ $action }}" @endif {{ $attributes->merge(['class' => 'ui-filter-bar']) }}>
    {{ $slot }}
    <div class="ui-filter-buttons">
        @isset($buttons){{ $buttons }}@else<button type="submit" class="btn-primary">Apply</button>@endisset
    </div>
</form>
