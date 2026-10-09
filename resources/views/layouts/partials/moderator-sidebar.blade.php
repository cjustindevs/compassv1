{{-- Variables provided by SidebarComposer: $queueCount, $sessionCount, $emergencyCount,
     $notifBadge, $avatarText, $displayName --}}

@include('layouts.partials.sidebar-critical')

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('moderator.dashboard') }}" class="sidebar-brand">
            <x-brand-mark />
        </a>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Collapse sidebar" title="Collapse / expand sidebar">
            <x-ui-icon name="chevron-left" class="sidebar-chevron" id="toggleIcon" />
        </button>
        {{-- theme toggle moved to Settings → Appearance --}}
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Operations</div>
        <a href="{{ route('moderator.dashboard') }}" class="nav-item {{ request()->routeIs('moderator.dashboard') ? 'active' : '' }}">
            <x-ui-icon name="dashboard"  /><span class="nav-text">Dashboard</span>
        </a>
        <a href="{{ route('moderator.queue') }}" class="nav-item {{ request()->routeIs('moderator.queue*') ? 'active' : '' }}">
            <x-ui-icon name="hourglass"  /><span class="nav-text">Incoming Queue</span>
            @if($queueCount > 0)
                <span class="nav-badge" id="queueBadge">{{ $queueCount }}</span>
            @endif
        </a>
        <a href="{{ route('moderator.sessions') }}" class="nav-item {{ request()->routeIs('moderator.sessions*') ? 'active' : '' }}">
            <x-ui-icon name="message"  /><span class="nav-text">Active Sessions</span>
            @if($sessionCount > 0)
                <span class="nav-badge" id="sessionBadge">{{ $sessionCount }}</span>
            @endif
        </a>
        <a href="{{ route('moderator.reconnections') }}" class="nav-item {{ request()->routeIs('moderator.reconnections') ? 'active' : '' }}" title="Connection review"><x-ui-icon name="plug"  /><span class="nav-text">Connection review</span></a>
        <a href="{{ route('moderator.manage') }}" class="nav-item {{ request()->routeIs('moderator.manage*') ? 'active' : '' }}">
            <x-ui-icon name="users"  /><span class="nav-text">Manage</span>
        </a>
        <a href="{{ route('moderator.schedules') }}" class="nav-item {{ request()->routeIs('moderator.schedules*') ? 'active' : '' }}">
            <x-ui-icon name="calendar"  /><span class="nav-text">Schedules</span>
        </a>
        <a href="{{ route('moderator.emergency') }}" class="nav-item {{ request()->routeIs('moderator.emergency*') ? 'active' : '' }}">
            <x-ui-icon name="warning"  /><span class="nav-text">Emergency Alerts</span>
            @if($emergencyCount > 0)
                <span class="nav-badge danger" id="emergencyBadge">{{ $emergencyCount }}</span>
            @endif
        </a>

        <div class="nav-section">Records</div>
        <a href="{{ route('moderator.reports') }}" class="nav-item {{ request()->routeIs('moderator.reports*') ? 'active' : '' }}">
            <x-ui-icon name="file-text"  /><span class="nav-text">Reports</span>
        </a>

        <div class="nav-section">Account</div>
        <a href="{{ route('moderator.notifications') }}" class="nav-item {{ request()->routeIs('moderator.notifications*') ? 'active' : '' }}">
            <x-ui-icon name="bell"  /><span class="nav-text">Notifications</span>
            @if($notifBadge > 0)
                <span class="nav-badge" id="notifBadge">{{ $notifBadge }}</span>
            @endif
        </a>
        <a href="{{ route('moderator.settings') }}" class="nav-item {{ request()->routeIs('moderator.settings*') ? 'active' : '' }}">
            <x-ui-icon name="settings"  /><span class="nav-text">Settings</span>
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
                <span class="value" id="liveCount">{{ $sessionCount }}</span>
                <span class="label">Live</span>
            </div>
            <div class="stat">
                <span class="value" id="queueCount">{{ $queueCount }}</span>
                <span class="label">Queue</span>
            </div>
            <div class="stat">
                <span class="value" id="emergencyCount">{{ $emergencyCount }}</span>
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
                <x-ui-icon name="logout"  /><span class="logout-text">Log out</span>
            </button>
        </form>
    </div>
</aside>

@include('components.confirmation-modal')

@once
    @include('partials.emergency-notice')
@endonce
