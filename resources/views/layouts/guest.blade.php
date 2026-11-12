<!DOCTYPE html>
<html class="compass-ui" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
        @include('layouts.partials.pwa-meta')
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'COMPASS') }}</title>

        <!-- Fonts -->
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.ui-assets')
</head>
    <body class="compass-compact auth-page">
        <main @class(['auth-shell w-full', 'max-w-4xl' => request()->routeIs('register', 'seeker.register', 'seeker.consent'), 'max-w-md' => !request()->routeIs('register', 'seeker.register', 'seeker.consent')])>
            @include('partials.auth-brand')

            <section class="auth-card auth-content">
                @hasSection('content')
                    @yield('content')
                @else
                    {{ $slot ?? '' }}
                @endif
            </section>
            <p class="auth-supporting-text"><a href="{{ route('login') }}">Back to sign in</a></p>
        </main>
        @include('components.confirmation-modal')
        @include('layouts.partials.pwa-banner')
    </body>
</html>
