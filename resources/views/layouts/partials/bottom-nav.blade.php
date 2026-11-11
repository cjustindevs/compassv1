{{-- Phone bottom bar. Variables from SidebarComposer: $queueCount, $emergencyCount. --}}
@if(auth()->user()?->role === 'moderator')
    <nav class="ui-bottom-nav" aria-label="Primary">
        <a href="{{ route('moderator.dashboard') }}" class="ui-bottom-nav-item {{ request()->routeIs('moderator.dashboard') ? 'is-active' : '' }}">
            <x-ui-icon name="dashboard" /><span>Dashboard</span>
        </a>
        <a href="{{ route('moderator.queue') }}" class="ui-bottom-nav-item {{ request()->routeIs('moderator.queue*') ? 'is-active' : '' }}">
            <x-ui-icon name="hourglass" /><span>Queue</span>
            @if(($queueCount ?? 0) > 0)<b class="ui-bottom-nav-badge">{{ $queueCount }}</b>@endif
        </a>
        <a href="{{ route('moderator.emergency') }}" class="ui-bottom-nav-item {{ request()->routeIs('moderator.emergency*') ? 'is-active' : '' }}">
            <x-ui-icon name="warning" /><span>Emergency</span>
            @if(($emergencyCount ?? 0) > 0)<b class="ui-bottom-nav-badge ui-bottom-nav-badge--danger">{{ $emergencyCount }}</b>@endif
        </a>
        <button type="button" class="ui-bottom-nav-item" data-open-sidebar aria-label="More">
            <x-ui-icon name="menu" /><span>More</span>
        </button>
    </nav>
@endif
