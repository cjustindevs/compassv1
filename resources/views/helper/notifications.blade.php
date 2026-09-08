@extends('layouts.helper')

@section('title', 'Notifications')

@section('heading', 'Notifications')
@section('subheading', 'Stay up to date with assignments, evaluations, and reminders.')

@section('content')

    <div class="card">
        <div class="card-header">
            <h3>All Notifications</h3>
            <div style="display:flex;gap:8px;align-items:center;">
                <span class="pill" style="{{ $unreadCount > 0 ? 'background:#FEF3C7;color:#B45309;' : '' }}">{{ $unreadCount }} unread</span>
                @if($unreadCount > 0)
                    <form method="POST" action="{{ route('helper.notifications.read-all') }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-check-double"></i> Mark all read</button>
                    </form>
                @endif
            </div>
        </div>

        @if($notifications->isNotEmpty())
            <div style="display:flex;flex-direction:column;">
                @foreach($notifications as $item)
                    @php
                        $icon = $item->type_icon ?: '🔔';
                        $isUnread = $item->status === 'unread';
                    @endphp
                    <div class="notif-item {{ $isUnread ? 'unread' : '' }}">
                        <div class="notif-icon">{{ $icon }}</div>
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
                                <span class="font-semibold text-gray-800" style="font-size:14px;">
                                    {{ $item->title }}
                                    @if($isUnread)
                                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--green-500);margin-left:6px;"></span>
                                    @endif
                                </span>
                                <span class="text-xs text-gray-400" style="flex-shrink:0;">{{ $item->created_at?->diffForHumans() }}</span>
                            </div>
                            <div class="text-sm text-gray-500 mt-1">{{ $item->message }}</div>
                            <div style="display:flex;gap:10px;margin-top:8px;flex-wrap:wrap;">
                                @if($item->link)
                                    <a href="{{ $item->link }}" class="btn btn-secondary btn-sm"><i class="fas fa-external-link-alt"></i> View</a>
                                @endif
                                @if($isUnread)
                                    <form method="POST" action="{{ route('helper.notifications.read', ['id' => $item->id]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-check"></i> Mark read</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($notifications->hasPages())
                <div style="margin-top:16px;">
                    {{ $notifications->links() }}
                </div>
            @endif
        @else
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>No notifications</h3>
                <p>New assignments and updates will appear here.</p>
            </div>
        @endif
    </div>

@endsection
