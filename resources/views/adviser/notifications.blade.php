<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <title>COMPASS – Notifications</title>

    @vite(['resources/js/app.js', 'resources/js/adviser-notifications.js'])
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0;
            --green-100: #D0F0D8;
            --green-500: #04A052;
            --green-600: #038A45;
            --green-700: #027039;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #163B2D;
            --red-500: #EF4444;
            --yellow-500: #F59E0B;
            --blue-500: #3B82F6;
        }

        body { background: #F8FBF9; }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(4,160,82,0.06);
            box-shadow: 4px 0 40px rgba(0,0,0,0.02);
            z-index: 100;
            transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
            display: flex;
            flex-direction: column;
            padding: 24px 16px 20px;
        }
        .sidebar.closed { transform: translateX(-100%); }

        .main-content { margin-left: 260px; padding: 24px 32px 80px; min-height: 100vh; }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px 24px;
            border: 1px solid var(--gray-200);
        }
        .stat-number { font-size: 28px; font-weight: 800; color: var(--gray-800); }
        .stat-label { font-size: 13px; color: var(--gray-500); }

        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            gap: 12px;
            flex-wrap: wrap;
        }
        .card-header h3 { font-weight: 700; font-size: 16px; color: var(--gray-800); }

        .notif-item {
            display: flex;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid var(--gray-100);
        }
        .notif-item:last-child { border-bottom: none; }
        .notif-item.unread { background: var(--green-50); border-radius: 14px; padding: 14px; margin-bottom: 2px; }
        .notif-item .icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .notif-item.unread .icon { background: white; }
        .notif-item .content { flex: 1; min-width: 0; }
        .notif-item .content .title-row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }
        .notif-item .content .title { font-weight: 600; font-size: 14px; color: var(--gray-800); }
        .notif-item .content .title .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--green-500);
            margin-left: 6px;
        }
        .notif-item .content .time { font-size: 12px; color: var(--gray-400); }
        .notif-item .content .message { font-size: 13px; color: var(--gray-500); margin-top: 4px; }
        .notif-item .content .actions { display: flex; gap: 10px; margin-top: 8px; flex-wrap: wrap; }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 20px;
            border: none;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-primary:hover { background: var(--green-700); transform: scale(1.02); }

        .btn-outline {
            background: transparent;
            color: var(--gray-600);
            padding: 6px 14px;
            border-radius: 16px;
            border: 1px solid var(--gray-200);
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-outline:hover { background: var(--green-50); border-color: var(--green-500); color: var(--green-700); }

        .empty-state { text-align: center; padding: 48px 16px; color: var(--gray-400); }
        .empty-state i { font-size: 40px; margin-bottom: 12px; display: block; opacity: 0.5; }

        .hamburger { display: none; background: none; border: none; font-size: 24px; color: var(--gray-700); cursor: pointer; padding: 4px; }
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.25); z-index: 99; }
        .sidebar-overlay.active { display: block; }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.94);
            backdrop-filter: blur(16px);
            border-top: 1px solid var(--gray-200);
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
            z-index: 200;
            justify-content: space-around;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: var(--gray-400);
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 12px;
        }
        .bottom-nav .nav-item.active { color: var(--green-500); }
        .bottom-nav .nav-item.active i { color: var(--green-500); }

        @media (min-width: 769px) { .sidebar-overlay { display: none !important; } }
        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .sidebar { width: 280px; padding: 16px; }
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .hamburger { display: block; }
            .bottom-nav { display: flex; }
            .stat-number { font-size: 22px; }
        }
    </style>
