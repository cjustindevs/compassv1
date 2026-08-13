@props([
    'admin',
    'pageTitle' => 'System Overview',
    'pageSubtitle' => 'Platform-wide activity and health',
    'searchQuery' => '',
    'searchAction' => null,
    'searchPlaceholder' => 'Search referrals, cases, users, reports...',
])

@php
    $adminName = $admin->name ?: 'System Administrator';
    $adminInitials = $admin->initials();
@endphp

<header class="admin-header">
    <div class="admin-header-inner">
        <div class="header-context">
            <button
                class="icon-button mobile-menu-button"
                type="button"
                data-sidebar-open
                aria-controls="admin-sidebar"
                aria-expanded="false"
                aria-label="Open navigation"
            >
                <x-admin.icon name="menu" :size="21" />
            </button>

            <span class="context-icon" aria-hidden="true">
                <x-admin.icon name="panel-left" :size="18" />
            </span>
            <span class="context-copy">
                <strong>{{ $pageTitle }}</strong>
                <small>{{ $pageSubtitle }}</small>
            </span>
        </div>

        <form class="admin-search" method="GET" action="{{ $searchAction ?? route('admin.dashboard') }}" role="search">
            <x-admin.icon name="search" :size="19" />
            <label class="sr-only" for="admin-search">Search the administrator portal</label>
            <input
                id="admin-search"
                name="q"
                type="search"
                value="{{ $searchQuery }}"
                placeholder="{{ $searchPlaceholder }}"
                autocomplete="off"
                data-admin-search
            >
            <kbd aria-label="Keyboard shortcut: Command or Control K">⌘K</kbd>
        </form>

        <div class="header-actions">
            <button class="icon-button header-action" type="button" aria-label="Help and support" title="Help and support">
                <x-admin.icon name="help" :size="19" />
            </button>

            <button class="icon-button header-action notification-button" type="button" aria-label="3 unread notifications" title="Notifications">
                <x-admin.icon name="bell" :size="19" />
                <span class="notification-badge">3</span>
            </button>

            <div class="profile-menu" data-profile-menu>
                <button
                    class="profile-trigger"
                    type="button"
                    data-profile-trigger
                    aria-haspopup="menu"
                    aria-expanded="false"
                >
                    <span class="avatar avatar-blue" aria-hidden="true">{{ $adminInitials }}</span>
                    <span class="profile-copy">
                        <strong>{{ $adminName }}</strong>
                        <small>System Administrator</small>
                    </span>
                    <x-admin.icon name="chevron-down" :size="16" />
                </button>

                <div class="profile-dropdown" data-profile-dropdown role="menu" hidden>
                    <div class="profile-dropdown-heading">
                        <strong>{{ $adminName }}</strong>
                        <span>{{ $admin->email }}</span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" role="none">
                        @csrf
                        <button type="submit" role="menuitem">
                            <x-admin.icon name="logout" :size="17" />
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
