<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
        @include('layouts.partials.pwa-meta')
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'COMPASS'))</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

        <!-- Styles / Scripts -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            body, body input, body select, body button, body textarea { font-family: 'Inter', sans-serif; }
            .fa, .fas, .far, .fab, .fa-solid, .fa-regular, .fa-brands, .fa-solid::before, .fas::before {
                font-family: 'Font Awesome 6 Free' !important;
            }
            .mobile-appbar {
                display: none;
                align-items: center;
                padding: 12px 16px;
                background: #ffffff;
                border-bottom: 1px solid #e5e7eb;
                position: sticky;
                top: 0;
                z-index: 90;
            }
            .hamburger {
                display: none;
                background: #ffffff;
                border: 1px solid #e5e7eb;
                font-size: 20px;
                color: #374151;
                cursor: pointer;
                width: 40px;
                height: 40px;
                border-radius: 10px;
                align-items: center;
                justify-content: center;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                flex-shrink: 0;
            }
            @media (max-width: 768px) {
                .mobile-appbar { display: flex; }
                .hamburger { display: inline-flex; }
            }
        </style>

        @stack('styles')
    </head>
    <body class="font-sans antialiased">
        @auth
            @php($roleSidebar = 'layouts.partials.' . auth()->user()->role . '-sidebar')
            @includeIf($roleSidebar)
            <div class="sidebar-overlay" id="sidebarOverlay"></div>
        @endauth

        <div class="min-h-screen bg-gray-100 {{ auth()->check() ? 'main-content' : '' }}">
            @auth
                <div class="mobile-appbar">
                    <button type="button" class="hamburger" id="hamburgerBtn" aria-label="Open sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            @endauth

            <!-- Page Content -->
            <main>
                @hasSection('content')
                    @yield('content')
                @else
                    {{ $slot ?? '' }}
                @endif
            </main>
        </div>
        @include('components.confirmation-modal')
        @include('layouts.partials.pwa-banner')
        @stack('scripts')
    </body>
</html>
