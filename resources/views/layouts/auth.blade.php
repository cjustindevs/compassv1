<!DOCTYPE html>
<html class="compass-ui" lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'COMPASS')</title>
    @vite(['resources/css/app.css'])
    @stack('styles')
    @include('partials.ui-assets')
</head>
<body class="compass-compact auth-page">
    <main class="auth-shell w-full @yield('container-width', 'max-w-md')">
        @include('partials.auth-brand')
        <section class="auth-card">
            <div class="auth-content">
                @yield('content')
            </div>
            <footer class="auth-footer">
                @yield('footer')
            </footer>
        </section>
        <p class="auth-supporting-text">Pseudonymous accounts. Supervised peer support.</p>
    </main>
    @include('layouts.partials.pwa-banner')
    @stack('scripts')
</body>
</html>
