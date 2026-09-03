@once
    <script>
        (function () {
            try {
                if (localStorage.getItem('sidebarCollapsed') === 'true') {
                    document.documentElement.classList.add('sidebar-collapsed-preload');
                }
            } catch (e) {}
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
        .sidebar-overlay { display: none; }
        .main-content { margin-left: 260px; min-height: 100vh; }
        .sidebar.collapsed,
        html.sidebar-collapsed-preload .sidebar { width: var(--sidebar-w-collapsed); }
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
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0 !important; }
        }
    </style>
@endonce
