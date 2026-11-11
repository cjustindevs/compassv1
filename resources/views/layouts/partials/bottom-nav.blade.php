{{-- Phone bottom bar. Items come from App\Support\RoleNav; badge values are SidebarComposer variables. --}}
@php($bottomItems = \App\Support\RoleNav::bottomItems((string) auth()->user()?->role))
@if($bottomItems !== [])
    <nav class="ui-bottom-nav" aria-label="Primary">
        @foreach($bottomItems as $item)
            @php($isActive = request()->routeIs(...(array) $item['match']))
            @php($badgeValue = $item['badge'] ? (${$item['badge']} ?? 0) : 0)
            <a href="{{ route($item['route']) }}" class="ui-bottom-nav-item {{ $isActive ? 'is-active' : '' }}" @if($isActive) aria-current="page" @endif>
                <x-ui-icon name="{{ $item['icon'] }}" /><span>{{ $item['label'] }}</span>
                @if($badgeValue > 0)<b class="ui-bottom-nav-badge{{ $item['tone'] === 'danger' ? ' ui-bottom-nav-badge--danger' : '' }}">{{ $badgeValue }}</b>@endif
            </a>
        @endforeach
        <button type="button" class="ui-bottom-nav-item" data-open-sidebar aria-label="More" aria-expanded="false" aria-controls="sidebar">
            <x-ui-icon name="menu" /><span>More</span>
        </button>
    </nav>
@endif
