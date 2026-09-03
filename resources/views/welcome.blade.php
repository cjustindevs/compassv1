<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
        @include('layouts.partials.pwa-meta')
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'COMPASS') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Styles / Scripts -->
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-900 min-h-screen flex flex-col">
        <header class="w-full max-w-5xl mx-auto px-6 py-6 flex items-center justify-between">
            <a href="/" class="inline-flex">
                <img src="{{ asset('images/compass/logo-wordmark.png') }}" alt="COMPASS"
                     class="h-9 md:h-12 w-auto">
            </a>

            @if (Route::has('login'))
                <nav class="flex items-center gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg text-emerald-700 hover:bg-emerald-50 text-sm font-medium">
                            Log in
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium">
                                Register
                            </a>
                        @endif
                    @endauth
                </nav>
            @endif
        </header>

        <main class="flex-1 flex items-center justify-center px-6">
            <div class="text-center max-w-xl">
                <div class="inline-flex mx-auto mb-6">
                    <img src="{{ asset('images/compass/logo-icon.png') }}" alt="COMPASS"
                         class="w-28 h-28 md:w-32 md:h-32 rounded-3xl shadow-xl shadow-green-500/20">
                </div>
                <h1 class="text-4xl font-extrabold tracking-tight">Welcome to COMPASS</h1>
                <p class="mt-3 text-lg text-gray-600">
                    {{ \App\Helpers\BrandingHelper::tagline() }}
                </p>
                <p class="mt-2 text-sm text-gray-500">
                    {{ \App\Helpers\BrandingHelper::project() }} &middot; {{ \App\Helpers\BrandingHelper::institution() }}
                </p>

                @auth
                    <div class="mt-8">
                        <a href="{{ route('dashboard') }}" class="inline-block px-6 py-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium shadow-lg shadow-emerald-500/25">
                            Go to Dashboard
                        </a>
                    </div>
                @elseif (Route::has('login'))
                    <div class="mt-8">
                        <a href="{{ route('login') }}" class="inline-block px-6 py-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium shadow-lg shadow-emerald-500/25">
                            Get Started
                        </a>
                    </div>
                @endif
            </div>
        </main>

        <footer class="w-full max-w-5xl mx-auto px-6 py-6 text-center text-xs text-gray-400">
            &copy; {{ date('Y') }} {{ \App\Helpers\BrandingHelper::institution() }}. All rights reserved.
        </footer>

        @include('layouts.partials.pwa-banner')
    </body>
</html>
