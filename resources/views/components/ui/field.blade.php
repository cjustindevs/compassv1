@props(['label', 'for'])
<div {{ $attributes->merge(['class' => 'ui-field']) }}>
    <label for="{{ $for }}">{{ $label }}</label>
    {{ $slot }}
</div>
