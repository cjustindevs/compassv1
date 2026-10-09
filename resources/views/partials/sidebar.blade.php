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

@include('layouts.partials.sidebar-critical')

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('seeker.dashboard') }}" class="sidebar-brand">
            <x-brand-mark />
        </a>
        {{-- theme toggle moved to Settings → Appearance --}}
        <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-label="Collapse sidebar" title="Collapse / expand sidebar">
            <x-ui-icon name="chevron-left" class="sidebar-chevron" id="toggleIcon" />
        </button>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Main</div>
        <a href="{{ route('seeker.dashboard') }}{{ $dashboardTabs ? '#home' : '' }}"
           class="nav-item {{ $isActive('seeker.dashboard') ? 'active' : '' }}"
           @if($dashboardTabs) data-tab="home" @endif>
            <x-ui-icon name="home"  /><span class="nav-text">Home</span>
        </a>
        <a href="{{ route('seeker.dashboard') }}#dashboard"
           class="nav-item nav-item-dashboard {{ $isActive('seeker.dashboard') ? 'active' : '' }}"
           @if($dashboardTabs) data-tab="dashboard" @endif>
            <x-ui-icon name="pie-chart"  /><span class="nav-text">Dashboard</span>
        </a>

        <div class="nav-section">Support</div>
        <a href="{{ route('request.screening') }}"
           class="nav-item {{ $anyActive(['request.screening*', 'request.preferences*', 'request.matching*', 'request.voice-consent*']) || $anyActive(['session.chat', 'session.voice', 'session.evaluation*']) ? 'active' : '' }}">
            <x-ui-icon name="message"  /><span class="nav-text">Request Help</span>
        </a>
        <a href="{{ route('seeker.requests') }}" title="Request history" class="nav-item {{ $isActive('seeker.requests') ? 'active' : '' }}"><x-ui-icon name="clipboard"  /><span class="nav-text">Request history</span></a>
        <a href="{{ route('seeker.privacy') }}" title="Privacy and consent" class="nav-item {{ $isActive('seeker.privacy') ? 'active' : '' }}"><x-ui-icon name="role"  /><span class="nav-text">Privacy and consent</span></a>
        <a href="{{ route('seeker.referrals') }}" title="Referral decisions" class="nav-item {{ $isActive('seeker.referrals') ? 'active' : '' }}"><x-ui-icon name="share"  /><span class="nav-text">Referral decisions</span></a>
        <a href="{{ route('session.history') }}"
           class="nav-item {{ $isActive('session.history') ? 'active' : '' }}">
            <x-ui-icon name="list"  /><span class="nav-text">My Sessions</span>
            @if($badgeSessions > 0)
                <span class="nav-badge">{{ $badgeSessions }}</span>
            @endif
        </a>

        <div class="nav-section">Resources</div>
        <a href="{{ route('selfhelp') }}"
           class="nav-item {{ $anyActive(['selfhelp*']) ? 'active' : '' }}">
            <x-ui-icon name="heart"  /><span class="nav-text">Self-Help</span>
        </a>
        <a href="{{ route('emergency') }}"
           class="nav-item {{ $isActive('emergency') ? 'active' : '' }}">
            <x-ui-icon name="warning"  /><span class="nav-text">Emergency</span>
        </a>

        <div class="nav-section">Account</div>
        <a href="{{ route('notifications') }}"
           class="nav-item {{ $anyActive(['notifications*']) ? 'active' : '' }}">
            <x-ui-icon name="bell"  /><span class="nav-text">Notifications</span>
            @if($badgeNotifications > 0)
                <span class="nav-badge" id="notificationBadge">{{ $badgeNotifications }}</span>
            @endif
        </a>
        <a href="{{ route('profile.edit') }}"
           class="nav-item {{ $anyActive(['profile*']) ? 'active' : '' }}">
            <x-ui-icon name="user"  /><span class="nav-text">Profile</span>
        </a>
        <a href="{{ route('settings') }}"
           class="nav-item {{ $anyActive(['settings*']) ? 'active' : '' }}">
            <x-ui-icon name="settings"  /><span class="nav-text">Settings</span>
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
                <div class="user-role"><x-ui-icon name="circle"  />{{ $role }}</div>
            </div>
        </a>
        <a href="{{ route('profile.edit') }}" class="view-profile">
            <x-ui-icon name="edit"  /> View Profile &amp; Settings
        </a>
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

<div class="sidebar-overlay" id="sidebarOverlay"></div>

@include('components.confirmation-modal')

<!-- ══════════════════════════════════════════════ -->
<!-- BOTTOM NAVIGATION (mobile)                    -->
<!-- ══════════════════════════════════════════════ -->

<nav class="bottom-nav" id="bottomNav">
    <a href="{{ route('seeker.dashboard') }}{{ $dashboardTabs ? '#home' : '' }}"
       class="nav-item {{ $isActive('seeker.dashboard') ? 'active' : '' }}"
       @if($dashboardTabs) data-tab="home" @endif>
        <x-ui-icon name="home"  />
        <span>Home</span>
    </a>
    <a href="{{ route('seeker.dashboard') }}#dashboard"
       class="nav-item nav-item-dashboard {{ $isActive('seeker.dashboard') ? 'active' : '' }}"
       @if($dashboardTabs) data-tab="dashboard" @endif>
        <x-ui-icon name="pie-chart"  />
        <span>Stats</span>
    </a>
    <a href="{{ route('request.screening') }}"
       class="nav-item {{ $anyActive(['request.screening*', 'request.preferences*', 'request.matching*', 'request.voice-consent*']) ? 'active' : '' }}">
        <x-ui-icon name="message"  />
        <span>Support</span>
    </a>
    <a href="{{ route('session.history') }}"
       class="nav-item {{ $isActive('session.history') ? 'active' : '' }}">
        <x-ui-icon name="list"  />
        <span>Sessions</span>
    </a>
    <a href="{{ route('selfhelp') }}"
       class="nav-item {{ $isActive('selfhelp') ? 'active' : '' }}">
        <x-ui-icon name="heart"  />
        <span>Wellness</span>
    </a>
</nav>

<script>
    @php
        $prefs = optional(auth()->user());
    @endphp
    // Apply persisted accessibility preferences.
    (function () {
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

        setInterval(refreshUnread, 60000);
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

@include('components.seeker-consent-modal')
