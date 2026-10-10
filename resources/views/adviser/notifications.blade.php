@extends('layouts.app')
@section('title','Notifications - COMPASS')
@section('content')
@php
    $inlineNotices = ['success'];
@endphp
<div class="adviser-page-content av-page notification-page">
<header><div><h1>{{ $history==='archived' ? 'Archived Notifications' : 'Notifications' }}</h1><p class="av-muted">{{ $history==='archived' ? 'Saved notification history. Restore a notification to return it to your inbox.' : 'Case updates and supervision alerts. '.$unreadCount.' unread.' }}</p></div><div class="av-actions">
@if($history==='archived')<a class="av-button" href="{{ route('adviser.notifications') }}">Back to inbox</a>@else<a class="av-button" href="{{ route('adviser.notifications',['history'=>'archived']) }}">Archived Notifications</a>@if($unreadCount)<form method="POST" action="{{ route('adviser.notifications.read-all') }}">@csrf<button class="av-button" type="submit">Mark all as read</button></form>@endif @endif
</div></header>
@if(session('success'))<p class="av-note" role="status">{{ session('success') }}</p>@endif
<form method="GET" class="av-filter mb-5"><input type="hidden" name="history" value="{{ $history }}"><label>Notification type<select name="type"><option value="">All types</option>@foreach($types as $type)<option value="{{ $type }}" @selected($typeFilter===$type)>{{ ucwords(str_replace('_',' ',$type)) }}</option>@endforeach</select></label><button class="av-button" type="submit">Filter</button></form>
<div class="ui-notification-list">
@forelse($notifications as $notification)
@php
    $emergency = str_contains(strtolower($notification->notification_type ?? ''), 'emergency') || str_contains(strtolower($notification->title ?? ''),'emergency');
    $description = \Illuminate\Support\Str::limit(strip_tags($notification->message ?? ''), 180);
@endphp
<article class="av-panel av-notification ui-notification-row {{ !$notification->is_read && $history==='inbox' ? 'av-notification-unread' : '' }} {{ $emergency ? 'av-notification-emergency' : '' }}">
<div class="ui-notification-content"><header class="av-heading"><div><h2>{{ \Illuminate\Support\Str::limit($notification->title ?: 'System update', 90) }}</h2><p class="av-muted">{{ $description }}</p><time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT / {{ $notification->created_at->diffForHumans() }}</time></div><span class="av-badge {{ $emergency ? 'av-badge-alert' : 'av-badge-muted' }}">{{ $history==='archived' ? 'Archived' : ($notification->is_read ? 'Read' : 'Unread') }}</span></header></div>
<div class="av-actions ui-notification-actions">
@if($history==='archived')<form method="POST" action="{{ route('adviser.notifications.restore',$notification->id) }}">@csrf<button class="av-button" type="submit">Restore to inbox</button></form>
@else
@if($notification->link || !$notification->is_read)<form method="POST" action="{{ route('adviser.notifications.read',$notification->id) }}">@csrf<button class="av-button" type="submit">{{ $notification->link ? 'Open update' : 'Mark as read' }}</button></form>@endif
<form method="POST" action="{{ route('adviser.notifications.destroy',$notification->id) }}">@csrf @method('DELETE')<button class="av-button" type="submit">Archive</button></form>
@endif
</div></article>
@empty<section class="av-panel av-empty">{{ $history==='archived' ? 'No archived notifications match this filter.' : 'No notifications match this filter.' }}</section>@endforelse
</div>
<div class="av-pagination">{{ $notifications->links() }}</div>
</div>
@endsection
