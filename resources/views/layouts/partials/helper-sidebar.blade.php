@php
    $helper = optional(auth()->user())->helper;
    $helperName = $helper?->full_name ?: auth()->user()?->name ?: 'Helper';

    $totalSessions = $helper ? \App\Models\Session::where('helper_id', $helper->id)->count() : 0;
    $competencyScore = $helper
        ? (int) round((float) optional(\App\Models\HelperCompetencyHistory::where('helper_id', $helper->id)->latest('evaluation_date')->first())->overall_score)
        : 0;
    $availabilityStatus = $helper
        ? ($helper->latestReadiness?->availability_status ?: $helper->status)
        : 'offline';
    $availabilityLabel = ucfirst((string) $availabilityStatus);

    $caseBadgeCount = $helper
        ? \App\Models\Session::where('helper_id', $helper->id)
            ->whereIn('session_status', ['helper_assigned', 'active'])
            ->count()
        : 0;
    $notifBadgeCount = optional(auth()->user())->unreadNotifications()->count();

    $activeSession = $helper
        ? \App\Models\Session::where('helper_id', $helper->id)
            ->whereIn('session_status', ['active', 'helper_assigned'])
            ->latest('created_date')
            ->first()
        : null;
    $voiceUrl = $activeSession ? route('helper.session.voice', ['id' => $activeSession->id]) : route('helper.cases');
    $notesUrl = $activeSession ? route('helper.session.notes', ['id' => $activeSession->id]) : route('helper.cases');
    $initials = \Illuminate\Support\Str::substr($helperName, 0, 2);
@endphp

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('helper.dashboard') }}" class="sidebar-brand">
            <x-brand-mark />
        </a>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Collapse sidebar" title="Collapse / expand sidebar">
            <i class="fas fa-chevron-left" id="toggleIcon"></i>
        </button>
        {{-- theme toggle moved to Settings → Appearance --}}
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Workspace</div>
        <a href="{{ route('helper.dashboard') }}" class="nav-item {{ request()->routeIs('helper.dashboard') ? 'active' : '' }}">
            <i class="fas fa-th-large"></i><span class="nav-text">Dashboard</span>
        </a>
        <a href="{{ route('helper.readiness') }}" class="nav-item {{ request()->routeIs('helper.readiness*') ? 'active' : '' }}">
            <i class="fas fa-heartbeat"></i><span class="nav-text">Readiness Check</span>
        </a>
        <a href="{{ route('helper.cases') }}" class="nav-item {{ request()->routeIs('helper.cases*') ? 'active' : '' }}">
            <i class="fas fa-folder-open"></i><span class="nav-text">Assigned Cases</span>
            <span class="nav-badge" id="caseBadge" style="{{ $caseBadgeCount > 0 ? '' : 'display:none;' }}">{{ $caseBadgeCount }}</span>
        </a>
        <a href="{{ route('helper.chat') }}" class="nav-item {{ request()->routeIs('helper.chat*', 'helper.session.chat*') ? 'active' : '' }}">
            <i class="fas fa-comment-dots"></i><span class="nav-text">Live Chat</span>
        </a>
        <a href="{{ $voiceUrl }}" class="nav-item {{ request()->routeIs('helper.voice', 'helper.session.voice*') ? 'active' : '' }}">
            <i class="fas fa-phone"></i><span class="nav-text">Voice Call</span>
        </a>
        <a href="{{ $notesUrl }}" class="nav-item {{ request()->routeIs('helper.notes', 'helper.session.notes*') ? 'active' : '' }}">
            <i class="fas fa-edit"></i><span class="nav-text">Session Notes</span>
        </a>
        <a href="{{ route('helper.calendar') }}" class="nav-item {{ request()->routeIs('helper.calendar*') ? 'active' : '' }}">
            <i class="fas fa-calendar-alt"></i><span class="nav-text">Calendar</span>
        </a>

        <div class="nav-section">Growth</div>
        <a href="{{ route('helper.competency') }}" class="nav-item {{ request()->routeIs('helper.competency*') ? 'active' : '' }}">
            <i class="fas fa-chart-line"></i><span class="nav-text">Competency</span>
        </a>
        <a href="{{ route('helper.resources') }}" class="nav-item {{ request()->routeIs('helper.resources*') ? 'active' : '' }}">
            <i class="fas fa-book"></i><span class="nav-text">Resources</span>
        </a>

        <div class="nav-section">Account</div>
        <a href="{{ route('helper.notifications') }}" class="nav-item {{ request()->routeIs('helper.notifications*') ? 'active' : '' }}">
            <i class="fas fa-bell"></i><span class="nav-text">Notifications</span>
            <span class="nav-badge danger" id="notifBadge" style="{{ $notifBadgeCount > 0 ? '' : 'display:none;' }}">{{ $notifBadgeCount }}</span>
        </a>
        <a href="{{ route('helper.profile') }}" class="nav-item {{ request()->routeIs('helper.profile*') ? 'active' : '' }}">
            <i class="fas fa-user-circle"></i><span class="nav-text">Profile</span>
        </a>
        <a href="{{ route('helper.settings') }}" class="nav-item {{ request()->routeIs('helper.settings*') ? 'active' : '' }}">
            <i class="fas fa-cog"></i><span class="nav-text">Settings</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">{{ $initials }}</div>
            <div class="user-info">
                <div class="user-name">{{ $helperName }}</div>
                <div class="user-role">Psychology Helper</div>
            </div>
        </div>
        <div class="user-stats">
            <div class="stat">
                <span class="value" id="sessionCount">{{ $totalSessions }}</span>
                <span class="label">Sessions</span>
            </div>
            <div class="stat">
                <span class="value" id="compScore">{{ $competencyScore }}%</span>
                <span class="label">Competency</span>
            </div>
            <div class="stat">
                <span class="value" id="availStatus" style="color: {{ $availabilityStatus === 'available' ? 'var(--green-500)' : 'var(--yellow-500)' }};">{{ $availabilityLabel }}</span>
                <span class="label">Status</span>
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
