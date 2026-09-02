{{--
    COMPASS Brand Mark — the logo + wordmark content used in every role sidebar.

    Renders the real COMPASS logo assets (light/dark aware) plus a small role
    subtitle. Intended to be placed INSIDE the shared .sidebar-brand link.

    Usage:
        <a href="{{ route('moderator.dashboard') }}" class="sidebar-brand">
            <x-brand-mark subtitle="Moderator" />
        </a>
--}}
@props([
    'subtitle' => '',
])

<img src="{{ asset('images/compass/logo-icon.png') }}" alt="COMPASS" class="brand-logo-icon" loading="eager">
<span class="brand-block">
    <img src="{{ asset('images/compass/logo-light.png') }}" alt="COMPASS" class="brand-wordmark brand-logo--light" loading="eager">
    <img src="{{ asset('images/compass/logo-dark.png') }}" alt="COMPASS" class="brand-wordmark brand-logo--dark" loading="eager">
    @if($subtitle)
        <span class="brand-sub">{{ $subtitle }}</span>
    @endif
</span>
