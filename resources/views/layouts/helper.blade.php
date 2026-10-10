<!DOCTYPE html>
<html class="compass-ui" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>@yield('title', 'COMPASS') – Helper</title>
    <!-- Google Fonts -->
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

    <link rel="stylesheet" href="{{ asset('css/helper-refinement.css') }}?v={{ filemtime(public_path('css/helper-refinement.css')) }}">
    <script src="{{ asset('js/helper-refinement.js') }}?v={{ filemtime(public_path('js/helper-refinement.js')) }}" defer></script>
    @yield('styles')
    <style>
        body.helper-layout > .main-content { margin-left: var(--sidebar-w); padding: 24px 28px 40px; min-width: 0; width: auto; }
        body.helper-layout > .sidebar.collapsed ~ .main-content,
        html.sidebar-collapsed-preload body.helper-layout > .main-content { margin-left: var(--sidebar-w-collapsed); }
        .helper-layout .main-content > * { min-width: 0; max-width: 100%; }
        .helper-layout .top-bar { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
        dialog {
            position: fixed;
            inset: 0;
            margin: auto;
            max-width: calc(100vw - 16px);
            max-height: 92dvh;
            overflow: auto;
        }
        dialog:not([open]) { display: none; }
        @media (max-width: 768px) {
            body.helper-layout > .main-content { margin-left: 0; padding: 20px 16px 88px; }
        }
    </style>
    @include('partials.ui-assets')
</head>
<body class="helper-layout compass-compact font-sans antialiased @yield('body-class')">

    @include('layouts.partials.helper-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="greeting">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="hamburger" id="hamburgerBtn" aria-label="Open navigation" aria-controls="sidebar"><x-ui-icon name="menu"  /></button>
                    <div>
                        <h1>@yield('heading', 'Helper')</h1>
                        <p>@yield('subheading', '')</p>
                    </div>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:12px;color:var(--gray-400);">{{ now('Asia/Manila')->format('M d, Y') }}</span>

            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="status" data-flash> {{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-error" role="alert" data-flash> {{ session('error') }}</div>
        @endif
        @if (session('info'))
            <div class="alert alert-info" role="status" data-flash> {{ session('info') }}</div>
        @endif
        @if (session('readiness_status'))
            <div class="alert {{ session('readiness_status') === 'ready' ? 'alert-success' : 'alert-warning' }}" data-flash>

                You are currently marked as <strong>{{ session('readiness_status') === 'ready' ? 'Ready' : 'Not Ready' }}</strong> for sessions.
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error" role="alert" data-flash>

                <div>
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        @yield('content')
    </main>

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
    @include('partials.workflow-notice', ['inlineNotices' => ['success', 'error', 'info'], 'inlineErrors' => true])
    @include('layouts.partials.pwa-banner')
</body>
</html>
