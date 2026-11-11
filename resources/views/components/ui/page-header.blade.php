@props(['title', 'description' => null, 'backHref' => null, 'backLabel' => null])
<header {{ $attributes->merge(['class' => 'ui-page-header']) }} data-page-header>
    <div class="ui-page-heading">
        @if($backHref)
            <a class="ui-back-link" href="{{ $backHref }}">&larr; {{ $backLabel ?? 'Back' }}</a>
        @endif
        <h1>{{ $title }}</h1>
        @if($description)
            <p class="ui-page-desc">{{ $description }}</p>
        @endif
    </div>
    <div class="ui-page-actions">
        @isset($actions){{ $actions }}@endisset
        <div data-notification-slot data-page-slot></div>
    </div>
</header>
