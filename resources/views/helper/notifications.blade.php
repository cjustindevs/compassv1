@extends('layouts.helper')
@section('title','Notifications')
@section('body-class','notification-page')
@section('heading','Notifications')
@section('subheading','Assignments, reminders and important session updates.')
@section('content')
<div class="hf-page">
<section class="card ui-notification-list">
<header class="card-header ui-notification-list-heading">
<h3>Inbox <span class="hf-muted">{{ $unreadCount }} unread</span>
</h3>
<div class="hf-actions">
<a class="btn btn-secondary btn-sm" href="{{ route('notifications.archive') }}">Archived</a>
@if($unreadCount)
<form method="POST" action="{{ route('helper.notifications.read-all') }}">@csrf<button class="btn btn-secondary btn-sm">Mark all as read</button>
</form>
@endif</div>
</header>
@forelse($notifications as $item)
@php
$type=str_contains(strtolower($item->title),'overdue') ? 'Overdue' : (str_contains(strtolower($item->title),'completed') ? 'Completed action' : ucwords(str_replace('_',' ',$item->notification_type)));
@endphp
<article class="hf-notification ui-notification-row {{ !$item->is_read ? 'hf-notification-unread' : '' }}">
<div class="ui-notification-content">
<header>
<h3>{{ $item->title }}</h3>
<span class="hf-status {{ $item->notification_type==='emergency' || $type==='Overdue' ? 'hf-status-danger' : 'hf-status-muted' }}">{{ $type }}</span>
<time datetime="{{ $item->created_at?->toIso8601String() }}" title="{{ $item->created_at?->copy()->timezone('Asia/Manila')->format('M d, Y g:i A') }} PHT">{{ $item->created_at?->diffForHumans() }}</time>
</header>
<p class="hf-muted">{{ \Illuminate\Support\Str::limit($item->message,180) }}</p>
</div>
<div class="hf-actions ui-notification-actions">
@if($item->link)
<a class="btn btn-secondary btn-sm" href="{{ $item->link }}">Open update</a>
@endif @if(!$item->is_read)
<form method="POST" action="{{ route('helper.notifications.read',$item->id) }}">@csrf<button class="btn btn-secondary btn-sm">Mark as Read</button>
</form>
@endif<form method="POST" action="{{ route('notifications.destroy',$item->id) }}">@csrf @method('DELETE')
<button class="btn btn-secondary btn-sm">Archive</button>
</form>
</div>
</article>
@empty<div class="empty-state">
<h3>No notifications</h3>
<p>New assignments and important updates will appear here.</p>
</div>
@endforelse
<div class="hf-record-count ui-notification-footer">Showing {{ $notifications->firstItem() ?? 0 }}-{{ $notifications->lastItem() ?? 0 }} of {{ $notifications->total() }} updates</div>{{ $notifications->links() }}
</section>
</div>
@endsection
