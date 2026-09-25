{{--
    COMPASS Admin Sidebar — same design language as the role sidebars
    (resources/views/partials/sidebar.blade.php and layouts/partials/*-sidebar.blade.php).

    Matches the shared sidebar on: 264px rail, the real <x-brand-mark /> lockup,
    .nav-section / .nav-item typography, the inset accent bar on the active item,
    the .sidebar-footer user card + view-profile + logout stack, and the desktop
    collapse to a 76px icon rail persisted under the same `sidebarCollapsed` key.

    Divergences kept on purpose (admin-only features):
      - dark theme + selectable accent, so colours resolve through the admin tokens
      - the inline SVG icon set instead of Font Awesome
      - the hamburger drawer (body.sidebar-open) at <=980px, owned by admin-dashboard.js
--}}
@props(['admin', 'active' => 'dashboard'])

@php
    $navigation = [
        'Platform' => [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'href' => route('admin.dashboard')],
            ['key' => 'users', 'label' => 'Users', 'icon' => 'users', 'href' => route('admin.users')],
            ['key' => 'roles-permissions', 'label' => 'Roles & Permissions', 'icon' => 'shield', 'href' => route('admin.roles-permissions')],
            ['key' => 'resource-library', 'label' => 'Resource Library', 'icon' => 'book-open', 'href' => route('admin.resource-library')],
        ],
        'System' => [
            ['key' => 'audit-logs', 'label' => 'Audit Logs', 'icon' => 'file-text', 'href' => route('admin.audit-logs')],
            ['key' => 'backup-restore', 'label' => 'Backup & Restore', 'icon' => 'backup', 'href' => route('admin.backup-restore')],
            ['key' => 'system-health', 'label' => 'System Health', 'icon' => 'activity', 'href' => route('admin.system-health')],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart-line', 'href' => route('admin.reports')],
        ],
        'Account' => [
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'href' => route('admin.settings')],
        ],
    ];

    $adminName = $admin->name ?: 'System Administrator';
    $adminInitials = $admin->initials();
@endphp

<style>
    /* ── Scope note ──────────────────────────────────────────────────────────
       These rules are prefixed with .admin-sidebar so the canonical sidebar
       look can be reproduced on the admin shell without loading sidebar.css
       (which is light-theme only and would fight the admin dark theme). */
    .admin-sidebar {
        --as-rail: #6b7280;
        --as-rail-muted: #9ca3af;
        --as-rail-strong: #163b2d;
        --as-soft: var(--admin-accent-soft);
        --as-accent: var(--admin-accent);
        --as-accent-dark: var(--admin-accent-dark);
        --as-line: rgba(4, 160, 82, .08);
        padding: 22px 12px 18px;
        transition: width .35s cubic-bezier(.4, 0, .2, 1);
        overflow: hidden;
    }

    /* Width is desktop-only so the <=980px hamburger drawer keeps the width
       admin-dashboard.css gives it. */
    @media (min-width: 981px) {
        .admin-sidebar { width: var(--sidebar-width); }
    }

    /* ── Brand / header ── */
    .admin-sidebar .sidebar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-shrink: 0;
        padding-bottom: 18px;
        margin-bottom: 8px;
        border-bottom: 1px solid var(--as-line);
    }
    .admin-sidebar .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 0;
        text-decoration: none;
    }
    .admin-sidebar .brand-logo-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        object-fit: cover;
        box-shadow: 0 4px 16px rgba(4, 160, 82, .2);
        flex-shrink: 0;
        display: none;
    }
    .admin-sidebar .brand-block {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: flex-start;
        gap: 4px;
        min-width: 0;
    }
    .admin-sidebar .brand-wordmark {
        height: 26px;
        width: auto;
        max-width: 100%;
        display: block;
    }
    .admin-sidebar .brand-logo--light { display: block; }
    .admin-sidebar .brand-logo--dark { display: none; }

    /* ── Collapse toggle (desktop only) ── */
    .admin-sidebar .sidebar-toggle {
        position: relative;
        width: 28px;
        height: 28px;
        flex-shrink: 0;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 0;
        border: none;
        border-radius: 8px;
        background: var(--as-soft);
        color: var(--as-accent-dark);
        cursor: pointer;
        transition: background .2s ease;
    }
    .admin-sidebar .sidebar-toggle:hover { background: var(--as-accent); color: #fff; }
    .admin-sidebar .sidebar-toggle svg { transition: transform .3s ease; }

    /* ── Navigation ── */
    .admin-sidebar .sidebar-nav {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        padding-top: 6px;
        margin-right: -6px;
        padding-right: 6px;
    }
    .admin-sidebar .sidebar-nav::-webkit-scrollbar { width: 6px; }
    .admin-sidebar .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(4, 160, 82, .15); border-radius: 3px; }

    .admin-sidebar .nav-section {
        padding: 16px 14px 6px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .12em;
        color: var(--as-rail-muted);
    }
    .admin-sidebar .nav-group { display: grid; gap: 2px; }
    .admin-sidebar .nav-item {
        display: flex;
        align-items: center;
        gap: 14px;
        min-height: 42px;
        padding: 10px 14px;
        border-radius: 12px;
        color: var(--as-rail);
        font-size: 14px;
        font-weight: 500;
        line-height: 1.25;
        text-decoration: none;
        cursor: pointer;
        position: relative;
        white-space: nowrap;
        transition: background .2s ease, color .2s ease, transform .2s ease;
    }
    .admin-sidebar .nav-item .admin-icon {
        width: 20px;
        height: 20px;
        text-align: center;
        color: var(--as-rail-muted);
        transition: color .2s ease;
    }
    .admin-sidebar .nav-item:hover,
    .admin-sidebar .nav-item:focus-visible {
        background: var(--as-soft);
        color: var(--as-rail-strong);
        outline: none;
        transform: translateX(2px);
    }
    .admin-sidebar .nav-item:hover .admin-icon { color: var(--as-accent); }
    .admin-sidebar .nav-item.active {
        background: var(--as-soft);
        color: var(--as-accent-dark);
        font-weight: 600;
        box-shadow: inset 3px 0 0 var(--as-accent);
    }
    .admin-sidebar .nav-item.active .admin-icon { color: var(--as-accent); }
    .admin-sidebar .nav-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* ── Footer: user card, profile link, logout ── */
    .admin-sidebar .sidebar-footer {
        flex-shrink: 0;
        border-top: 1px solid var(--as-line);
        padding-top: 14px;
        margin-top: 12px;
    }
    .admin-sidebar .sidebar-user {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 6px 4px;
        border-radius: 12px;
        text-decoration: none;
        transition: background .2s ease;
    }
    .admin-sidebar .sidebar-user:hover,
    .admin-sidebar .sidebar-user:focus-visible { background: var(--as-soft); outline: none; }
    .admin-sidebar .user-avatar {
        width: 42px;
        height: 42px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: linear-gradient(135deg, #30b650, var(--admin-green-dark));
        color: #fff;
        font-weight: 700;
        font-size: 15px;
        text-transform: uppercase;
        overflow: hidden;
    }
    .admin-sidebar .user-info { display: flex; min-width: 0; flex-direction: column; }
    .admin-sidebar .user-name {
        font-weight: 600;
        font-size: 14px;
        color: var(--as-rail-strong);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .admin-sidebar .user-role {
        display: flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        color: var(--as-rail-muted);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .admin-sidebar .user-role::before {
        content: '';
        width: 7px;
        height: 7px;
        flex: none;
        border-radius: 50%;
        background: var(--as-accent);
    }
    .admin-sidebar .view-profile {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        margin-top: 6px;
        padding: 4px;
        border-radius: 8px;
        color: var(--as-accent-dark);
        font-size: 11px;
        font-weight: 600;
        text-decoration: none;
        transition: background .2s ease, color .2s ease;
    }
    .admin-sidebar .view-profile:hover,
    .admin-sidebar .view-profile:focus-visible { background: var(--as-soft); color: var(--as-accent-dark); outline: none; }
    .admin-sidebar .sidebar-logout-form { margin-top: 12px; }
    .admin-sidebar .sidebar-logout-button .logout-text { white-space: nowrap; }

    /* ── Collapsed icon rail (desktop only) ── */
    @media (min-width: 981px) {
        .admin-sidebar .sidebar-toggle { display: inline-flex; }
        .admin-sidebar.collapsed { width: 76px; }
        .admin-sidebar.collapsed .brand-block,
        .admin-sidebar.collapsed .nav-text,
        .admin-sidebar.collapsed .nav-section,
        .admin-sidebar.collapsed .user-info,
        .admin-sidebar.collapsed .view-profile,
        .admin-sidebar.collapsed .logout-text { display: none; }
        .admin-sidebar.collapsed .brand-logo-icon { display: block; }
        .admin-sidebar.collapsed .sidebar-header { flex-direction: column; gap: 14px; padding-bottom: 12px; }
        .admin-sidebar.collapsed .nav-item { justify-content: center; padding-left: 0; padding-right: 0; }
        .admin-sidebar.collapsed .sidebar-user { justify-content: center; }
        .admin-sidebar.collapsed .sidebar-toggle svg { transform: rotate(180deg); }
        .admin-sidebar.collapsed ~ .admin-main { margin-left: 76px; }
        html.admin-sidebar-collapsed .admin-main { margin-left: 76px; }
    }

    /* ── Dark theme ── */
    :root[data-admin-theme="dark"] .admin-sidebar {
        --as-rail: #c5d0df;
        --as-rail-muted: #9aa8ba;
        --as-rail-strong: #edf3fb;
        --as-soft: #1b2738;
        --as-line: rgba(255, 255, 255, .08);
    }
    :root[data-admin-theme="dark"] .admin-sidebar .brand-logo--light { display: none; }
    :root[data-admin-theme="dark"] .admin-sidebar .brand-logo--dark { display: block; }
    :root[data-admin-theme="dark"] .admin-sidebar .nav-item:hover .admin-icon { color: var(--as-accent); }
</style>

<aside class="admin-sidebar" id="adminSidebar" data-admin-sidebar aria-label="System administrator navigation">
    <div class="sidebar-header">
        <a href="{{ route('admin.dashboard') }}" class="sidebar-brand" aria-label="COMPASS admin dashboard">
            <x-brand-mark />
        </a>

        <button type="button" class="sidebar-toggle" id="adminSidebarToggle" aria-label="Collapse sidebar" title="Collapse / expand sidebar">
            <x-admin.icon name="panel-left" :size="15" />
        </button>

        <button class="sidebar-close" type="button" data-sidebar-close aria-label="Close navigation">
            <x-admin.icon name="close" :size="21" />
        </button>
    </div>

    <nav class="sidebar-nav" aria-label="System administrator sections">
        @foreach ($navigation as $section => $items)
            <div class="nav-section">{{ $section }}</div>
            <div class="nav-group">
                @foreach ($items as $item)
                    <a
                        class="nav-item {{ $active === $item['key'] ? 'active' : '' }}"
                        href="{{ $item['href'] }}"
                        @if ($active === $item['key']) aria-current="page" @endif
                    >
                        <x-admin.icon :name="$item['icon']" :size="19" />
                        <span class="nav-text">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endforeach
    </nav>

    <div class="sidebar-footer">
        <a href="{{ route('admin.settings') }}" class="sidebar-user" aria-label="Open administrator settings">
            <span class="user-avatar" aria-hidden="true">{{ $adminInitials }}</span>
            <span class="user-info">
                <span class="user-name">{{ $adminName }}</span>
                <span class="user-role">System Administrator</span>
            </span>
        </a>

        <a href="{{ route('admin.settings') }}" class="view-profile">
            <x-admin.icon name="settings" :size="15" />
            <span>View Profile &amp; Settings</span>
        </a>

        <form class="sidebar-logout-form" method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="sidebar-logout-button" type="submit">
                <x-admin.icon name="logout" :size="19" />
                <span class="logout-text">Logout</span>
            </button>
        </form>
    </div>
</aside>

<script>
    // Desktop-only collapse, mirroring layouts/partials/sidebar-critical.blade.php:
    // same `sidebarCollapsed` storage key and the same .collapsed icon-rail state.
    (function () {
        var STORAGE_KEY = 'sidebarCollapsed';
        var MOBILE_MAX = 980;
        var rail = document.getElementById('adminSidebar');
        var toggle = document.getElementById('adminSidebarToggle');
        if (!rail) return;

        function isMobile() { return window.innerWidth <= MOBILE_MAX; }

        function setCollapsed(collapsed) {
            if (isMobile()) collapsed = false;
            rail.classList.toggle('collapsed', collapsed);
            document.documentElement.classList.toggle('admin-sidebar-collapsed', collapsed);
        }

        // Applied before first paint so a stored preference never flashes the wide rail.
        setCollapsed(!isMobile() && localStorage.getItem(STORAGE_KEY) === 'true');

        if (toggle) {
            toggle.addEventListener('click', function () {
                var collapsed = !rail.classList.contains('collapsed');
                setCollapsed(collapsed);
                try { localStorage.setItem(STORAGE_KEY, collapsed ? 'true' : 'false'); } catch (e) {}
            });
        }

        window.addEventListener('resize', function () {
            setCollapsed(!isMobile() && localStorage.getItem(STORAGE_KEY) === 'true');
        });
    })();
</script>
