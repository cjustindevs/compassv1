@once
@if(auth()->check() && auth()->user()->is_active && in_array(auth()->user()->role,['adviser','moderator','professional']))
<dialog id="staffEmergencyNotice" aria-labelledby="staffEmergencyTitle" style="position:fixed;inset:0;margin:auto;width:min(460px,calc(100vw - 32px));padding:24px;border:1px solid #e7c7c1;border-radius:16px;font-family:Inter,system-ui,sans-serif;max-height:85dvh;overflow:auto">
<h2 id="staffEmergencyTitle" style="font-size:20px;font-weight:700;color:#9c2921">Emergency requires attention</h2><p style="margin:16px 0">Open your emergency queue to review the case and coordinate support. This alert remains in your notification center.</p><p data-error role="alert"></p><div style="display:flex;gap:12px;flex-wrap:wrap"><button type="button" data-open style="background:#078749;color:white;padding:10px 16px;border-radius:8px">Open emergency queue</button><button type="button" data-dismiss style="padding:10px 16px;border:1px solid #cbd8d0;border-radius:8px">Acknowledge alert</button></div></dialog>
<style>#staffEmergencyNotice::backdrop{background:rgba(20,30,25,.5)}#staffEmergencyNotice button:focus-visible{outline:2px solid #078749;outline-offset:3px}</style>
<meta id="staffEmergencyConfig" content="{{ json_encode(['next' => route('emergency-notice.next'), 'dismiss' => route('emergency-notice.dismiss'), 'csrf' => csrf_token(), 'openLabel' => auth()->user()->role === 'professional' ? 'Review assigned referral' : 'Open emergency queue']) }}">
<script src="{{ asset('js/staff-emergency-notice.js') }}?v={{ filemtime(public_path('js/staff-emergency-notice.js')) }}" defer></script>
@endif
@endonce
