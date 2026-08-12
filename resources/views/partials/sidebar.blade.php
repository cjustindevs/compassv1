{{--
    COMPASS Sidebar Partial — single source of truth for authenticated navigation.

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

<style>
    /* ─── Sidebar (shared) ─── */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: 260px;
        height: 100vh;
        background: rgba(255, 255, 255, 0.88);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-right: 1px solid rgba(4, 160, 82, 0.08);
        box-shadow: 4px 0 40px rgba(0, 0, 0, 0.02);
        z-index: 100;
        transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        padding: 24px 16px 20px;
    }
    .sidebar.closed { transform: translateX(-100%); }

    .sidebar .logo {
        display: flex;
        align-items: center;
        gap: 12px;
        padding-bottom: 20px;
        border-bottom: 1px solid rgba(4, 160, 82, 0.08);
        margin-bottom: 8px;
    }
    .sidebar .logo .icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--green-400), var(--green-600));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 800;
        font-size: 20px;
        box-shadow: 0 4px 16px rgba(4, 160, 82, 0.25);
    }
    .sidebar .logo span {
        font-weight: 700;
        font-size: 20px;
        color: var(--green-700);
        letter-spacing: -0.5px;
    }

    .sidebar .nav { flex: 1; overflow-y: auto; padding-top: 8px; }
    .sidebar .nav-section {
        padding: 16px 14px 6px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: var(--gray-400);
    }
    .sidebar .nav .nav-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 10px 14px;
        border-radius: 12px;
        color: var(--gray-500);
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s ease;
        cursor: pointer;
        text-decoration: none;
        margin-bottom: 2px;
        position: relative;
    }
    .sidebar .nav .nav-item i { width: 20px; text-align: center; font-size: 16px; color: var(--gray-400); transition: color 0.2s ease; }
    .sidebar .nav .nav-item:hover { background: var(--green-50); color: var(--gray-800); transform: translateX(2px); }
    .sidebar .nav .nav-item:hover i { color: var(--green-500); }
    .sidebar .nav .nav-item.active {
        background: var(--green-50);
        color: var(--green-700);
        font-weight: 600;
        box-shadow: inset 3px 0 0 var(--green-500);
    }
    .sidebar .nav .nav-item.active i { color: var(--green-500); }
    .sidebar .nav .nav-item .badge {
        margin-left: auto;
        background: var(--green-500);
        color: white;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 20px;
        animation: badge-dot 2s infinite;
    }
    @keyframes badge-dot {
        0%, 100% { box-shadow: 0 0 0 0 rgba(4, 160, 82, 0.35); }
        50% { box-shadow: 0 0 0 4px rgba(4, 160, 82, 0); }
    }

    .sidebar .user-section {
        border-top: 1px solid rgba(4, 160, 82, 0.08);
        padding-top: 14px;
        margin-top: 12px;
    }
    .sidebar .user-section .user-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 8px;
        border-radius: 12px;
        text-decoration: none;
        transition: background 0.2s ease;
    }
    .sidebar .user-section .user-card:hover { background: var(--green-50); }
    .sidebar .user-section .user-card .avatar {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--green-400), var(--green-600));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 700;
        font-size: 15px;
        flex-shrink: 0;
        text-transform: uppercase;
    }
    .sidebar .user-section .user-card .info { min-width: 0; }
    .sidebar .user-section .user-card .info .name {
        font-weight: 600;
        font-size: 14px;
        color: var(--gray-800);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .sidebar .user-section .user-card .info .role {
        font-size: 12px;
        color: var(--gray-400);
    }
    .sidebar .user-section .user-card .info .role i { font-size: 10px; color: var(--green-500); margin-right: 3px; }
    .sidebar .user-section .view-profile {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 600;
        color: var(--green-600);
        text-decoration: none;
        margin-top: 4px;
        padding: 4px;
        border-radius: 8px;
        transition: all 0.2s ease;
    }
    .sidebar .user-section .view-profile:hover { background: var(--green-50); color: var(--green-700); }
    .sidebar .user-section .logout-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-top: 10px;
        padding: 10px 12px;
        border-radius: 12px;
        color: #DC2626;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s ease;
        cursor: pointer;
        border: 1px solid #FECACA;
        background: #FEF2F2;
        width: 100%;
    }
    .sidebar .user-section .logout-btn:hover {
        background: #DC2626;
        color: white;
        border-color: #DC2626;
        box-shadow: 0 6px 20px rgba(220, 38, 38, 0.25);
        transform: translateY(-1px);
    }
    .sidebar .user-section .logout-btn i { width: 20px; text-align: center; }

    /* ─── Hamburger ─── */
    .hamburger {
        display: none;
        background: none;
        border: none;
        font-size: 24px;
        color: var(--gray-700);
        cursor: pointer;
        padding: 4px;
    }
    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.25);
        z-index: 99;
    }
    .sidebar-overlay.active { display: block; }

    /* ─── Bottom Nav (mobile) ─── */
    .bottom-nav {
        display: none;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border-top: 1px solid var(--gray-200);
        padding: 6px 0 env(safe-area-inset-bottom, 6px);
        z-index: 200;
        justify-content: space-around;
    }
    .bottom-nav .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0px;
        color: var(--gray-400);
        text-decoration: none;
        font-size: 10px;
        font-weight: 500;
        padding: 4px 12px;
        transition: all 0.2s ease;
    }
    .bottom-nav .nav-item i { font-size: 20px; }
    .bottom-nav .nav-item.active { color: var(--green-500); }

    @media (max-width: 768px) {
        .sidebar { width: 280px; padding: 16px; }
        .hamburger { display: block; }
        .bottom-nav { display: flex; }
    }
    @media (min-width: 769px) {
        .sidebar-overlay { display: none !important; }
    }
