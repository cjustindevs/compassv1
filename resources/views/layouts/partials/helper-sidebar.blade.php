{{-- Variables provided by SidebarComposer: $helper, $helperName, $assignedAdviser, $totalSessions, $competencyScore,
     $availabilityStatus, $availabilityLabel, $caseBadgeCount, $notifBadgeCount,
     $activeSession, $voiceUrl, $notesUrl, $initials --}}

@include('layouts.partials.sidebar-critical')

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('helper.dashboard') }}" class="sidebar-brand">
            <x-brand-mark />
        </a>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Collapse sidebar" title="Collapse / expand sidebar">
            <x-ui-icon name="chevron-left" class="sidebar-chevron" id="toggleIcon" />
        </button>
        {{-- theme toggle moved to Settings → Appearance --}}
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Workspace</div>
        <a href="{{ route('helper.dashboard') }}" class="nav-item {{ request()->routeIs('helper.dashboard') ? 'active' : '' }}">
            <x-ui-icon name="dashboard"  /><span class="nav-text">Dashboard</span>
        </a>
        <a href="{{ route('helper.readiness') }}" class="nav-item {{ request()->routeIs('helper.readiness*') ? 'active' : '' }}">
            <x-ui-icon name="heart-pulse"  /><span class="nav-text">Readiness Check</span>
        </a>
        <a href="{{ route('helper.cases') }}" class="nav-item {{ request()->routeIs('helper.cases*') ? 'active' : '' }}">
            <x-ui-icon name="folder"  /><span class="nav-text">Assigned Cases</span>
            <span class="nav-badge" id="caseBadge" style="{{ $caseBadgeCount > 0 ? '' : 'display:none;' }}">{{ $caseBadgeCount }}</span>
        </a>
        <a href="{{ route('helper.reconnections') }}" class="nav-item {{ request()->routeIs('helper.reconnections') ? 'active' : '' }}" title="Connection review"><x-ui-icon name="plug"  /><span class="nav-text">Connection review</span></a>

        <a href="{{ route('helper.calendar') }}" class="nav-item {{ request()->routeIs('helper.calendar*') ? 'active' : '' }}">
            <x-ui-icon name="calendar"  /><span class="nav-text">Calendar</span>
        </a>

        <div class="nav-section">Growth</div>
        <a href="{{ route('helper.competency') }}" class="nav-item {{ request()->routeIs('helper.competency*') ? 'active' : '' }}">
            <x-ui-icon name="chart-line"  /><span class="nav-text">Competency</span>
        </a>
        <a href="{{ route('helper.feedback') }}" class="nav-item {{ request()->routeIs('helper.feedback*') ? 'active' : '' }}">
            <x-ui-icon name="star"  /><span class="nav-text">Feedback</span>
        </a>
        <a href="{{ route('helper.resources') }}" class="nav-item {{ request()->routeIs('helper.resources*') ? 'active' : '' }}">
            <x-ui-icon name="book-open"  /><span class="nav-text">Resources</span>
        </a>

        <a href="{{ route('helper.reports') }}" class="nav-item {{ request()->routeIs('helper.reports') ? 'active' : '' }}"><x-ui-icon name="file-text"  /><span class="nav-text">Reports</span></a>

        <div class="nav-section">Account</div>
        <a href="{{ route('helper.notifications') }}" class="nav-item {{ request()->routeIs('helper.notifications*') ? 'active' : '' }}">
            <x-ui-icon name="bell"  /><span class="nav-text">Notifications</span>
            <span class="nav-badge danger" id="notifBadge" style="{{ $notifBadgeCount > 0 ? '' : 'display:none;' }}">{{ $notifBadgeCount }}</span>
        </a>
        <a href="{{ route('helper.profile') }}" class="nav-item {{ request()->routeIs('helper.profile*') ? 'active' : '' }}">
            <x-ui-icon name="user"  /><span class="nav-text">Profile</span>
        </a>
        <a href="{{ route('helper.settings') }}" class="nav-item {{ request()->routeIs('helper.settings*') ? 'active' : '' }}">
            <x-ui-icon name="settings"  /><span class="nav-text">Settings</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar">{{ $initials }}</div>
            <div class="user-info">
                <div class="user-name">{{ $helperName }}</div>
                <div class="user-role">{{ $assignedAdviser ? 'Supervised by ' . $assignedAdviser->full_name : 'Psychology Helper' }}</div>
            </div>
        </div>
        <div class="user-stats" data-helper-sidebar-stats="{{ route('helper.readiness.status') }}">
            <div class="stat">
                <span class="value" id="sessionCount">{{ $totalSessions }}</span>
                <span class="label">Sessions</span>
            </div>
            <div class="stat">
                <span class="value" id="compScore">{{ $competencyScore === null ? 'No data' : $competencyScore.'%' }}</span>
                <span class="label">Competency</span>
            </div>
            <div class="stat">
                <span class="value" id="availStatus" role="status" aria-live="polite" style="color: {{ $availabilityStatus === 'available' ? 'var(--green-500)' : 'var(--yellow-500)' }};">{{ $availabilityLabel }}</span>
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
                <x-ui-icon name="logout"  /><span class="logout-text">Log out</span>
            </button>
        </form>
    </div>
</aside>

@include('components.confirmation-modal')

<script src="{{ asset('js/helper-sidebar-stats.js').'?v='.filemtime(public_path('js/helper-sidebar-stats.js')) }}" defer></script>
