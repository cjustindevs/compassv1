@once
    <script>
        (function () {
            try {
                // Desktop-only: never collapse the drawer strip on phones/tablets,
                // even when a desktop collapse preference was saved previously.
                if (window.innerWidth > 768 && localStorage.getItem('sidebarCollapsed') === 'true') {
                    document.documentElement.classList.add('sidebar-collapsed-preload');
                }
            } catch (e) {}
        })();

        // ── Self-contained sidebar controller ──────────────────────────────
        // Every role sidebar includes this partial, so the drawer works even on
        // pages that never load the app.js bundle (standalone seeker pages).
        // app.js's resources/js/sidebar.js detects `data-sidebar-bound` and
        // skips its own binding to avoid double toggles.
        (function () {
            if (window.__compassSidebarInline) return;
            window.__compassSidebarInline = true;

            var MOBILE_BREAKPOINT = 768;
            var STORAGE_KEY = 'sidebarCollapsed';

            function isMobile() { return window.innerWidth <= MOBILE_BREAKPOINT; }

            function init() {
                var sidebar = document.getElementById('sidebar');
                if (!sidebar || sidebar.dataset.sidebarBound === '1') return;
                sidebar.dataset.sidebarBound = '1';

                document.documentElement.classList.remove('sidebar-collapsed-preload');

                var overlay   = document.getElementById('sidebarOverlay');
                var hamburger = document.getElementById('hamburgerBtn');
                var toggle    = document.getElementById('sidebarToggle');
                var toggleIcon = document.getElementById('toggleIcon');

                function openDrawer() {
                    sidebar.classList.remove('closed', 'collapsed');
                    sidebar.classList.add('open');
                    if (overlay) overlay.classList.add('active');
                }
                function closeDrawer() {
                    sidebar.classList.remove('open');
                    if (overlay) overlay.classList.remove('active');
                }
                function toggleDrawer() {
                    if (sidebar.classList.contains('open')) closeDrawer();
                    else openDrawer();
                }
                function setCollapsed(collapsed) {
                    sidebar.classList.toggle('collapsed', collapsed);
                    if (toggleIcon) {
                        toggleIcon.classList.toggle('fa-chevron-left', !collapsed);
                        toggleIcon.classList.toggle('fa-chevron-right', collapsed);
                    }
                }

                if (hamburger) {
                    hamburger.addEventListener('click', function (e) {
                        e.preventDefault();
                        toggleDrawer();
                    });
                }
                if (overlay) overlay.addEventListener('click', closeDrawer);
                if (toggle) {
                    toggle.addEventListener('click', function () {
                        if (isMobile()) { toggleDrawer(); return; }
                        var collapsed = sidebar.classList.toggle('collapsed');
                        try { localStorage.setItem(STORAGE_KEY, collapsed ? 'true' : 'false'); } catch (err) {}
                        setCollapsed(collapsed);
                    });
                }

                // Tapping a nav link closes the mobile drawer.
                sidebar.querySelectorAll('.nav-item, .sidebar-user, .logout-btn').forEach(function (item) {
                    item.addEventListener('click', function () {
                        if (isMobile()) closeDrawer();
                    });
                });

                function syncViewport() {
                    sidebar.classList.remove('closed');
                    if (isMobile()) {
                        sidebar.classList.remove('collapsed');
                        closeDrawer();
                    } else {
                        sidebar.classList.remove('open');
                        if (overlay) overlay.classList.remove('active');
                        var collapsed = false;
                        try { collapsed = localStorage.getItem(STORAGE_KEY) === 'true'; } catch (err) {}
                        setCollapsed(collapsed);
                    }
                }

                syncViewport();
                window.addEventListener('resize', syncViewport);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
    </script>
    <style>
        :root { --sidebar-w: 264px; --sidebar-w-collapsed: 76px; }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-w);
            height: 100vh;
            z-index: 100;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: rgba(255,255,255,0.96);
            border-right: 1px solid rgba(4,160,82,0.08);
            box-sizing: border-box;
            contain: layout paint;
            will-change: width, transform;
        }
        .sidebar-nav { flex: 1; overflow-y: auto; overflow-x: hidden; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.25); z-index: 99; }
        .sidebar-overlay.active { display: block; }
        .main-content { margin-left: var(--sidebar-w); min-height: 100vh; }
        .hamburger { display: none; background: none; border: none; font-size: 22px; color: #4B5563; cursor: pointer; padding: 4px; }
        .sidebar-toggle { display: none; }
        .sidebar.collapsed,
        html.sidebar-collapsed-preload .sidebar { width: var(--sidebar-w-collapsed); }
        @media (min-width: 769px) {
            .sidebar-toggle { display: inline-flex; }
            .sidebar-overlay { display: none !important; }
        }
        html.sidebar-collapsed-preload .main-content { margin-left: var(--sidebar-w-collapsed); }
        html.sidebar-collapsed-preload .sidebar .brand-text,
        html.sidebar-collapsed-preload .sidebar .brand-block,
        html.sidebar-collapsed-preload .sidebar .nav-section,
        html.sidebar-collapsed-preload .sidebar .nav-text,
        html.sidebar-collapsed-preload .sidebar .nav-label,
        html.sidebar-collapsed-preload .sidebar .user-info,
        html.sidebar-collapsed-preload .sidebar .user-stats,
        html.sidebar-collapsed-preload .sidebar .view-profile,
        html.sidebar-collapsed-preload .sidebar .logout-text { display: none; }
        @media (max-width: 768px) {
            .sidebar { width: 280px; transform: translateX(-100%); }
            .sidebar.collapsed { width: 280px; }
            .sidebar.open,
            .sidebar.open.closed { transform: translateX(0); }
            .sidebar.closed { transform: translateX(-100%); }
            .main-content { margin-left: 0 !important; }
            .hamburger { display: block; }
        }
    </style>
@endonce