</style>

<!-- ══════════════════════════════════════════════ -->
<!-- SIDEBAR                                      -->
<!-- ══════════════════════════════════════════════ -->

<aside class="sidebar" id="sidebar">
    <a href="{{ route('seeker.dashboard') }}" class="logo" style="text-decoration: none;">
        <div class="icon">C</div>
        <span>COMPASS</span>
    </a>

    <nav class="nav">
        <div class="nav-section">Main</div>
        <a href="{{ route('seeker.dashboard') }}{{ $dashboardTabs ? '#home' : '' }}"
           class="nav-item {{ $isActive('seeker.dashboard') ? 'active' : '' }}"
           @if($dashboardTabs) data-tab="home" @endif>
            <i class="fas fa-home"></i> Home
        </a>
        <a href="{{ route('seeker.dashboard') }}#dashboard"
           class="nav-item nav-item-dashboard {{ $isActive('seeker.dashboard') ? 'active' : '' }}"
           @if($dashboardTabs) data-tab="dashboard" @endif>
            <i class="fas fa-chart-pie"></i> Dashboard
        </a>

        <div class="nav-section">Support</div>
        <a href="{{ route('request.screening') }}"
           class="nav-item {{ $anyActive(['request.screening*', 'request.preferences*', 'request.matching*', 'request.voice-consent*']) || $anyActive(['session.chat', 'session.voice', 'session.evaluation*']) ? 'active' : '' }}">
            <i class="fas fa-comment-dots"></i> Request Help
        </a>
        <a href="{{ route('session.history') }}"
           class="nav-item {{ $isActive('session.history') ? 'active' : '' }}">
            <i class="fas fa-list-ul"></i> My Sessions
            @if($badgeSessions > 0)
                <span class="badge">{{ $badgeSessions }}</span>
            @endif
        </a>

        <div class="nav-section">Resources</div>
        <a href="{{ route('selfhelp') }}"
           class="nav-item {{ $anyActive(['selfhelp*']) ? 'active' : '' }}">
            <i class="fas fa-heart"></i> Self-Help
        </a>
        <a href="{{ route('emergency') }}"
           class="nav-item {{ $isActive('emergency') ? 'active' : '' }}">
            <i class="fas fa-exclamation-triangle"></i> Emergency
        </a>

        <div class="nav-section">Account</div>
        <a href="{{ route('notifications') }}"
           class="nav-item {{ $anyActive(['notifications*']) ? 'active' : '' }}">
            <i class="fas fa-bell"></i> Notifications
            @if($badgeNotifications > 0)
                <span class="badge" id="notificationBadge">{{ $badgeNotifications }}</span>
            @endif
        </a>
        <a href="{{ route('profile.edit') }}"
           class="nav-item {{ $anyActive(['profile*']) ? 'active' : '' }}">
            <i class="fas fa-user-circle"></i> Profile
        </a>
        <a href="{{ route('settings') }}"
           class="nav-item {{ $anyActive(['settings*']) ? 'active' : '' }}">
            <i class="fas fa-cog"></i> Settings
        </a>
    </nav>

    <div class="user-section">
        <a href="{{ route('profile.edit') }}" class="user-card" title="Edit profile">
            <div class="avatar">
                @if(auth()->user() && auth()->user()->avatar_path)
                    <img src="{{ asset(auth()->user()->avatar_path) }}" alt="Avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                @else
                    {{ Illuminate\Support\Str::substr($userName, 0, 2) }}
                @endif
            </div>
            <div class="info">
                <div class="name">{{ $userName }}</div>
                <div class="role"><i class="fas fa-circle"></i>{{ $role }}</div>
            </div>
        </a>
        <a href="{{ route('profile.edit') }}" class="view-profile">
            <i class="fas fa-user-edit"></i> View Profile &amp; Settings
        </a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </form>
    </div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

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

    document.addEventListener('DOMContentLoaded', function () {
        // ── Sidebar Toggle ──
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const hamburger = document.getElementById('hamburgerBtn');

        function closeSidebar() {
            if (sidebar) sidebar.classList.add('closed');
            if (overlay) overlay.classList.remove('active');
        }

        if (hamburger) {
            hamburger.addEventListener('click', function () {
                const isOpen = sidebar && !sidebar.classList.contains('closed');
                if (sidebar) sidebar.classList.toggle('closed', isOpen);
                if (overlay) overlay.classList.toggle('active', isOpen);
            });
        }
        if (overlay) overlay.addEventListener('click', closeSidebar);
        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) closeSidebar();
        });

        @if($dashboardTabs)
        // ── Dashboard hash tabs (Home / Dashboard sections) ──
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

            document.querySelectorAll('.sidebar .nav .nav-item[data-tab]').forEach(function (item) {
                item.classList.toggle('active', item.dataset.tab === tabId);
            });
            document.querySelectorAll('.bottom-nav .nav-item[data-tab]').forEach(function (item) {
                item.classList.toggle('active', item.dataset.tab === tabId);
            });

            const titleEl = document.getElementById('pageTitle');
            if (titleEl) titleEl.textContent = tabId === 'dashboard' ? 'Dashboard' : 'Home';
        }

        function applyHash() {
            const hash = window.location.hash.replace('#', '');
            switchDashboardTab(hash === 'dashboard' || hash === 'home' ? hash : 'home');
        }

        document.querySelectorAll('.sidebar .nav .nav-item[data-tab], .bottom-nav .nav-item[data-tab]').forEach(function (item) {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                switchDashboardTab(this.dataset.tab);
                history.pushState(null, '', '#' + this.dataset.tab);
                if (window.innerWidth <= 768) closeSidebar();
            });
        });

        window.addEventListener('hashchange', applyHash);
        applyHash();
        @endif
    });
</script>