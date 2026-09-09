@props(['value' => null])
<i {{ $attributes->class([\App\Support\UiIcon::resolve($value)]) }} aria-hidden="true"></i>
