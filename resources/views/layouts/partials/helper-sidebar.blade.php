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
@endphp

<aside class="sidebar" id="sidebar">
    <div class="logo">
        <div class="icon">C</div>
        <span>COMPASS</span>
    </div>

    <nav class="nav">
        <p class="nav-label">Workspace</p>
        <a href="{{ route('helper.dashboard') }}" class="nav-item {{ request()->routeIs('helper.dashboard') ? 'active' : '' }}">
            <i class="fas fa-th-large"></i> Dashboard
        </a>
        <a href="{{ route('helper.readiness') }}" class="nav-item {{ request()->routeIs('helper.readiness*') ? 'active' : '' }}">
            <i class="fas fa-heartbeat"></i> Readiness Check
        </a>
        <a href="{{ route('helper.cases') }}" class="nav-item {{ request()->routeIs('helper.cases*') ? 'active' : '' }}">
            <i class="fas fa-folder-open"></i> Assigned Cases
            <span class="badge" id="caseBadge" style="{{ $caseBadgeCount > 0 ? '' : 'display:none;' }}">{{ $caseBadgeCount }}</span>
        </a>
        <a href="{{ route('helper.chat') }}" class="nav-item {{ request()->routeIs('helper.chat*', 'helper.session.chat*') ? 'active' : '' }}">
            <i class="fas fa-comment-dots"></i> Live Chat
        </a>
        <a href="{{ $voiceUrl }}" class="nav-item {{ request()->routeIs('helper.voice', 'helper.session.voice*') ? 'active' : '' }}">
            <i class="fas fa-phone"></i> Voice Call
        </a>
        <a href="{{ $notesUrl }}" class="nav-item {{ request()->routeIs('helper.notes', 'helper.session.notes*') ? 'active' : '' }}">
            <i class="fas fa-edit"></i> Session Notes
        </a>
        <a href="{{ route('helper.calendar') }}" class="nav-item {{ request()->routeIs('helper.calendar*') ? 'active' : '' }}">
            <i class="fas fa-calendar-alt"></i> Calendar
        </a>

        <p class="nav-label">Growth</p>
        <a href="{{ route('helper.competency') }}" class="nav-item {{ request()->routeIs('helper.competency*') ? 'active' : '' }}">
            <i class="fas fa-chart-line"></i> Competency
        </a>
        <a href="{{ route('helper.resources') }}" class="nav-item {{ request()->routeIs('helper.resources*') ? 'active' : '' }}">
            <i class="fas fa-book"></i> Resources
        </a>

        <p class="nav-label">Account</p>
        <a href="{{ route('helper.notifications') }}" class="nav-item {{ request()->routeIs('helper.notifications*') ? 'active' : '' }}">
            <i class="fas fa-bell"></i> Notifications
            <span class="badge danger" id="notifBadge" style="{{ $notifBadgeCount > 0 ? '' : 'display:none;' }}">{{ $notifBadgeCount }}</span>
        </a>
        <a href="{{ route('helper.profile') }}" class="nav-item {{ request()->routeIs('helper.profile*') ? 'active' : '' }}">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        <a href="{{ route('helper.settings') }}" class="nav-item {{ request()->routeIs('helper.settings*') ? 'active' : '' }}">
            <i class="fas fa-cog"></i> Settings
        </a>
    </nav>

    <div class="user-section">
        <div class="user-card">
            <div class="avatar">{{ \Illuminate\Support\Str::substr($helperName, 0, 2) }}</div>
            <div class="info">
                <div class="name">{{ $helperName }}</div>
                <div class="role">Psychology Helper</div>
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
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </form>
    </div>
</aside>
