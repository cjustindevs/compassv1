{{--
    COMPASS Sidebar Partial — single source of truth for the Help Seeker navigation.

    Usage:
        @include('partials.sidebar', [
            'active'   => ['request.screening*'],   // route pattern(s) to highlight
            'role'     => 'Help Seeker',            // role label shown in the user card
            'userName' => null,                     // optional override for display name
            'dashboardTabs' => false,               // true on the seeker dashboard (hash tabs)
        ])
--}}
@php
    $active  = $active ?? [];
    $active  = is_array($active) ? $active : [$active];
    $role    = $role ?? 'Help Seeker';
    $userName = $userName ?? optional(auth()->user())->name ?? optional(auth()->user())->email ?? 'Seeker';
    $dashboardTabs = $dashboardTabs ?? false;

    try {
        $badgeNotifications = $badgeNotifications ?? (int) auth()->user()->unreadNotifications()->count();
    } catch (\Throwable $e) {
        $badgeNotifications = 0;
    }
    $badgeSessions = $badgeSessions ?? 0;

    $isActive = fn (string $pattern) => request()->routeIs($pattern);
    $anyActive = fn (array $patterns) => collect($patterns)->contains(fn ($p) => $isActive($p));
@endphp

<!-- ══════════════════════════════════════════════ -->
<!-- SIDEBAR                                      -->
<!-- ══════════════════════════════════════════════ -->

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('seeker.dashboard') }}" class="sidebar-brand">
            <x-brand-mark />
        </a>
        {{-- theme toggle moved to Settings → Appearance --}}
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Collapse sidebar" title="Collapse / expand sidebar">
            <i class="fas fa-chevron-left" id="toggleIcon"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="{{ route('seeker.dashboard') }}{{ $dashboardTabs ? '#home' : '' }}"
           class="nav-item {{ $isActive('seeker.dashboard') ? 'active' : '' }}"
           @if($dashboardTabs) data-tab="home" @endif>
            <i class="fas fa-home"></i><span class="nav-text">Home</span>
        </a>
        <a href="{{ route('seeker.dashboard') }}#dashboard"
           class="nav-item nav-item-dashboard {{ $isActive('seeker.dashboard') ? 'active' : '' }}"
           @if($dashboardTabs) data-tab="dashboard" @endif>
            <i class="fas fa-chart-pie"></i><span class="nav-text">Dashboard</span>
        </a>

        <div class="nav-section">Support</div>
        <a href="{{ route('request.screening') }}"
           class="nav-item {{ $anyActive(['request.screening*', 'request.preferences*', 'request.matching*', 'request.voice-consent*']) || $anyActive(['session.chat', 'session.voice', 'session.evaluation*']) ? 'active' : '' }}">
            <i class="fas fa-comment-dots"></i><span class="nav-text">Request Help</span>
        </a>
        <a href="{{ route('session.history') }}"
           class="nav-item {{ $isActive('session.history') ? 'active' : '' }}">
            <i class="fas fa-list-ul"></i><span class="nav-text">My Sessions</span>
            @if($badgeSessions > 0)
                <span class="nav-badge">{{ $badgeSessions }}</span>
            @endif
        </a>

        <div class="nav-section">Resources</div>
        <a href="{{ route('selfhelp') }}"
           class="nav-item {{ $anyActive(['selfhelp*']) ? 'active' : '' }}">
            <i class="fas fa-heart"></i><span class="nav-text">Self-Help</span>
        </a>
        <a href="{{ route('emergency') }}"
           class="nav-item {{ $isActive('emergency') ? 'active' : '' }}">
            <i class="fas fa-exclamation-triangle"></i><span class="nav-text">Emergency</span>
        </a>

        <div class="nav-section">Account</div>
        <a href="{{ route('notifications') }}"
           class="nav-item {{ $anyActive(['notifications*']) ? 'active' : '' }}">
            <i class="fas fa-bell"></i><span class="nav-text">Notifications</span>
            @if($badgeNotifications > 0)
                <span class="nav-badge" id="notificationBadge">{{ $badgeNotifications }}</span>
            @endif
        </a>
        <a href="{{ route('profile.edit') }}"
           class="nav-item {{ $anyActive(['profile*']) ? 'active' : '' }}">
            <i class="fas fa-user-circle"></i><span class="nav-text">Profile</span>
        </a>
        <a href="{{ route('settings') }}"
           class="nav-item {{ $anyActive(['settings*']) ? 'active' : '' }}">
            <i class="fas fa-cog"></i><span class="nav-text">Settings</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="{{ route('profile.edit') }}" class="sidebar-user" title="Edit profile">
            <div class="user-avatar">
                @if(auth()->user() && auth()->user()->avatar_path)
                    <img src="{{ asset(auth()->user()->avatar_path) }}" alt="Avatar">
                @else
                    {{ Illuminate\Support\Str::substr($userName, 0, 2) }}
                @endif
            </div>
            <div class="user-info">
                <div class="user-name">{{ $userName }}</div>
                <div class="user-role"><i class="fas fa-circle"></i>{{ $role }}</div>
            </div>
        </a>
        <a href="{{ route('profile.edit') }}" class="view-profile">
            <i class="fas fa-user-edit"></i> View Profile &amp; Settings
        </a>
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

