<x-admin.layout
    title="Notifications"
    page-title="Notifications"
    page-subtitle="Your account updates"
    active-nav="notifications"
    :admin="auth()->user()"
>
    <section class="admin-notifications">
        <header class="admin-section-heading">
            <div><h1>Notifications</h1><p>{{ $unreadCount }} unread. Updates sent to your administrator account.</p></div>
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button type="submit" class="admin-button admin-button-secondary">Mark all as read</button></form>
            @endif
        </header>

        <nav class="admin-notification-filters" aria-label="Notification types">
            <a href="{{ route('notifications') }}" @class(['is-active' => !$activeType]) @if(!$activeType) aria-current="page" @endif>All</a>
            @foreach($types as $key => $type)
                <a href="{{ route('notifications', ['type' => $key]) }}" @class(['is-active' => $activeType === $key]) @if($activeType === $key) aria-current="page" @endif>{{ $type['label'] }}</a>
            @endforeach
        </nav>

        <div class="admin-notification-list">
            @forelse($notifications as $notification)
                @php $unread = $notification->status === 'unread'; @endphp
                <article @class(['admin-notification', 'is-unread' => $unread])>
                    <div class="admin-notification-copy">
                        <strong>@if($unread)<span class="admin-notification-dot" role="img" aria-label="Unread"></span>@endif{{ $notification->title }}</strong>
                        <p>{{ $notification->message }}</p>
                        <small>{{ $types[$notification->notification_type]['label'] ?? 'System' }} · <time datetime="{{ $notification->created_at?->toIso8601String() }}">{{ $notification->created_at?->timezone('Asia/Manila')->format('M d, Y g:i A') }}</time></small>
                    </div>
                    <div class="admin-notification-actions">
                        @if($notification->link)<a class="admin-button admin-button-secondary" href="{{ $notification->link }}">Open</a>@endif
                        @if($unread)
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf<button type="submit" class="admin-button admin-button-secondary">Mark as read</button></form>
                        @endif
                    </div>
                </article>
            @empty
                <p class="admin-table-empty">No notifications yet.</p>
            @endforelse
        </div>
        {{ $notifications->links('vendor.pagination.compass', ['noun' => 'notifications']) }}
    </section>

    <style>
        .admin-notifications .admin-section-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}
        .admin-notifications .admin-section-heading h1{margin:0 0 4px;font-size:24px}
        .admin-notification-filters{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px}
        .admin-notification-filters a{padding:6px 12px;border:1px solid #dfe5ed;border-radius:999px;background:#fff;color:#334155;font-size:13px;text-decoration:none}
        .admin-notification-filters a.is-active{background:#2b6619;border-color:#2b6619;color:#fff}
        .admin-notification-list{display:grid;gap:10px}
        .admin-notification{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:16px 18px;border:1px solid #e3e8ef;border-radius:12px;background:#fff}
        .admin-notification.is-unread{border-left:3px solid #31711d;background:#f6fbf8}
        .admin-notification-copy{min-width:0}
        .admin-notification-copy strong{display:flex;align-items:center;gap:8px;font-size:14px;color:#163b2d}
        .admin-notification-copy p{margin:4px 0;font-size:14px;color:#334155}
        .admin-notification-copy small{font-size:12px;color:#566170}
        .admin-notification-dot{width:8px;height:8px;border-radius:50%;background:#31711d;flex:none}
        .admin-notification-actions{display:flex;flex-wrap:wrap;gap:8px;flex:none}
        @media(max-width:640px){.admin-notification,.admin-notifications .admin-section-heading{flex-direction:column}}
    </style>
</x-admin.layout>
