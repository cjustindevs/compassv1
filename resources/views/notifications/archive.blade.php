@extends('layouts.app')
@section('title', 'Archived notifications - COMPASS')
@section('content')
@include('partials.management-ui-styles')
@php($notificationRoute = match(auth()->user()->role) { 'helper' => 'helper.notifications', 'adviser' => 'adviser.notifications', 'moderator' => 'moderator.notifications', default => 'notifications.index' })
<div class="cm-page">
    <header class="cm-header"><div><h1>Archived notifications</h1><p class="cm-muted">Your archived messages are kept here for reference.</p></div>@if(Route::has($notificationRoute))<a class="cm-button" href="{{ route($notificationRoute) }}">Back to notifications</a>@endif</header>
    <section class="cm-card"><div class="cm-list-head"><h2>Notification history</h2><span class="cm-badge off">{{ $notifications->total() }} archived</span></div>
        @forelse($notifications as $notification)
        <article class="cm-entry"><div class="cm-list-head"><h2>{{ $notification->title }}</h2><time class="cm-time" datetime="{{ $notification->archived_at->toIso8601String() }}">{{ $notification->archived_at->timezone('Asia/Manila')->format('M j, Y ? g:i A') }} PHT</time></div><p class="cm-message">{{ $notification->message }}</p><span class="cm-muted">Archived</span></article>
        @empty<div class="cm-empty"><h2>No archived notifications yet</h2><p>Notifications you archive will appear here.</p></div>@endforelse
        <div class="cm-pagination">{{ $notifications->links() }}</div>
    </section>
</div>
@endsection
