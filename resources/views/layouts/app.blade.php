<!DOCTYPE html>
<html class="compass-ui" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
        @include('layouts.partials.pwa-meta')
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', config('app.name', 'COMPASS'))</title>

        <!-- Fonts -->
        <!-- Styles / Scripts -->
        @vite(['resources/css/app.css', 'resources/css/helper-components.css', 'resources/js/app.js'])

        <style>
            body, body input, body select, body button, body textarea { font-family: 'Inter', sans-serif; }
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
            dialog {
                position: fixed;
                inset: 0;
                margin: auto;
                max-width: calc(100vw - 16px);
                max-height: 92dvh;
                overflow: auto;
            }
            dialog:not([open]) { display: none; }
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
        @if(auth()->user()?->role === 'adviser')
        <link rel="stylesheet" href="{{ asset('css/adviser-refinement.css') }}?v={{ filemtime(public_path('css/adviser-refinement.css')) }}">
        @endif
        @include('partials.ui-assets')
</head>
    <body class="compass-compact font-sans antialiased">
        @auth
            @php($roleSidebar = 'layouts.partials.' . auth()->user()->role . '-sidebar')
            @includeIf($roleSidebar)
            <div class="sidebar-overlay" id="sidebarOverlay"></div>
        @endauth

        <div class="min-h-screen bg-gray-100 {{ auth()->check() ? 'main-content' : '' }}">
            @auth
                <div class="mobile-appbar">
                    <button type="button" class="hamburger" id="hamburgerBtn" aria-label="Open sidebar">
                        <x-ui-icon name="menu"  />
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
        @include('partials.workflow-notice')
        @include('layouts.partials.pwa-banner')
        @stack('scripts')
    </body>
</html>
