@extends('layouts.app')
@section('title', 'Archived notifications - COMPASS')
@section('content')
@include('partials.management-ui-styles')
@php($notificationRoute = match(auth()->user()->role) { 'helper' => 'helper.notifications', 'adviser' => 'adviser.notifications', 'moderator' => 'moderator.notifications', default => 'notifications' })
<div class="cm-page notification-page">
    <header class="cm-header"><div><h1>Archived notifications</h1><p class="cm-muted">Your archived messages are kept here for reference.</p></div>@if(Route::has($notificationRoute))<a class="cm-button" href="{{ route($notificationRoute) }}">Back to notifications</a>@endif</header>
    <section class="cm-card"><div class="cm-list-head"><h2>Archive preference</h2></div>
        <form method="POST" action="{{ route('notifications.auto-archive') }}" class="cm-form">
            @csrf
            <label class="cm-field-label" for="auto_archive_read_days">Automatically archive read notifications after</label>
            <select id="auto_archive_read_days" name="auto_archive_read_days" class="cm-input">
                <option value="0" @selected(!$user_preference)>Keep read notifications in my inbox</option>
                <option value="7" @selected($user_preference === 7)>7 days</option>
                <option value="30" @selected($user_preference === 30)>30 days</option>
                <option value="90" @selected($user_preference === 90)>90 days</option>
            </select>
            <button type="submit" class="cm-button">Save preference</button>
        </form>
    </section>
    <section class="cm-card ui-notification-list"><div class="cm-list-head ui-notification-list-heading"><h2>Notification history</h2><span class="cm-badge off">{{ $notifications->total() }} archived</span></div>
        @forelse($notifications as $notification)
        <article class="cm-entry ui-notification-row"><div class="ui-notification-content"><div class="cm-list-head"><h2>{{ $notification->title }}</h2><time class="cm-time" datetime="{{ $notification->archived_at->toIso8601String() }}">{{ $notification->archived_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} PHT</time></div><p class="cm-message">{{ $notification->message }}</p>
            </div><div class="cm-actions ui-notification-actions"><form method="POST" action="{{ route('notifications.restore', $notification->id) }}" data-confirm="Restore notification?" data-confirm-message="This notification will return to your active inbox." data-confirm-text="Restore">@csrf<button type="submit" class="cm-button">Restore to inbox</button></form></div>
        </article>
        @empty<div class="cm-empty"><h2>No archived notifications yet</h2><p>Notifications you archive will appear here.</p></div>@endforelse
        <div class="cm-pagination">{{ $notifications->links() }}</div>
    </section>
</div>
@endsection
