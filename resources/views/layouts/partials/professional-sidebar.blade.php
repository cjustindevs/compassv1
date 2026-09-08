{{-- Variables provided by SidebarComposer: $pending, $active, $completed,
     $notifBadge, $displayName, $avatarText, $isAvailable --}}

@include('layouts.partials.sidebar-critical')

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('professional.dashboard') }}" class="sidebar-brand">
            <x-brand-mark />
        </a>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Collapse sidebar" title="Collapse / expand sidebar">
            <i class="fas fa-chevron-left" id="toggleIcon"></i>
        </button>
        {{-- theme toggle moved to Settings → Appearance --}}
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Clinical Work</div>
        <a href="{{ route('professional.dashboard') }}" class="nav-item {{ request()->routeIs('professional.dashboard') ? 'active' : '' }}">
            <i class="fas fa-th-large"></i><span class="nav-text">Dashboard</span>
        </a>
        <a href="{{ route('professional.referrals') }}" class="nav-item {{ request()->routeIs('professional.referrals*', 'professional.referral*') ? 'active' : '' }}">
            <i class="fas fa-clipboard-list"></i><span class="nav-text">Referrals</span>
            @if($pending > 0)
                <span class="nav-badge warning" id="pendingBadge">{{ $pending }}</span>
            @endif
        </a>
        <a href="{{ route('professional.cases') }}" class="nav-item {{ request()->routeIs('professional.cases*') ? 'active' : '' }}">
            <i class="fas fa-folder-open"></i><span class="nav-text">Active Cases</span>
            @if($active > 0)
                <span class="nav-badge" id="activeBadge">{{ $active }}</span>
            @endif
        </a>

        <div class="nav-section">Reports</div>
        <a href="{{ route('professional.reports') }}" class="nav-item {{ request()->routeIs('professional.reports*') ? 'active' : '' }}">
            <i class="fas fa-chart-bar"></i><span class="nav-text">Reports</span>
        </a>

        <div class="nav-section">Account</div>
        <a href="{{ route('professional.profile') }}" class="nav-item {{ request()->routeIs('professional.profile*') ? 'active' : '' }}">
            <i class="fas fa-user-circle"></i><span class="nav-text">Profile</span>
        </a>
        <a href="{{ route('professional.settings') }}" class="nav-item {{ request()->routeIs('professional.settings*') ? 'active' : '' }}">
            <i class="fas fa-cog"></i><span class="nav-text">Settings</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">{{ $avatarText }}</div>
            <div class="user-info">
                <div class="user-name">{{ $displayName }}</div>
                <div class="user-role">Psychology Professional</div>
            </div>
        </div>
        <div class="availability-dot">
            <span class="dot {{ $isAvailable ? 'online' : 'offline' }}"></span>
            {{ $isAvailable ? 'Available for referrals' : 'Unavailable' }}
        </div>
        <div class="user-stats">
            <div class="stat">
                <span class="value" id="sidePending">{{ $pending }}</span>
                <span class="label">Pending</span>
            </div>
            <div class="stat">
                <span class="value" id="sideActive">{{ $active }}</span>
                <span class="label">Active</span>
            </div>
            <div class="stat">
                <span class="value" id="sideCompleted">{{ $completed }}</span>
                <span class="label">Completed</span>
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
