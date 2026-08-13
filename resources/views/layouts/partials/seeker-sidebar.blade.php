{{--
    COMPASS Seeker Sidebar — thin wrapper around the canonical seeker sidebar.
    See resources/views/partials/sidebar.blade.php for the full implementation.
--}}
@include('partials.sidebar', [
    'active' => $active ?? [],
    'role' => $role ?? 'Help Seeker',
    'badgeNotifications' => $badgeNotifications ?? (int) optional(auth()->user())->unreadNotifications()->count(),
    'badgeSessions' => $badgeSessions ?? 0,
    'dashboardTabs' => $dashboardTabs ?? false,
])
