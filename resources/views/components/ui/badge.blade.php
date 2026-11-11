@props(['tone' => 'neutral'])
<span {{ $attributes->merge(['class' => 'ui-badge ui-badge--'.$tone]) }}>{{ $slot }}</span>
