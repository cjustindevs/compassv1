@props(['value' => null])
<label {{ $attributes->class(['ui-label']) }}>{{ $value ?? $slot }}</label>
