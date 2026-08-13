@props(['status', 'label'])

<span class="user-status-badge user-status-{{ $status }}">
    <i aria-hidden="true"></i>
    {{ $label }}
</span>
