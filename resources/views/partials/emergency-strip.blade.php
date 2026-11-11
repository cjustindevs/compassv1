{{-- Variables from SidebarComposer: $emergencyCount (moderator data; adviser data does not expose it, so no strip there). --}}
@php($stripCount = (int) ($emergencyCount ?? 0))
@if(auth()->user()?->role === 'moderator' && $stripCount > 0 && ! request()->routeIs('moderator.emergency*'))
    <div class="ui-emergency-strip" role="status">
        <x-ui-icon name="warning" />
        <span class="ui-emergency-strip-text">{{ $stripCount }} active {{ $stripCount === 1 ? 'emergency' : 'emergencies' }}</span>
        <a class="ui-emergency-strip-link" href="{{ route('moderator.emergency') }}">Open</a>
    </div>
@endif
