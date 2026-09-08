<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Notifications</title>

    @vite(['resources/js/app.js', 'resources/js/moderator-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg-primary, #F8FBF9); }

        .main-content {
            margin-left: 260px;
            max-width: 900px;
            padding: 24px 32px 80px;
            min-height: 100vh;
        }

        .card {
            background: var(--bg-card, white);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border-color, #E5E7EB);
            box-shadow: var(--card-shadow, 0 4px 20px rgba(0,0,0,0.01));
        }

        .notif-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-light, #F3F4F6);
            transition: background 0.2s;
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item:hover { background: var(--bg-hover, #F9FAFB); }
        .notif-item.unread { background: #F0FDF4; }
        .notif-item.unread:hover { background: #EAF8F0; }
        .notif-item .icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 16px;
        }
        .notif-item .icon.emergency { background: #FEE2E2; }
        .notif-item .icon.queue { background: #FEF3C7; }
        .notif-item .icon.referral { background: #DBEAFE; }
        .notif-item .icon.assignment { background: #DCFCE7; }
        .notif-item .icon.system { background: var(--border-light, #F3F4F6); }
        .notif-item .content { flex: 1; min-width: 0; }
        .notif-item .content .title { font-weight: 600; font-size: 14px; color: var(--text-primary, #1F2937); }
        .notif-item .content .msg { font-size: 13px; color: var(--text-secondary, #6B7280); margin-top: 2px; }
        .notif-item .content .time { font-size: 11px; color: var(--text-muted, #9CA3AF); margin-top: 4px; }
        .notif-item .actions { display: flex; gap: 6px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #04A052; flex-shrink: 0; margin-top: 6px; }

        .filter-pill {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary, #6B7280);
            background: var(--bg-card, white);
            border: 1.5px solid var(--border-color, #E5E7EB);
            text-decoration: none;
            transition: all 0.2s;
            display: inline-block;
        }
        .filter-pill:hover { border-color: #04A052; color: #04A052; }
        .filter-pill.active { background: #EAF8F0; border-color: #04A052; color: #027039; }

        .btn-ghost {
            background: var(--bg-card, white);
            color: var(--text-secondary, #6B7280);
            padding: 6px 12px;
            border-radius: 16px;
            font-weight: 600;
            font-size: 11px;
            border: 1.5px solid var(--border-color, #E5E7EB);
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-ghost:hover { border-color: #DC2626; color: #DC2626; }
        .btn-primary {
            background: linear-gradient(135deg, #04A052, #038A45);
            color: white;
            padding: 8px 18px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary:hover { background: #027039; }

        .flash-success { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 13px; font-weight: 500; margin-bottom: 16px; }

        .hamburger { display: none; background: none; border: none; font-size: 24px; color: var(--text-primary, #374151); cursor: pointer; padding: 4px; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.25); z-index: 99; }
        .sidebar-overlay.active { display: block; }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--navbar-bg, rgba(255,255,255,0.94));
            backdrop-filter: blur(16px);
            border-top: 1px solid #E5E7EB;
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
            z-index: 200;
            justify-content: space-around;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--text-muted, #9CA3AF);
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 12px;
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: #04A052; }
        .bottom-nav .nav-item.active i { color: #04A052; }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .card { padding: 16px; }
        }
    </style>
</head>
<body class="compass-compact">

    @include('layouts.partials.moderator-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <div class="flex items-center gap-4 mb-6">
            <button class="hamburger" id="hamburgerBtn">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <h1 class="text-xl md:text-2xl font-bold text-gray-800">Notifications</h1>
                <p class="text-sm text-gray-500 hidden sm:block">{{ $unreadCount }} unread</p>
            </div>
        </div>

        @if(session('success'))
            <div class="flash-success"><i class="fas fa-check-circle mr-1"></i> {{ session('success') }}</div>
        @endif

        <div class="flex flex-wrap items-center gap-2 mb-6">
            <a href="{{ route('moderator.notifications') }}" class="filter-pill {{ ! $typeFilter ? 'active' : '' }}">All</a>
            @foreach($types as $type)
                <a href="{{ route('moderator.notifications', ['type' => $type]) }}"
                   class="filter-pill {{ $typeFilter === $type ? 'active' : '' }}">
                    {{ ucfirst($type) }}
                </a>
            @endforeach
            <div class="ml-auto">
                <form method="POST" action="{{ route('moderator.notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn-primary"><i class="fas fa-check-double mr-1"></i> Mark all as read</button>
                </form>
            </div>
        </div>

        <div class="card">
            @forelse($notifications as $notification)
                <div class="notif-item {{ $notification->status === 'unread' ? 'unread' : '' }}">
                    @if($notification->status === 'unread')
                        <span class="dot"></span>
                    @endif
                    <div class="icon {{ $notification->notification_type ?? 'system' }}">
                        <span>{{ $notification->type_icon }}</span>
                    </div>
                    <div class="content">
                        <p class="title">{{ $notification->title }}</p>
                        <p class="msg">{{ $notification->message }}</p>
                        <p class="time">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="actions">
                        <form method="POST" action="{{ route('moderator.notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" class="btn-ghost" title="Mark as read / open">
                                <i class="fas fa-eye"></i>
                            </button>
                        </form>
                        <form method="POST" action="{{ route('moderator.notifications.destroy', $notification->id) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-ghost" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="text-center py-12 text-gray-400">
                    <p class="text-3xl mb-2">🔔</p>
                    <p>No notifications</p>
                </div>
            @endforelse
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('moderator.dashboard') }}" class="nav-item">
            <i class="fas fa-th-large"></i><span>Dashboard</span>
        </a>
        <a href="{{ route('moderator.queue') }}" class="nav-item">
            <i class="fas fa-hourglass-half"></i><span>Queue</span>
        </a>
        <a href="{{ route('moderator.sessions') }}" class="nav-item">
            <i class="fas fa-comments"></i><span>Sessions</span>
        </a>
        <a href="{{ route('moderator.emergency') }}" class="nav-item">
            <i class="fas fa-exclamation-triangle"></i><span>Emergency</span>
        </a>
        <a href="{{ route('moderator.analytics') }}" class="nav-item">
            <i class="fas fa-chart-line"></i><span>Analytics</span>
        </a>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const hamburger = document.getElementById('hamburgerBtn');

            function toggleSidebar() {
                sidebar.classList.toggle('closed');
                overlay.classList.toggle('active');
            }

            function closeSidebar() {
                sidebar.classList.add('closed');
                overlay.classList.remove('active');
            }

            hamburger.addEventListener('click', toggleSidebar);
            overlay.addEventListener('click', closeSidebar);

            window.addEventListener('resize', function () {
                if (window.innerWidth > 768) closeSidebar();
            });
        });
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
