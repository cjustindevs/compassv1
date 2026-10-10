<!DOCTYPE html>
<html class="compass-ui" lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS - Notifications</title>
    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0; --green-100: #DCF5E0; --green-200: #A8E0B0; --green-300: #6DCB80;
            --green-400: #30B650; --green-500: #04A052; --green-600: #038A45; --green-700: #027039;
            --green-800: #01562B; --green-900: #003D1E;
            --gray-50: #F9FAFB; --gray-100: #F3F4F6; --gray-200: #E5E7EB; --gray-300: #D1D5DB;
            --gray-400: #9CA3AF; --gray-500: #6B7280; --gray-600: #4B5563; --gray-700: #374151;
            --gray-800: #163B2D; --gray-900: #111827;
        }

        body { background: #F8FBF9; font-family: 'Inter', sans-serif; }

        ::-webkit-scrollbar { width: 4px; height: 4px; }
        ::-webkit-scrollbar-track { background: var(--gray-100); }
        ::-webkit-scrollbar-thumb { background: var(--green-500); border-radius: 4px; }

        .main-content { margin-left: 260px; padding: 24px 32px 80px; min-height: 100vh; }

        .filter-btn {
            padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: 600;
            border: 1px solid var(--gray-200); background: white; color: var(--gray-500);
            cursor: pointer; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }
        .filter-btn:hover { border-color: var(--green-300); color: var(--green-700); }
        .filter-btn.active { background: var(--green-500); border-color: var(--green-500); color: white; box-shadow: 0 4px 14px rgba(4,160,82,0.25); }

        .notif-card {
            background: white; border-radius: 20px; padding: 18px 22px;
            border: 1px solid var(--gray-200); display: flex; gap: 16px; align-items: flex-start;
            transition: all 0.3s ease; position: relative;
        }
        .notif-card:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05); }
        .notif-card.unread { border-left: 4px solid var(--green-500); background: var(--green-50); }
        .notif-card .link-btn {
            background: none; border: none; color: var(--green-600); font-weight: 600;
            font-size: 12px; cursor: pointer; padding: 0; transition: color 0.2s ease;
        }
        .notif-card .link-btn:hover { color: var(--green-800); text-decoration: underline; }
        .notif-card .delete-btn {
            background: none; border: none; color: var(--gray-400); cursor: pointer;
            font-size: 14px; padding: 4px 6px; border-radius: 8px; transition: all 0.2s ease;
        }
        .notif-card .delete-btn:hover { color: #DC2626; background: #FEF2F2; }

        .mark-all-btn {
            background: white; border: 1px solid var(--green-300); color: var(--green-600);
            font-weight: 600; font-size: 13px; padding: 8px 18px; border-radius: 10px;
            transition: all 0.2s ease; cursor: pointer;
        }
        .mark-all-btn:hover { background: var(--green-50); }

        .empty-state { background: white; border-radius: 24px; border: 1px dashed var(--gray-300); padding: 56px 24px; text-align: center; }

        .flash-banner {
            background: var(--green-500); color: white; border-radius: 14px; padding: 12px 18px;
            font-size: 14px; font-weight: 500; display: none; align-items: center; gap: 10px;
            box-shadow: 0 8px 28px rgba(4,160,82,0.3);
        }
        .flash-banner.show { display: flex; animation: slideDown 0.4s ease; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        @media (max-width: 1024px) { .main-content { padding: 20px 24px 80px; } }
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px 16px 100px; }
            .filter-row { overflow-x: auto; }
        }
    .moderator-emergency-notification{border-left:4px solid #c34242!important}.moderator-emergency-notification h4{color:#982e2e}.notif-card .notif-content{min-width:0;overflow-wrap:anywhere}
</style>
    @vite(['resources/css/app.css'])
    @include('partials.ui-assets')
</head>
<body class="notification-page">

    <main class="main-content">

        @if(session('success'))
            <div class="flash-banner show mb-4">

                <span>{{ session('success') }}</span>
                <button class="ml-auto text-lg leading-none" onclick="this.parentElement.remove()">&times;</button>
            </div>
        @endif

        <!-- Top Bar -->
        <div class="flex items-center justify-between mb-6 gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <button class="hamburger" id="hamburgerBtn" aria-label="Open navigation" aria-controls="sidebar">
                    <x-ui-icon name="menu"  />
                </button>
                <div>
                    <h1 class="text-xl md:text-2xl font-extrabold text-gray-800">Notifications</h1>
                    <p class="text-sm text-gray-500 hidden sm:block">
                        @if($unreadCount > 0)
                            You have <strong class="text-[#04A052]">{{ $unreadCount }}</strong> unread {{ $unreadCount === 1 ? 'notification' : 'notifications' }}.
                        @else
                            You're all caught up.
                        @endif
                    </p>
                </div>
            </div>
            @if($unreadCount > 0)
                <button class="mark-all-btn" id="markAllBtn" onclick="markAllRead()">
                    <x-ui-icon name="check-circle" class="mr-1" /> Mark all read
                </button>
            @endif
        </div>

        <!-- Type filters -->
        <div class="flex items-center gap-2 mb-6 filter-row ui-notification-toolbar">
            <a href="{{ route('notifications') }}" class="filter-btn {{ $activeType === null ? 'active' : '' }}">
                <x-ui-icon name="list"  /> All
            </a>
            @foreach($types as $slug => $meta)
                <a href="{{ route('notifications', ['type' => $slug]) }}" class="filter-btn {{ $activeType === $slug ? 'active' : '' }}">
                    <span><x-ui-icon :value="$meta['icon']" /></span> {{ $meta['label'] }}
                </a>
            @endforeach
        </div>

        <!-- Notification list -->
        <div id="notifList" class="ui-notification-list">
            @forelse($notifications as $notification)
                <div class="notif-card ui-notification-row {{ auth()->user()->role === 'moderator' && $notification->notification_type === 'emergency' ? 'moderator-emergency-notification' : '' }} {{ $notification->is_read ? '' : 'unread' }}" id="notif-{{ $notification->id }}" data-id="{{ $notification->id }}">
                    <div class="flex-1 min-w-0 ui-notification-content">
                        <h4 class="font-semibold text-gray-800 text-sm">{{ auth()->user()->role === 'moderator' ? \Illuminate\Support\Str::limit($notification->title, 80) : $notification->title }}</h4>
                        <p class="text-sm text-gray-500 mt-1">@if(auth()->user()->role === 'moderator'){{ \Illuminate\Support\Str::limit(preg_split('/(?<=[.!?])\s+/', strip_tags($notification->message), 2)[0], 180) }}@else{{ $notification->message }}@endif</p>
                        <div class="flex items-center gap-4 mt-2">
                            <span class="text-xs text-gray-400">{{ auth()->user()->role === 'moderator' ? $notification->created_at->copy()->timezone('Asia/Manila')->format('M j, Y g:i A').' PHT' : $notification->created_at->diffForHumans() }}</span>
                            <span class="text-[10px] uppercase font-bold tracking-wide
                                {{ $notification->notification_type === 'session' ? 'text-blue-500' : ($notification->notification_type === 'reminder' ? 'text-amber-500' : ($notification->notification_type === 'update' ? 'text-purple-500' : 'text-[#04A052]')) }}">
                                {{ $types[$notification->notification_type]['label'] ?? ucfirst($notification->notification_type ?? 'System') }}
                            </span>
                        </div>
                    </div>
                    <div class="ui-notification-actions">
                        @if(! $notification->is_read && $notification->link)
                            <button class="link-btn" data-link="{{ $notification->link }}" onclick="markRead({{ $notification->id }}, true)">
                                <x-ui-icon name="arrow-right" class="mr-1" />Open &amp; mark as read
                            </button>
                        @endif
                        @if(! $notification->is_read)
                            <button class="link-btn" onclick="markRead({{ $notification->id }}, false)" title="Mark as read">
                                <x-ui-icon name="check"  />
                            </button>
                        @endif
                        <button class="delete-btn" onclick="deleteNotif({{ $notification->id }})" title="Archive">
                            <x-ui-icon name="archive"  />
                        </button>
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <h3 class="text-lg font-semibold text-gray-800">
                        @if($activeType)
                            No {{ strtolower($types[$activeType]['label']) }} notifications
                        @else
                            No notifications yet
                        @endif
                    </h3>
                    <p class="text-sm text-gray-500 mt-1">You'll see session updates, reminders, and system messages here.</p>
                </div>
            @endforelse
        </div>
        <div class="ui-notification-footer">{{ $notifications->links() }}</div>

        <p class="text-xs text-gray-500 mt-3">Showing {{ $notifications->firstItem() ?? 0 }}-{{ $notifications->lastItem() ?? 0 }} of {{ $notifications->total() }} notifications.</p>

    </main>

    @include('partials.sidebar', [
        'active' => ['notifications'],
        'role'   => 'Help Seeker',
    ])

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        async function post(url) {
            return fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            }).then(function (r) { return r.json(); });
        }

        async function markRead(id, navigate) {
            let target = null;

            if (navigate) {
                const btn = document.querySelector('#notif-' + id + ' .link-btn[data-link]');
                target = btn ? btn.dataset.link : null;
            }

            const data = await post('/notifications/' + id + '/read');

            if (data.read) {
                const card = document.getElementById('notif-' + id);
                if (card) {
                    card.classList.remove('unread');
                    card.querySelectorAll('.link-btn').forEach(function (b) { b.remove(); });
                }
                refreshCount();
            }

            if (navigate && data.read) {
                window.location = target || '/notifications';
            }
        }

        async function markAllRead() {
            await post('/notifications/read-all');
            document.querySelectorAll('.notif-card.unread').forEach(function (card) {
                card.classList.remove('unread');
                card.querySelectorAll('.link-btn').forEach(function (b) { b.remove(); });
            });
            const btn = document.getElementById('markAllBtn');
            if (btn) btn.remove();
            const subtitle = document.querySelector('p.text-sm.text-gray-500');
            if (subtitle) subtitle.innerHTML = "You're all caught up. ";
            refreshCount();
        }

        async function deleteNotif(id) {
            if (!confirm('Archive this notification?')) return;
            await fetch('/notifications/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
            });
            const card = document.getElementById('notif-' + id);
            if (card) card.remove();
            refreshCount();

            const list = document.getElementById('notifList');
            if (list && list.children.length === 0) {
                list.innerHTML = '<div class="empty-state"><h3 class="text-lg font-semibold text-gray-800">All caught up</h3><p class="text-sm text-gray-500 mt-1">You have no notifications left.</p></div>';
            }
        }

        async function refreshCount() {
            try {
                const res = await fetch('/notifications/unread-count');
                const data = await res.json();
                const badge = document.querySelector('.sidebar .nav .nav-item .badge');
                if (badge) {
                    if (data.count > 0) badge.textContent = data.count;
                    else badge.remove();
                }
            } catch (e) { /* ignore */ }
        }
    </script>

    @include('layouts.partials.pwa-banner')

</body>
</html>
