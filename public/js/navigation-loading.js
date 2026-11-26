(() => {
    if (window.compassNavigationLoading) return;
    window.compassNavigationLoading = true;
    const style = document.createElement('style');
    style.textContent = `#compass-navigation-loading {position:fixed;inset:0;z-index:9999;display:grid;place-items:center;background:rgba(255,255,255,.88);pointer-events:none;font-family:Inter,sans-serif} #compass-navigation-loading[hidden]{display:none} #compass-navigation-loading .loading-card {padding:24px 32px;border:1px solid #dcfce7;border-radius:16px;background:white;color:#166534;text-align:center;box-shadow:0 8px 30px #0000000d} #compass-navigation-loading .loading-ring {width:28px;height:28px;margin:0 auto 12px;border:3px solid #dcfce7;border-top-color:#16a34a;border-radius:50%;animation:compass-loading-spin .7s linear infinite} @keyframes compass-loading-spin {to{transform:rotate(360deg)}} @media(prefers-reduced-motion:reduce){#compass-navigation-loading .loading-ring{animation:none}}`;
    document.head.append(style);
    const overlay = document.createElement('div');
    overlay.id = 'compass-navigation-loading';
    overlay.hidden = true;
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.innerHTML = '<div class="loading-card"><div class="loading-ring" aria-hidden="true"></div><span>Loading page...</span></div>';
    document.body.append(overlay);
    let fallback;
    const hide = () => { overlay.hidden = true; clearTimeout(fallback); };
    const show = () => { overlay.hidden = false; clearTimeout(fallback); fallback = setTimeout(hide, 8000); };
    window.addEventListener('pageshow', hide);
    window.addEventListener('pagehide', hide);
    document.addEventListener('click', event => {
        if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self') || link.hasAttribute('data-no-loading')) return;
        const url = new URL(link.href, location.href);
        if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return;
        if (url.pathname === location.pathname && url.search === location.search) return;
        queueMicrotask(() => { if (!event.defaultPrevented) show(); });
    });
    document.addEventListener('submit', event => {
        const form = event.target;
        if (form.method === 'dialog' || (form.target && form.target !== '_self') || form.hasAttribute('data-no-loading')) return;
        queueMicrotask(() => { if (!event.defaultPrevented) show(); });
    });
})();
