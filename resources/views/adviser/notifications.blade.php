@extends('layouts.app')
@section('title','Notifications - COMPASS')
@section('content')
@php
    $emergencyCopy = app(\App\Services\EmergencyNotificationPresentation::class);
    $resetHref = route('adviser.notifications', $history === 'archived' ? ['history' => 'archived'] : []);
    $chips = $typeFilter ? \App\Support\FilterChips::from(request(), ['type' => 'Type'], ['history']) : [];
@endphp
<div class="adviser-page-content notification-page">
<x-ui.page-header :title="$history==='archived' ? 'Archived Notifications' : 'Notifications'" :description="$history==='archived' ? 'Saved notification history. Restore a notification to return it to your inbox.' : 'Case updates and supervision alerts. '.$unreadCount.' unread.'">
    <x-slot:actions>
    @if($history==='archived')
        <a class="btn-secondary" href="{{ route('adviser.notifications') }}">Back to inbox</a>
    @else
        <a class="btn-secondary" href="{{ route('adviser.notifications',['history'=>'archived']) }}">Archived Notifications</a>
        @if($unreadCount)<form method="POST" action="{{ route('adviser.notifications.read-all') }}">@csrf<button class="btn-primary" type="submit"><x-ui-icon name="check-circle" /> Mark all as read</button></form>@endif
    @endif
    </x-slot:actions>
</x-ui.page-header>
@if(session('success'))<p class="ui-card" role="status">{{ session('success') }}</p>@endif
<x-ui.filter-bar :action="route('adviser.notifications')" aria-label="Notification filters">
    <input type="hidden" name="history" value="{{ $history }}">
    <x-ui.field label="Notification type" for="notificationType"><select id="notificationType" name="type" data-control="select"><option value="">All types</option>@foreach($types as $type)<option value="{{ $type }}" @selected($typeFilter===$type)>{{ ucwords(str_replace('_',' ',$type)) }}</option>@endforeach</select></x-ui.field>
    <x-slot:buttons><button class="btn-primary" type="submit">Filter</button></x-slot:buttons>
</x-ui.filter-bar>
<x-ui.filter-chips :chips="$chips" :reset-href="$resetHref" />
<x-ui.card class="ui-notification-list">
@forelse($notifications as $notification)
@php
    $emergency = str_contains(strtolower($notification->notification_type ?? ''), 'emergency') || str_contains(strtolower($notification->title ?? ''),'emergency');
    $copy = $emergencyCopy->present($notification, auth()->user());
    $description = \Illuminate\Support\Str::limit(strip_tags($copy['message'] ?? ''), 180);
    $unread = !$notification->is_read && $history==='inbox';
    $when = $notification->created_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A').' PHT / '.$notification->created_at->diffForHumans();
@endphp
<x-ui.list-row class="ui-notification-row {{ $unread ? 'av-notification-unread' : '' }} {{ $emergency ? 'av-notification-emergency' : '' }}" :initials="$emergency ? '!' : 'N'" :title="\Illuminate\Support\Str::limit($copy['title'] ?: 'System update', 90)" :subtitle="trim($description.' '.$when)">
    <x-slot:badge><x-ui.badge :tone="$emergency ? 'red' : ($unread ? 'green' : 'neutral')">{{ $history==='archived' ? 'Archived' : ($notification->is_read ? 'Read' : 'Unread') }}</x-ui.badge></x-slot:badge>
    <x-slot:actions>
    @if($history==='archived')
        <form method="POST" action="{{ route('adviser.notifications.restore',$notification->id) }}">@csrf<button class="btn-secondary" type="submit">Restore to inbox</button></form>
    @else
        @if($notification->link || !$notification->is_read)<form method="POST" action="{{ route('adviser.notifications.read',$notification->id) }}">@csrf<button class="btn-secondary" type="submit">{{ $notification->link ? 'Open update' : 'Mark as read' }}</button></form>@endif
        <form method="POST" action="{{ route('adviser.notifications.destroy',$notification->id) }}">@csrf @method('DELETE')<button class="btn-secondary" type="submit">Archive</button></form>
    @endif
    </x-slot:actions>
</x-ui.list-row>
@empty
<x-ui.empty :title="$history==='archived' ? 'No archived notifications' : 'No notifications'" :text="$history==='archived' ? 'No archived notifications match this filter.' : 'No notifications match this filter.'" />
@endforelse
</x-ui.card>
{{ $notifications->links('vendor.pagination.compass', ['noun' => 'notifications']) }}
</div>
@endsection
