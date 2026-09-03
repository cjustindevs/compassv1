@php
    $adviserHelperIds = \App\Models\Helper::where('adviser_id', optional(auth()->user()->adviser)->id)->pluck('id');
    $adviserBadges = [
        'evalBadge' => \App\Models\SessionReport::where('adviser_reviewed', false)->whereHas('session', fn ($query) => $query->whereIn('helper_id', $adviserHelperIds))->count(),
        'referralBadge' => \App\Models\Referral::where('status', 'pending_adviser')->whereIn('helper_id', $adviserHelperIds)->count(),
        'notifBadge' => optional(auth()->user())->unreadNotifications()->count() ?? 0,
        'totalHelpers' => $adviserHelperIds->count(),
        'activeSessions' => \App\Models\Session::whereIn('helper_id', $adviserHelperIds)->where('session_status', 'active')->count(),
        'pendingReviews' => \App\Models\SessionReport::where('adviser_reviewed', false)->whereHas('session', fn ($query) => $query->whereIn('helper_id', $adviserHelperIds))->count(),
    ];
    $user = auth()->user();
    $avatarText = optional($user->adviser)->first_name
        ? substr($user->adviser->first_name, 0, 1) . substr($user->adviser->last_name, 0, 1)
        : strtoupper(substr($user->name, 0, 2));
    $displayName = optional($user->adviser)->full_name ?? $user->name;
@endphp

@include('layouts.partials.sidebar-critical')

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('adviser.dashboard') }}" class="sidebar-brand">
            <x-brand-mark />
        </a>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Collapse sidebar" title="Collapse / expand sidebar">
            <i class="fas fa-chevron-left" id="toggleIcon"></i>
        </button>
        {{-- theme toggle moved to Settings → Appearance --}}
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Evaluation</div>
        <a href="{{ route('adviser.dashboard') }}" class="nav-item {{ request()->routeIs('adviser.dashboard') ? 'active' : '' }}">
            <i class="fas fa-th-large"></i><span class="nav-text">Dashboard</span>
        </a>
        <a href="{{ route('adviser.evaluations') }}" class="nav-item {{ request()->routeIs('adviser.evaluations*', 'adviser.evaluate*') ? 'active' : '' }}">
            <i class="fas fa-clipboard-list"></i><span class="nav-text">Pending Evaluations</span>
            @if($adviserBadges['evalBadge'] > 0)
                <span class="nav-badge" id="evalBadge">{{ $adviserBadges['evalBadge'] }}</span>
            @endif
        </a>
        <a href="{{ route('adviser.helpers') }}" class="nav-item {{ request()->routeIs('adviser.helpers*', 'adviser.helper*') ? 'active' : '' }}">
            <i class="fas fa-users"></i><span class="nav-text">Manage Helpers</span>
        </a>
        <a href="{{ route('adviser.referrals') }}" class="nav-item {{ request()->routeIs('adviser.referrals*', 'adviser.referral*') ? 'active' : '' }}">
            <i class="fas fa-arrow-right"></i><span class="nav-text">Referral Queue</span>
            @if($adviserBadges['referralBadge'] > 0)
                <span class="nav-badge" id="referralBadge">{{ $adviserBadges['referralBadge'] }}</span>
            @endif
        </a>
        <a href="{{ route('adviser.emergencies') }}" class="nav-item {{ request()->routeIs('adviser.emergencies*') ? 'active' : '' }}">
            <i class="fas fa-exclamation-triangle"></i><span class="nav-text">Emergencies</span>
        </a>

        <div class="nav-section">Records</div>
        <a href="{{ route('adviser.reports') }}" class="nav-item {{ request()->routeIs('adviser.reports*') ? 'active' : '' }}">
            <i class="fas fa-chart-bar"></i><span class="nav-text">Reports</span>
        </a>
        <a href="{{ route('adviser.calendar') }}" class="nav-item {{ request()->routeIs('adviser.calendar*') ? 'active' : '' }}">
            <i class="fas fa-calendar-alt"></i><span class="nav-text">Calendar</span>
        </a>
        <a href="{{ route('adviser.schedule') }}" class="nav-item {{ request()->routeIs('adviser.schedule*') ? 'active' : '' }}">
            <i class="fas fa-clock"></i><span class="nav-text">Helper Schedules</span>
        </a>
        <a href="{{ route('adviser.transcripts') }}" class="nav-item {{ request()->routeIs('adviser.transcripts*', 'adviser.transcript*') ? 'active' : '' }}">
            <i class="fas fa-file-alt"></i><span class="nav-text">Transcripts</span>
        </a>
        <a href="{{ route('adviser.resources') }}" class="nav-item {{ request()->routeIs('adviser.resources*') ? 'active' : '' }}">
            <i class="fas fa-book"></i><span class="nav-text">Resources</span>
        </a>

        <div class="nav-section">Account</div>
        <a href="{{ route('adviser.notifications') }}" class="nav-item {{ request()->routeIs('adviser.notifications*') ? 'active' : '' }}">
            <i class="fas fa-bell"></i><span class="nav-text">Notifications</span>
            @if($adviserBadges['notifBadge'] > 0)
                <span class="nav-badge" id="notifBadge">{{ $adviserBadges['notifBadge'] }}</span>
            @endif
        </a>
        <a href="{{ route('adviser.settings') }}" class="nav-item {{ request()->routeIs('adviser.settings*') ? 'active' : '' }}">
            <i class="fas fa-cog"></i><span class="nav-text">Settings</span>
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
                <span class="value" id="totalHelpers">{{ $adviserBadges['totalHelpers'] }}</span>
                <span class="label">Helpers</span>
            </div>
            <div class="stat">
                <span class="value" id="activeSessions">{{ $adviserBadges['activeSessions'] }}</span>
                <span class="label">Active</span>
            </div>
            <div class="stat">
                <span class="value" id="pendingReviews">{{ $adviserBadges['pendingReviews'] }}</span>
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
                <i class="fas fa-sign-out-alt"></i><span class="logout-text">Log out</span>
            </button>
        </form>
    </div>
</aside>
