@props(['title' => null, 'subtitle' => null, 'id' => null])
<section {{ $attributes->merge(['class' => 'ui-card']) }} @if($id) id="{{ $id }}" @endif>
    @if($title || $subtitle || isset($actions))
        <div class="ui-card-head">
            <div>
                @if($title)<h2 class="ui-card-title">{{ $title }}</h2>@endif
                @if($subtitle)<p class="ui-card-sub">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="ui-card-actions">{{ $actions }}</div>@endisset
        </div>
    @endif
    {{ $slot }}
</section>
