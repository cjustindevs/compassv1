@props([
    'title' => 'System Overview',
    'pageTitle' => 'System Overview',
    'pageSubtitle' => 'Platform-wide activity and health',
    'admin',
    'searchQuery' => '',
    'activeNav' => 'dashboard',
    'searchAction' => null,
    'searchPlaceholder' => 'Search referrals, cases, users, reports...',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} | COMPASS Admin</title>

    <link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}">
</head>
<body>
    <div class="admin-app" data-admin-app>
        <x-admin.sidebar :admin="$admin" :active="$activeNav" />

        <button class="sidebar-backdrop" type="button" data-sidebar-close aria-label="Close navigation"></button>

        <div class="admin-main">
            <x-admin.header
                :admin="$admin"
                :page-title="$pageTitle"
                :page-subtitle="$pageSubtitle"
                :search-query="$searchQuery"
                :search-action="$searchAction ?? route('admin.dashboard')"
                :search-placeholder="$searchPlaceholder"
            />

            <main class="admin-content" id="main-content">
                {{ $slot }}
            </main>
        </div>
    </div>

    <script src="{{ asset('js/admin-dashboard.js') }}" defer></script>
</body>
</html>
