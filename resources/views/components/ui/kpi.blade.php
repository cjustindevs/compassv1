@props(['label', 'value', 'tone' => 'neutral', 'note' => null, 'href' => null, 'icon' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'ui-kpi ui-kpi--'.$tone.($icon ? ' ui-kpi--icon' : '')]) }}>
    @if($icon)
    <span class="ui-kpi-icon" aria-hidden="true"><x-ui-icon :name="$icon" /></span>
    <span class="ui-kpi-body">
    @endif
    <span class="ui-kpi-label">{{ $label }}</span>
    <strong class="ui-kpi-value">{{ $value }}</strong>
    @if($note)<small class="ui-kpi-note">{{ $note }}</small>@endif
    @if($icon)
    </span>
    @endif
</{{ $tag }}>
