<header class="auth-brand">
    <a href="{{ url('/') }}" aria-label="COMPASS home">
        <img src="{{ asset('images/compass/logo-wordmark.png') }}" alt="COMPASS" width="200" height="48">
    </a>
    @hasSection('subtitle')<p>@yield('subtitle')</p>@endif
</header>
