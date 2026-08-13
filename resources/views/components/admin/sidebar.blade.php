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
            ['key' => 'audit-logs', 'label' => 'Audit Logs', 'icon' => 'file-text', 'path' => '/admin/audit-logs'],
            ['key' => 'backup-restore', 'label' => 'Backup & Restore', 'icon' => 'backup', 'path' => '/admin/backup-restore'],
            ['key' => 'system-health', 'label' => 'System Health', 'icon' => 'activity', 'path' => '/admin/system-health'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart-line', 'path' => '/admin/reports'],
        ],
        'Account' => [
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'path' => '/admin/settings'],
        ],
    ];

    $adminName = $admin->name ?: 'System Administrator';
    $adminInitials = $admin->initials();
@endphp

<aside class="admin-sidebar" id="admin-sidebar" data-admin-sidebar aria-label="System administrator navigation">
    <div class="sidebar-brand-row">
        <a class="sidebar-brand" href="{{ route('admin.dashboard') }}" aria-label="COMPASS admin dashboard">
            <span class="sidebar-brand-mark" aria-hidden="true">C</span>
            <span class="sidebar-brand-copy">
                <strong>COMPASS</strong>
                <small>PDAF PROGRAM</small>
            </span>
        </a>

        <button class="sidebar-close" type="button" data-sidebar-close aria-label="Close navigation">
            <x-admin.icon name="close" :size="21" />
        </button>
    </div>

    <nav class="sidebar-navigation">
        @foreach ($navigation as $section => $items)
            <section class="sidebar-section" aria-labelledby="admin-nav-{{ Str::slug($section) }}">
                <h2 id="admin-nav-{{ Str::slug($section) }}">{{ $section }}</h2>

                <div class="sidebar-links">
                    @foreach ($items as $item)
                        @if (isset($item['href']))
                            <a
                                class="sidebar-link {{ $active === $item['key'] ? 'is-active' : '' }}"
                                href="{{ $item['href'] }}"
                                @if ($active === $item['key']) aria-current="page" @endif
                            >
                                <x-admin.icon :name="$item['icon']" :size="19" />
                                <span>{{ $item['label'] }}</span>
                            </a>
                        @else
                            <span
                                class="sidebar-link is-pending"
                                aria-disabled="true"
                                data-future-route="{{ $item['path'] }}"
                                title="This admin module will be implemented separately"
                            >
                                <x-admin.icon :name="$item['icon']" :size="19" />
                                <span>{{ $item['label'] }}</span>
                            </span>
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach
    </nav>

    <div class="sidebar-account">
        <span class="avatar avatar-green" aria-hidden="true">{{ $adminInitials }}</span>
        <span class="sidebar-account-copy">
            <strong>{{ $adminName }}</strong>
            <small>System Administrator</small>
        </span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="icon-button logout-button" type="submit" aria-label="Log out of COMPASS">
                <x-admin.icon name="logout" :size="19" />
            </button>
        </form>
    </div>
</aside>
