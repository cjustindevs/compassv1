@props([
    'categoryId',
    'categoryName',
    'action',
    'checked' => false,
])

<button
    {{ $attributes->class(['permission-switch', 'is-enabled' => $checked]) }}
    type="button"
    role="switch"
    aria-checked="{{ $checked ? 'true' : 'false' }}"
    aria-label="Toggle {{ Str::headline($action) }} permission for {{ $categoryName }}"
    data-permission-switch
    data-category="{{ $categoryId }}"
    data-action="{{ $action }}"
>
    <span class="permission-switch-knob" aria-hidden="true"></span>
</button>
