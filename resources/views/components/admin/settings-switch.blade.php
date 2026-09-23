@props([
    'setting',
    'label',
    'checked' => false,
    'storage' => 'local',
    'disabled' => false,
])

<button
    {{ $attributes->class(['settings-switch', 'is-enabled' => $checked]) }}
    type="button"
    role="switch"
    aria-checked="{{ $checked ? 'true' : 'false' }}"
    aria-label="Toggle {{ $label }}"
    data-settings-switch
    data-setting-key="{{ $setting }}"
    data-setting-storage="{{ $storage }}"
    @disabled($disabled)
>
    <span class="settings-switch-knob" aria-hidden="true"></span>
    <span class="sr-only" data-setting-state>{{ $checked ? 'On' : 'Off' }}</span>
</button>
