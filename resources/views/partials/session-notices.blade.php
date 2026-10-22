@once
<span hidden data-session-notices data-session-id="{{ $session->id }}" data-claim-url="{{ route('session.notices.claim', $session) }}"></span>
<style>
.session-warning-toast{position:fixed;z-index:9998;top:calc(env(safe-area-inset-top,0px) + 80px);left:50%;transform:translateX(-50%);width:min(420px,calc(100vw - 32px));display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:16px 18px;border:1px solid #e8c677;border-left:4px solid #d58b08;border-radius:12px;background:#fffaf0;color:#67450c;box-shadow:0 8px 28px #173b2c20;font:14px/1.5 Inter,system-ui,sans-serif}
.session-warning-toast strong{font-size:15px}.session-warning-toast p{margin:3px 0 0}.session-warning-toast button{background:transparent!important;border:0!important;color:#67450c!important;min-width:36px;min-height:36px;font-size:24px;cursor:pointer}.session-ending-soon{color:#a05b06!important;font-weight:700!important}
</style>
<script src="{{ asset('js/session-notices.js') }}?v={{ filemtime(public_path('js/session-notices.js')) }}" defer></script>
@endonce
