@once
@if(auth()->check() && auth()->user()->is_active && in_array(auth()->user()->role,['adviser','moderator','professional']))
<dialog id="staffEmergencyNotice" class="cm-dialog" data-tone="danger" aria-labelledby="staffEmergencyTitle">
    <div class="cm-head">
        <span class="cm-icon" aria-hidden="true"><x-ui-icon name="warning" /></span>
        <div><h2 id="staffEmergencyTitle" class="cm-title">Emergency requires attention</h2><p class="cm-text">Open your emergency queue to review the case and coordinate support. This alert remains in your notification center.</p></div>
    </div>
    <p class="cm-error" data-error role="alert"></p>
    <div class="cm-actions"><button type="button" class="cm-btn cm-btn--secondary" data-dismiss>Acknowledge alert</button><button type="button" class="cm-btn cm-btn--danger" data-open>Open emergency queue</button></div>
</dialog>
<meta id="staffEmergencyConfig" content="{{ json_encode(['next' => route('emergency-notice.next'), 'dismiss' => route('emergency-notice.dismiss'), 'csrf' => csrf_token(), 'openLabel' => auth()->user()->role === 'professional' ? 'Review assigned referral' : 'Open emergency queue']) }}">
<script src="{{ asset('js/staff-emergency-notice.js') }}?v={{ filemtime(public_path('js/staff-emergency-notice.js')) }}" defer></script>
@endif
@endonce
