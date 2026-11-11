@props(['label', 'value', 'tone' => 'neutral', 'note' => null, 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'ui-kpi ui-kpi--'.$tone]) }}>
    <span class="ui-kpi-label">{{ $label }}</span>
    <strong class="ui-kpi-value">{{ $value }}</strong>
    @if($note)<small class="ui-kpi-note">{{ $note }}</small>@endif
</{{ $tag }}>
