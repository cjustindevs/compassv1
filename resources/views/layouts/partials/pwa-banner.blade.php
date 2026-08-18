<!-- ── Offline mode banner (shown/hidden by app.js) ── -->
<div id="offlineBanner" style="display:none;position:fixed;top:0;left:0;right:0;z-index:9999;background:#FEF2F2;border-bottom:2px solid #EF4444;padding:8px 16px;text-align:center;font-size:14px;color:#DC2626;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
    <i class="fas fa-wifi" style="margin-right:8px;"></i>You are offline. Some features may be unavailable.
</div>

<!-- ── PWA Install Button (shown on beforeinstallprompt) ── -->
<button type="button" id="installPwaBtn" style="display:none;position:fixed;bottom:24px;right:24px;z-index:9998;align-items:center;gap:10px;padding:12px 20px;background:#04A052;color:#fff;border:none;border-radius:50px;font-weight:600;font-size:14px;cursor:pointer;box-shadow:0 8px 30px rgba(4,160,82,0.35);font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
    <i class="fas fa-download" style="margin-right:8px;"></i>Install App
</button>