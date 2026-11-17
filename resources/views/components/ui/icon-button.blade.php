@props(['icon', 'label', 'href' => null, 'type' => 'button', 'tone' => null])
{{-- Icon-only action (View, Edit, Archive, Delete): the label is its accessible name and its tooltip. --}}
@php($class = 'ui-icon-btn'.($tone ? ' ui-icon-btn--'.$tone : ''))
@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $class]) }} aria-label="{{ $label }}" data-tooltip="{{ $label }}"><x-ui-icon :name="$icon" /></a>
@else
<button type="{{ $type }}" {{ $attributes->merge(['class' => $class]) }} aria-label="{{ $label }}" data-tooltip="{{ $label }}"><x-ui-icon :name="$icon" /></button>
@endif
