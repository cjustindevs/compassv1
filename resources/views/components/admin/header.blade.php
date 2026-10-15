@props([
    'admin',
    'pageTitle' => 'System Overview',
    'pageSubtitle' => 'Platform-wide activity and health',
    'searchQuery' => '',
    'searchAction' => null,
    'searchPlaceholder' => 'Search referrals, cases, users, reports...',
])

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
                maxlength="120"
                data-admin-search
            >
            @if(request()->routeIs('admin.reports', 'admin.audit-logs', 'admin.users'))
                @foreach(['from','to','activity_date','section','case_status','emergency_status','priority','helper_id','concern_id','referral_status','actor','category','date','per_page','role','status'] as $filter)
                    @if(request()->filled($filter) && is_scalar(request($filter)))<input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">@endif
                @endforeach
            @endif
            <kbd aria-label="Keyboard shortcut: Command or Control K">⌘K</kbd>
        </form>

        <div class="header-actions">
            <a class="icon-button header-action" href="{{ route('admin.settings') }}" aria-label="Account settings" title="Account settings"><x-admin.icon name="sliders" :size="19" /></a>

        </div>
    </div>
</header>