</head>
<body>

    @include('layouts.partials.adviser-sidebar')

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-gray-800">Notifications</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        Emergencies, referrals, and evaluation updates
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400 hidden sm:inline">{{ now()->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Total Notifications</span>
                    <span class="text-2xl">🔔</span>
                </div>
                <div class="stat-number">{{ $notifications->count() }}</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Unread</span>
                    <span class="text-2xl">📬</span>
                </div>
                <div class="stat-number">{{ $unreadCount }}</div>
                <span class="text-xs text-gray-400">Needs your attention</span>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between">
                    <span class="stat-label">Read</span>
                    <span class="text-2xl">✅</span>
                </div>
                <div class="stat-number">{{ $notifications->count() - $unreadCount }}</div>
            </div>
        </div>

        <!-- Type Filter -->
        <div class="flex items-center gap-2 mb-6 flex-wrap">
            <a href="{{ route('adviser.notifications') }}"
               class="px-3 py-1.5 rounded-full text-xs font-semibold border {{ !$typeFilter ? 'bg-[#EAF8F0] border-[#04A052] text-[#027039]' : 'border-gray-200 text-gray-500 hover:border-[#04A052]' }} transition">
                All
            </a>
            @foreach($types as $type)
                <a href="{{ route('adviser.notifications', ['type' => $type]) }}"
                   class="px-3 py-1.5 rounded-full text-xs font-semibold border capitalize {{ $typeFilter === $type ? 'bg-[#EAF8F0] border-[#04A052] text-[#027039]' : 'border-gray-200 text-gray-500 hover:border-[#04A052]' }} transition">
                    {{ $type }}
                </a>
            @endforeach
        </div>

        <!-- Notification List -->
        <div class="card">
            <div class="card-header">
                <h3>All Notifications</h3>
                @if($unreadCount > 0)
                    <form method="POST" action="{{ route('adviser.notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="btn-outline"><i class="fas fa-check-double"></i> Mark all read</button>
                    </form>
                @endif
            </div>

            @if($notifications->isNotEmpty())
                <div style="display:flex;flex-direction:column;">
                    @foreach($notifications as $item)
                        @php $isUnread = $item->status === 'unread'; @endphp
                        <div class="notif-item {{ $isUnread ? 'unread' : '' }}">
                            <div class="icon">{{ $item->type_icon ?: '🔔' }}</div>
                            <div class="content">
                                <div class="title-row">
                                    <span class="title">
                                        {{ $item->title }}
                                        @if($isUnread)
                                            <span class="dot"></span>
                                        @endif
                                    </span>
                                    <span class="time">{{ $item->created_at?->diffForHumans() }}</span>
                                </div>
                                <div class="message">{{ $item->message }}</div>
                                <div class="actions">
                                    @if($item->link)
                                        <a href="{{ $item->link }}" class="btn-outline"><i class="fas fa-external-link-alt"></i> View</a>
                                    @endif
                                    @if($isUnread)
                                        <form method="POST" action="{{ route('adviser.notifications.read', ['id' => $item->id]) }}">
                                            @csrf
                                            <button type="submit" class="btn-outline"><i class="fas fa-check"></i> Mark read</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('adviser.notifications.destroy', ['id' => $item->id]) }}"
                                          onsubmit="return confirm('Delete this notification?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-outline" style="color:var(--red-500);border-color:#FECACA;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <p class="text-lg font-medium text-gray-600">No notifications</p>
                    <p>Emergency flags and referral updates will appear here.</p>
                </div>
            @endif
        </div>

        <!-- Footer -->
        <div class="mt-8 text-center text-sm text-gray-400 border-t border-gray-200 pt-6">
            <i class="fas fa-heart text-[#04A052] mr-1"></i>
            Quality supervision leads to quality support.
        </div>

    </main>

    <!-- Bottom Navigation -->
    <nav class="bottom-nav" id="bottomNav">
        <a href="{{ route('adviser.dashboard') }}" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
        <a href="{{ route('adviser.evaluations') }}" class="nav-item"><i class="fas fa-clipboard-list"></i><span>Evaluations</span></a>
        <a href="{{ route('adviser.referrals') }}" class="nav-item"><i class="fas fa-arrow-right"></i><span>Referrals</span></a>
        <a href="{{ route('adviser.helpers') }}" class="nav-item"><i class="fas fa-users"></i><span>Helpers</span></a>
        <a href="{{ route('adviser.notifications') }}" class="nav-item active"><i class="fas fa-bell"></i><span>Alerts</span></a>
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