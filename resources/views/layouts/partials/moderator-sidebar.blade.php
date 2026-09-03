@php
    $moderatorBadges = [
        'queueBadge' => \App\Models\QueueRequest::where('request_status', 'waiting')->count(),
        'sessionBadge' => \App\Models\Session::where('session_status', 'active')->count(),
        'emergencyBadge' => \App\Models\IncidentReport::whereIn('status', ['open', 'under_review', 'escalated'])->count(),
        'notifBadge' => optional(auth()->user())->unreadNotifications()->count() ?? 0,
        'liveCount' => \App\Models\Session::where('session_status', 'active')->count(),
        'queueCount' => \App\Models\QueueRequest::where('request_status', 'waiting')->count(),
        'emergencyCount' => \App\Models\IncidentReport::whereIn('status', ['open', 'under_review', 'escalated'])->count(),
    ];
    $user = auth()->user();
    $avatarText = optional($user->moderator)->first_name
        ? substr($user->moderator->first_name, 0, 1) . substr($user->moderator->last_name, 0, 1)
        : strtoupper(substr($user->name, 0, 2));
    $displayName = optional($user->moderator)->full_name ?? $user->name;
@endphp

@include('layouts.partials.sidebar-critical')

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('moderator.dashboard') }}" class="sidebar-brand">
            <x-brand-mark />
        </a>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Collapse sidebar" title="Collapse / expand sidebar">
            <i class="fas fa-chevron-left" id="toggleIcon"></i>
        </button>
        {{-- theme toggle moved to Settings → Appearance --}}
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Operations</div>
        <a href="{{ route('moderator.dashboard') }}" class="nav-item {{ request()->routeIs('moderator.dashboard') ? 'active' : '' }}">
            <i class="fas fa-th-large"></i><span class="nav-text">Dashboard</span>
        </a>
        <a href="{{ route('moderator.queue') }}" class="nav-item {{ request()->routeIs('moderator.queue*') ? 'active' : '' }}">
            <i class="fas fa-hourglass-half"></i><span class="nav-text">Incoming Queue</span>
            @if($moderatorBadges['queueBadge'] > 0)
                <span class="nav-badge" id="queueBadge">{{ $moderatorBadges['queueBadge'] }}</span>
            @endif
        </a>
        <a href="{{ route('moderator.sessions') }}" class="nav-item {{ request()->routeIs('moderator.sessions*') ? 'active' : '' }}">
            <i class="fas fa-comments"></i><span class="nav-text">Active Sessions</span>
            @if($moderatorBadges['sessionBadge'] > 0)
                <span class="nav-badge" id="sessionBadge">{{ $moderatorBadges['sessionBadge'] }}</span>
            @endif
        </a>
        <a href="{{ route('moderator.manage') }}" class="nav-item {{ request()->routeIs('moderator.manage*') ? 'active' : '' }}">
            <i class="fas fa-users-cog"></i><span class="nav-text">Manage</span>
        </a>
        <a href="{{ route('moderator.schedules') }}" class="nav-item {{ request()->routeIs('moderator.schedules*') ? 'active' : '' }}">
            <i class="fas fa-calendar-alt"></i><span class="nav-text">Schedules</span>
        </a>
        <a href="{{ route('moderator.emergency') }}" class="nav-item {{ request()->routeIs('moderator.emergency*') ? 'active' : '' }}">
            <i class="fas fa-exclamation-triangle"></i><span class="nav-text">Emergency Alerts</span>
            @if($moderatorBadges['emergencyBadge'] > 0)
                <span class="nav-badge danger" id="emergencyBadge">{{ $moderatorBadges['emergencyBadge'] }}</span>
            @endif
        </a>

        <div class="nav-section">Analytics</div>
        <a href="{{ route('moderator.analytics') }}" class="nav-item {{ request()->routeIs('moderator.analytics*') ? 'active' : '' }}">
            <i class="fas fa-chart-line"></i><span class="nav-text">Analytics</span>
        </a>
        <a href="{{ route('moderator.reports') }}" class="nav-item {{ request()->routeIs('moderator.reports*') ? 'active' : '' }}">
            <i class="fas fa-file-alt"></i><span class="nav-text">Reports</span>
        </a>

        <div class="nav-section">Account</div>
        <a href="{{ route('moderator.notifications') }}" class="nav-item {{ request()->routeIs('moderator.notifications*') ? 'active' : '' }}">
            <i class="fas fa-bell"></i><span class="nav-text">Notifications</span>
            @if($moderatorBadges['notifBadge'] > 0)
                <span class="nav-badge" id="notifBadge">{{ $moderatorBadges['notifBadge'] }}</span>
            @endif
        </a>
        <a href="{{ route('moderator.settings') }}" class="nav-item {{ request()->routeIs('moderator.settings*') ? 'active' : '' }}">
            <i class="fas fa-cog"></i><span class="nav-text">Settings</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">{{ $avatarText }}</div>
            <div class="user-info">
                <div class="user-name">{{ $displayName }}</div>
                <div class="user-role">Moderator</div>
            </div>
        </div>
        <div class="user-stats">
            <div class="stat">
                <span class="value" id="liveCount">{{ $moderatorBadges['liveCount'] }}</span>
                <span class="label">Live</span>
            </div>
            <div class="stat">
                <span class="value" id="queueCount">{{ $moderatorBadges['queueCount'] }}</span>
                <span class="label">Queue</span>
            </div>
            <div class="stat">
                <span class="value" id="emergencyCount">{{ $moderatorBadges['emergencyCount'] }}</span>
                <span class="label">Emergency</span>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}"
              data-confirm="Log out?"
              data-confirm-message="You will be signed out of your COMPASS account."
              data-confirm-text="Log out"
              data-confirm-class="bg-red-600 hover:bg-red-700 focus:ring-red-500">
            @csrf
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i><span class="logout-text">Log out</span>
            </button>
        </form>
    </div>
</aside>

@include('components.confirmation-modal')