<div class="sidebar-overlay" id="sidebarOverlay"></div>

@include('components.confirmation-modal')

<!-- ══════════════════════════════════════════════ -->
<!-- BOTTOM NAVIGATION (mobile)                    -->
<!-- ══════════════════════════════════════════════ -->

<nav class="bottom-nav" id="bottomNav">
    <a href="{{ route('seeker.dashboard') }}{{ $dashboardTabs ? '#home' : '' }}"
       class="nav-item {{ $isActive('seeker.dashboard') ? 'active' : '' }}"
       @if($dashboardTabs) data-tab="home" @endif>
        <i class="fas fa-home"></i>
        <span>Home</span>
    </a>
    <a href="{{ route('seeker.dashboard') }}#dashboard"
       class="nav-item nav-item-dashboard {{ $isActive('seeker.dashboard') ? 'active' : '' }}"
       @if($dashboardTabs) data-tab="dashboard" @endif>
        <i class="fas fa-chart-pie"></i>
        <span>Stats</span>
    </a>
    <a href="{{ route('request.screening') }}"
       class="nav-item {{ $anyActive(['request.screening*', 'request.preferences*', 'request.matching*', 'request.voice-consent*']) ? 'active' : '' }}">
        <i class="fas fa-comment-dots"></i>
        <span>Support</span>
    </a>
    <a href="{{ route('session.history') }}"
       class="nav-item {{ $isActive('session.history') ? 'active' : '' }}">
        <i class="fas fa-list-ul"></i>
        <span>Sessions</span>
    </a>
    <a href="{{ route('selfhelp') }}"
       class="nav-item {{ $isActive('selfhelp') ? 'active' : '' }}">
        <i class="fas fa-heart"></i>
        <span>Wellness</span>
    </a>
</nav>

<script>
    @php
        $prefs = optional(auth()->user());
    @endphp
    // ── Apply persisted appearance preferences (dark mode / font size) ──
    (function () {
        @if($prefs->dark_mode)
            document.body.classList.add('dark-mode');
        @endif
        @if($prefs->high_contrast)
            document.body.classList.add('high-contrast');
        @endif
        @if($prefs->font_size)
            document.body.classList.add('font-' + '{{ $prefs->font_size }}');
        @endif
    })();

    // ── Notification badge polling (keeps the sidebar count fresh) ──
    (function () {
        var badge = document.getElementById('notificationBadge');
        if (!badge) return;

        function refreshUnread() {
            fetch('/notifications/unread-count', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var count = parseInt(data.count || 0, 10);
                    if (count > 0) {
                        badge.textContent = count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.parentElement?.removeChild(badge);
                    }
                })
                .catch(function () { /* ignore */ });
        }

        setInterval(refreshUnread, 30000);
    })();

    @if($dashboardTabs)
    // ── Dashboard hash tabs (Home / Dashboard sections) ──
    document.addEventListener('DOMContentLoaded', function () {
        const sectionTabs = {
            home: document.getElementById('tab-home'),
            dashboard: document.getElementById('tab-dashboard')
        };

        function switchDashboardTab(tabId) {
            if (!sectionTabs[tabId]) { tabId = 'home'; }
            Object.keys(sectionTabs).forEach(function (key) {
                if (sectionTabs[key]) sectionTabs[key].classList.remove('active');
            });
            if (sectionTabs[tabId]) sectionTabs[tabId].classList.add('active');

            document.querySelectorAll('.sidebar .nav-item[data-tab], .bottom-nav .nav-item[data-tab]').forEach(function (item) {
                item.classList.toggle('active', item.dataset.tab === tabId);
            });
            const titleEl = document.getElementById('pageTitle');
            if (titleEl) titleEl.textContent = tabId === 'dashboard' ? 'Dashboard' : 'Home';
        }

        function applyHash() {
            const hash = window.location.hash.replace('#', '');
            switchDashboardTab(hash === 'dashboard' || hash === 'home' ? hash : 'home');
        }

        document.querySelectorAll('.sidebar .nav-item[data-tab], .bottom-nav .nav-item[data-tab]').forEach(function (item) {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                switchDashboardTab(this.dataset.tab);
                history.pushState(null, '', '#' + this.dataset.tab);
            });
        });

        window.addEventListener('hashchange', applyHash);
        applyHash();
    });
    @endif
</script>

@vite(['resources/js/app.js'])
