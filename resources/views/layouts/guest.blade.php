<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
        @include('layouts.partials.pwa-meta')
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'COMPASS') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="compass-compact font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div>
                <a href="/" class="inline-flex">
                    <img src="{{ asset('images/compass/logo-wordmark.png') }}" alt="COMPASS"
                         class="h-12 sm:h-16 w-auto">
                </a>
            </div>

            <div @class(['w-full mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg', 'sm:max-w-4xl' => request()->routeIs('register', 'seeker.register', 'seeker.consent'), 'sm:max-w-md' => !request()->routeIs('register', 'seeker.register', 'seeker.consent')])>
                @hasSection('content')
                    @yield('content')
                @else
                    {{ $slot ?? '' }}
                @endif
            </div>
        </div>
        @include('components.confirmation-modal')
        @include('layouts.partials.pwa-banner')
    </body>
</html>
