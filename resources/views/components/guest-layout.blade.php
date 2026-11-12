<!DOCTYPE html>
<html class="compass-ui" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'COMPASS') }}</title>

    <!-- Fonts -->
    <!-- Tailwind -->

    @vite(['resources/css/app.css'])
    @include('partials.ui-assets')
</head>
<body class="auth-page">
    <main class="auth-shell w-full max-w-md">
        @include('partials.auth-brand')
        <section class="auth-card auth-content">
        {{ $slot }}
        </section>
    </main>
    @include('layouts.partials.pwa-banner')
</body>
</html>
