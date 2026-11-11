{{-- Variables from SidebarComposer: $emergencyCount (moderator and adviser data). Target comes from RoleNav. --}}
@php($stripCount = (int) ($emergencyCount ?? 0))
@php($strip = \App\Support\RoleNav::emergencyStrip((string) auth()->user()?->role))
@if($strip && $stripCount > 0 && ! request()->routeIs($strip['hideOn']))
    <div class="ui-emergency-strip" role="status">
        <x-ui-icon name="warning" />
        <span class="ui-emergency-strip-text">{{ $stripCount }} active {{ $stripCount === 1 ? 'emergency' : 'emergencies' }}</span>
        <a class="ui-emergency-strip-link" href="{{ route($strip['route']) }}">Open</a>
    </div>
@endif
