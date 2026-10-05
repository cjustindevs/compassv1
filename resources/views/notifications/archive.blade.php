@extends('layouts.app')
@section('content')
@include('partials.referral-ui-styles')
<div class="referral-ui"><h1>Archived notifications</h1><p class="ru-muted">Archived messages remain available here for your records.</p>
@forelse($notifications as $notification)<article class="ru-card"><h2>{{ $notification->title }}</h2><p>{{ $notification->message }}</p><p class="ru-muted">Archived {{ $notification->archived_at->timezone('Asia/Manila')->format('M j, Y g:i A') }} PHT</p></article>@empty<div class="ru-card">You have no archived notifications.</div>@endforelse
{{ $notifications->links() }}</div>
@endsection
