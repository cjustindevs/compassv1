@props(['title', 'text' => null])
<div {{ $attributes->merge(['class' => 'ui-empty']) }}>
    <p class="ui-empty-title">{{ $title }}</p>
    @if($text)<p class="ui-empty-text">{{ $text }}</p>@endif
    @if(trim((string) $slot) !== '')<div class="ui-empty-action">{{ $slot }}</div>@endif
</div>
