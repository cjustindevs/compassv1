<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>@yield('title', 'COMPASS') – Helper</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/css/helper-components.css', 'resources/js/app.js', 'resources/js/helper-notifications.js'])

    <script>
        (function () {
            try {
                if (localStorage.getItem('sidebarCollapsed') === 'true') {
                    document.documentElement.classList.add('sidebar-collapsed-preload');
                }
            } catch (e) {}
        })();
    </script>

    @yield('styles')
    <style>
        body.helper-layout > .main-content { margin-left: var(--sidebar-w); padding: 24px 28px 40px; min-width: 0; width: auto; }
        body.helper-layout > .sidebar.collapsed ~ .main-content,
        html.sidebar-collapsed-preload body.helper-layout > .main-content { margin-left: var(--sidebar-w-collapsed); }
        .helper-layout .main-content > * { min-width: 0; max-width: 100%; }
        .helper-layout .top-bar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
        @media (max-width: 768px) {
            body.helper-layout > .main-content { margin-left: 0; padding: 20px 16px 88px; }
        }
    </style>
</head>
<body class="helper-layout compass-compact font-sans antialiased">

    @include('layouts.partials.helper-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="greeting">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="hamburger" id="hamburgerBtn"><i class="fas fa-bars"></i></button>
                    <div>
                        <h1>@yield('heading', 'Helper')</h1>
                        <p>@yield('subheading', '')</p>
                    </div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:12px;color:var(--gray-400);">{{ now()->format('M d, Y') }}</span>
                <a href="{{ route('helper.notifications') }}" class="notif-bell" style="position:relative;width:36px;height:36px;border-radius:50%;background:white;border:1px solid var(--gray-200);display:flex;align-items:center;justify-content:center;color:var(--gray-500);">
                    <i class="fas fa-bell"></i>
                    @if(($unreadBadge = optional(auth()->user())->unreadNotifications()->count()) > 0)
                        <span style="position:absolute;top:-4px;right:-4px;background:var(--red-600);color:white;font-size:10px;font-weight:700;min-width:18px;height:18px;border-radius:10px;display:flex;align-items:center;justify-content:center;padding:0 4px;">{{ $unreadBadge }}</span>
                    @endif
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success" data-flash><i class="fas fa-check-circle"></i> {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error" data-flash><i class="fas fa-exclamation-circle"></i> {{ session('error') }}</div>
        @endif
        @if (session('info'))
            <div class="alert alert-info" data-flash><i class="fas fa-info-circle"></i> {{ session('info') }}</div>
        @endif
        @if (session('readiness_status'))
            <div class="alert {{ session('readiness_status') === 'ready' ? 'alert-success' : 'alert-warning' }}" data-flash>
                <i class="fas fa-heartbeat"></i>
                You are currently marked as <strong>{{ session('readiness_status') === 'ready' ? 'Ready' : 'Not Ready' }}</strong> for sessions.
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error" data-flash>
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Bottom navigation (mobile) -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('helper.dashboard') }}" class="nav-item {{ request()->routeIs('helper.dashboard') ? 'active' : '' }}"><i class="fas fa-th-large"></i><span>Home</span></a>
        <a href="{{ route('helper.cases') }}" class="nav-item {{ request()->routeIs('helper.cases*') ? 'active' : '' }}"><i class="fas fa-folder-open"></i><span>Cases</span></a>
        <a href="{{ route('helper.chat') }}" class="nav-item {{ request()->routeIs('helper.session.chat*') ? 'active' : '' }}"><i class="fas fa-comment-dots"></i><span>Chat</span></a>
        <a href="{{ route('helper.notifications') }}" class="nav-item {{ request()->routeIs('helper.notifications*') ? 'active' : '' }}"><i class="fas fa-bell"></i><span>Alerts</span></a>
        <a href="{{ route('helper.profile') }}" class="nav-item {{ request()->routeIs('helper.profile*') ? 'active' : '' }}"><i class="fas fa-user-circle"></i><span>Profile</span></a>
    </nav>

    <script>
        @php $u = optional(auth()->user()); @endphp
        (function () {
            @if($u->high_contrast)
                document.body.classList.add('high-contrast');
            @endif
            @if($u->font_size === 'large')
                document.documentElement.style.fontSize = '112.5%';
            @elseif($u->font_size === 'small')
                document.documentElement.style.fontSize = '93%';
            @endif
        })();

    </script>

    @yield('scripts')
    @include('layouts.partials.pwa-banner')
</body>
</html>
