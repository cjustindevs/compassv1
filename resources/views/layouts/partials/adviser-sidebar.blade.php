{{-- Variables provided by SidebarComposer: $evalBadge, $screeningBadge, $referralBadge, $notifBadge,
     $totalHelpers, $activeSessions, $pendingReviews, $avatarText, $displayName --}}

@include('layouts.partials.sidebar-critical')

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">

<a href="{{ route('adviser.dashboard') }}" class="sidebar-brand">
            <x-brand-mark />
        </a>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Collapse sidebar" title="Collapse / expand sidebar">
            <x-ui-icon name="chevron-left" class="sidebar-chevron" id="toggleIcon" />
        </button>
        {{-- theme toggle moved to Settings → Appearance --}}
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Evaluation</div>
        <a href="{{ route('adviser.dashboard') }}" class="nav-item {{ request()->routeIs('adviser.dashboard') ? 'active' : '' }}">
            <x-ui-icon name="dashboard"  /><span class="nav-text">Dashboard</span>
        </a>
        <a href="{{ route('adviser.screenings') }}" class="nav-item {{ request()->routeIs('adviser.screenings*') ? 'active' : '' }}" title="Screening reviews">
            <x-ui-icon name="clipboard"  /><span class="nav-text">Screening reviews</span>
            @if($screeningBadge > 0)
                <span class="nav-badge" id="pendingReviewsBadge">{{ $screeningBadge }}</span>
            @endif
        </a>
        <a href="{{ route('adviser.evaluations') }}" class="nav-item {{ request()->routeIs('adviser.evaluations*', 'adviser.evaluate*') ? 'active' : '' }}">
            <x-ui-icon name="clipboard"  /><span class="nav-text">Pending Evaluations</span>
            @if($evalBadge > 0)
                <span class="nav-badge" id="evalBadge">{{ $evalBadge }}</span>
            @endif
        </a>
        <a href="{{ route('adviser.helpers') }}" class="nav-item {{ request()->routeIs('adviser.helpers*', 'adviser.helper*') ? 'active' : '' }}">
            <x-ui-icon name="users"  /><span class="nav-text">Manage Helpers</span>
        </a>
        <a href="{{ route('adviser.referrals') }}" class="nav-item {{ request()->routeIs('adviser.referrals*', 'adviser.referral*') ? 'active' : '' }}">
            <x-ui-icon name="arrow-right"  /><span class="nav-text">Referral Queue</span>
            @if($referralBadge > 0)
                <span class="nav-badge" id="referralBadge">{{ $referralBadge }}</span>
            @endif
        </a>
        <a href="{{ route('adviser.emergencies') }}" class="nav-item {{ request()->routeIs('adviser.emergencies*') ? 'active' : '' }}">
            <x-ui-icon name="warning"  /><span class="nav-text">Emergencies</span>
        </a>

        <div class="nav-section">Records</div>
        <a href="{{ route('adviser.reports') }}" class="nav-item {{ request()->routeIs('adviser.reports*') ? 'active' : '' }}">
            <x-ui-icon name="bar-chart"  /><span class="nav-text">Reports</span>
        </a>
        <a href="{{ route('adviser.calendar') }}" class="nav-item {{ request()->routeIs('adviser.calendar*') ? 'active' : '' }}">
            <x-ui-icon name="calendar"  /><span class="nav-text">Calendar</span>
        </a>
        <a href="{{ route('adviser.schedule') }}" class="nav-item {{ request()->routeIs('adviser.schedule*') ? 'active' : '' }}">
            <x-ui-icon name="clock"  /><span class="nav-text">Helper Schedules</span>
        </a>
        <a href="{{ route('adviser.transcripts') }}" class="nav-item {{ request()->routeIs('adviser.transcripts*', 'adviser.transcript*') ? 'active' : '' }}">
            <x-ui-icon name="file-text"  /><span class="nav-text">Transcripts</span>
        </a>
        <a href="{{ route('adviser.resources') }}" class="nav-item {{ request()->routeIs('adviser.resources*') ? 'active' : '' }}">
            <x-ui-icon name="book-open"  /><span class="nav-text">Resources</span>
        </a>
<a href="{{ route('concerns.manage') }}" class="nav-item {{ request()->routeIs('concerns.*') ? 'active' : '' }}"><x-ui-icon name="list"  /><span class="nav-text">Areas of concern</span></a>

        <div class="nav-section">Account</div>
        <a href="{{ route('adviser.notifications') }}" class="nav-item {{ request()->routeIs('adviser.notifications*') ? 'active' : '' }}">
            <x-ui-icon name="bell"  /><span class="nav-text">Notifications</span>
            @if($notifBadge > 0)
                <span class="nav-badge" id="notifBadge">{{ $notifBadge }}</span>
            @endif
        </a>
        <a href="{{ route('adviser.settings') }}" class="nav-item {{ request()->routeIs('adviser.settings*') ? 'active' : '' }}">
            <x-ui-icon name="settings"  /><span class="nav-text">Settings</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">{{ $avatarText }}</div>
            <div class="user-info">
                <div class="user-name">{{ $displayName }}</div>
                <div class="user-role">Adviser</div>
            </div>
        </div>
        <div class="user-stats">
            <div class="stat">
                <span class="value" id="totalHelpers">{{ $totalHelpers }}</span>
                <span class="label">Helpers</span>
            </div>
            <div class="stat">
                <span class="value" id="activeSessions">{{ $activeSessions }}</span>
                <span class="label">Active</span>
            </div>
            <div class="stat">
                <span class="value" id="pendingReviews" title="Pending evaluations">{{ $pendingReviews }}</span>
                <span class="label">Pending</span>
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

@once
    @include('partials.emergency-notice')
@endonce
