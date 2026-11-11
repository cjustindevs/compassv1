@extends('layouts.app')

@section('title', 'COMPASS – Notifications')

@push('styles')
    <meta name="user-id" content="{{ auth()->id() }}">
    @vite(['resources/js/moderator-notifications.js'])
    <style>
        .notif-item { display: flex; align-items: flex-start; gap: 14px; padding: 14px 16px; border-bottom: 1px solid var(--compass-divider, #F3F4F6); }
        .notif-item:last-child { border-bottom: none; }
        .notif-item.unread { background: #F0FDF4; }
        .notif-item .content { flex: 1; min-width: 0; }
        .notif-item .content .title { font-weight: 600; font-size: 15px; color: var(--compass-text, #1F2937); margin: 0; }
        .notif-item .content .msg { font-size: 14px; color: var(--compass-muted, #6B7280); margin: 2px 0 0; }
        .notif-item .content .time { font-size: 13px; color: var(--compass-muted, #6B7280); margin: 4px 0 0; }
        .notif-item .actions { display: flex; gap: 6px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #04A052; flex-shrink: 0; margin-top: 6px; }
        .notif-flash { background: #EAF8F0; color: #027039; border: 1px solid #D0F0D8; border-radius: 12px; padding: 12px 16px; font-size: 14px; font-weight: 500; margin-bottom: 16px; }
        .filter-pill { display: inline-flex; align-items: center; min-height: 44px; padding: 0 16px; border-radius: 999px; font-size: 14px; font-weight: 600; color: var(--compass-muted, #6B7280); background: var(--bg-card, #fff); border: 1px solid var(--compass-border, #E5E7EB); text-decoration: none; }
        .filter-pill:hover { border-color: var(--compass-green, #04A052); color: var(--compass-green, #04A052); }
        .filter-pill.active { background: #EAF8F0; border-color: var(--compass-green, #04A052); color: #027039; }
        .btn-ghost { background: var(--bg-card, #fff); color: var(--compass-muted, #6B7280); padding: 0 14px; min-height: 40px; border-radius: 999px; font-weight: 600; font-size: 13px; border: 1px solid var(--compass-border, #E5E7EB); cursor: pointer; }
        .btn-ghost:hover { border-color: #DC2626; color: #DC2626; }
        .notification-page .ui-notification-list { padding: 0; }
        .notification-page .ui-notification-toolbar { align-items: center; }
        .notification-group{border-bottom:1px solid #e5ede8;padding:14px 0}.notification-group summary{cursor:pointer;font-size:14px;font-weight:600}.moderator-emergency-notification{border-left:3px solid #c34242;padding-left:14px!important}.moderator-emergency-notification .title{color:#982e2e!important}.notif-item .content{min-width:0;overflow-wrap:anywhere}.notif-item .actions{flex-wrap:wrap}.notif-item .actions .btn-ghost{font-size:12px;min-height:40px;padding:8px 10px;width:auto}
        @media(max-width:480px){.notif-item{flex-wrap:wrap}.notif-item .actions{width:100%}.notif-item .content{flex:1 1 210px}}
    </style>
@endpush

@section('content')
<div class="notification-page">
    <x-ui.page-header title="Notifications" :description="$unreadCount.' unread'">
        <x-slot:actions>
            <form method="POST" action="{{ route('moderator.notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn-primary"><x-ui-icon name="check-circle" class="mr-1" /> Mark all as read</button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    @if(session('success'))
        <div class="notif-flash" role="status"> {{ session('success') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-2 mb-6 ui-notification-toolbar">
        <a href="{{ route('moderator.notifications') }}" class="filter-pill {{ ! $typeFilter ? 'active' : '' }}">All</a>
        @foreach($types as $type)
            <a href="{{ route('moderator.notifications', ['type' => $type]) }}"
               class="filter-pill {{ $typeFilter === $type ? 'active' : '' }}">
                {{ ucfirst($type) }}
            </a>
        @endforeach
    </div>

    <div class="ui-card ui-notification-list">
            @php
                // Group only identical non-emergency events in one five-minute window.
                // Distinct destinations and read states remain separate; every child retains its own action.
                $groups = $notifications->getCollection()->groupBy(fn($n) => $n->notification_type === 'emergency' ? 'emergency-'.$n->id : hash('sha256', implode('|',[$n->notification_type,$n->title,$n->message,$n->link,$n->status,intdiv($n->created_at->timestamp,300)])));
            @endphp
            @forelse($groups as $updates)
                @if($updates->count()>1)<details class="notification-group"><summary>{{ $updates->count() }} updates: {{ \Illuminate\Support\Str::limit($updates->first()->title,80) }}</summary><p class="time">{{ $updates->first()->created_at->copy()->timezone('Asia/Manila')->format('M j, Y g:i A') }} PHT</p>@endif
                @foreach($updates as $notification)
                <div class="notif-item ui-notification-row {{ $notification->notification_type === 'emergency' ? 'moderator-emergency-notification' : '' }} {{ $notification->status === 'unread' ? 'unread' : '' }}">
                    <div class="content ui-notification-content">
                        <p class="title">@if($notification->status === 'unread')<span class="dot" aria-label="Unread"></span>@endif{{ \Illuminate\Support\Str::limit($notification->title,80) }}</p>
                        <p class="msg">{{ \Illuminate\Support\Str::limit(preg_split('/(?<=[.!?])\s+/', strip_tags($notification->message),2)[0],180) }}</p>
                        <p class="time">{{ $notification->created_at->copy()->timezone('Asia/Manila')->format('M j, Y g:i A') }} PHT</p>
                    </div>
                    <div class="actions ui-notification-actions">
                        <form method="POST" action="{{ route('moderator.notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" class="btn-ghost" title="Mark as read / open">
                                {{ $notification->link ? 'Open update' : 'Mark as read' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('moderator.notifications.destroy', $notification->id) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-ghost" title="Archive">
                                Archive
                            </button>
                        </form>
                    </div>
                </div>
                @endforeach
                @if($updates->count()>1)</details>@endif
            @empty
                <x-ui.empty title="No notifications" text="New updates will appear here." />
            @endforelse
    </div>

    <div class="mt-6">{{ $notifications->links() }}</div>
</div>
@endsection
