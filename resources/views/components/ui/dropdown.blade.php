@props(['label', 'icon' => null])
{{-- A small menu button (e.g. Export → CSV / PDF). Works without JavaScript; the script only closes it on outside click or Escape. --}}
<details class="ui-dropdown" {{ $attributes }}>
    <summary class="btn-secondary ui-dropdown-toggle" aria-haspopup="menu">@if($icon)<x-ui-icon :name="$icon" /> @endif{{ $label }} <x-ui-icon name="chevron-down" class="ui-dropdown-caret" /></summary>
    <div class="ui-dropdown-menu" role="menu">{{ $slot }}</div>
</details>
@once
@push('scripts')
<script>
(() => {
    const close = (except) => document.querySelectorAll('details.ui-dropdown[open]').forEach((d) => { if (d !== except) d.removeAttribute('open'); });
    document.addEventListener('click', (e) => close(e.target.closest('details.ui-dropdown')));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(null); });
})();
</script>
@endpush
@endonce
