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

    <script>
        (() => {
            const root = document.documentElement;
            const fallbackTheme = @js($admin->dark_mode ? 'dark' : 'light');
            const allowedThemes = ['light', 'dark', 'system'];
            const allowedAccents = ['green', 'cyan', 'mint', 'orange', 'red'];

            try {
                const preference = localStorage.getItem('compass-admin-theme') || fallbackTheme;
                const theme = allowedThemes.includes(preference) ? preference : fallbackTheme;
                const resolvedTheme = theme === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : theme;
                const savedAccent = localStorage.getItem('compass-admin-accent') || 'green';

                root.dataset.adminThemePreference = theme;
                root.dataset.adminTheme = resolvedTheme;
                root.dataset.adminAccent = allowedAccents.includes(savedAccent) ? savedAccent : 'green';
                root.classList.toggle('admin-reduced-motion', localStorage.getItem('compass-admin-reduced-motion') === 'true');
            } catch (error) {
                root.dataset.adminThemePreference = fallbackTheme;
                root.dataset.adminTheme = fallbackTheme;
                root.dataset.adminAccent = 'green';
            }
        })();
    </script>

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
