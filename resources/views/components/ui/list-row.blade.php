@props(['initials', 'title', 'subtitle' => null])
<div {{ $attributes->merge(['class' => 'ui-list-row']) }}>
    <span class="ui-avatar" aria-hidden="true">{{ $initials }}</span>
    <div class="ui-list-main">
        <p class="ui-list-title">{{ $title }}</p>
        @if($subtitle)<p class="ui-list-sub">{{ $subtitle }}</p>@endif
    </div>
    @if(isset($badge) || isset($actions))
        <div class="ui-list-side">
            @isset($badge)<span class="ui-list-badge">{{ $badge }}</span>@endisset
            @isset($actions)<div class="ui-list-actions">{{ $actions }}</div>@endisset
        </div>
    @endif
</div>
