@extends('layouts.app')
@section('title', 'Live sessions - COMPASS')
@section('content')
<div class="adviser-page-content">
    <x-ui.page-header title="Live sessions" description="Active chats and voice calls of the Helpers you supervise. Read the chat, listen to a call (receive-only), and keep notes. Seekers are shown by alias.">
        <x-slot:actions>
            <a class="btn-secondary" href="{{ route('adviser.live.index') }}">Refresh</a>
        </x-slot:actions>
    </x-ui.page-header>
    @if(session('status'))<p class="ui-notice" role="status">{{ session('status') }}</p>@endif
    @if($sessions->isEmpty())
        <x-ui.empty title="No live sessions right now" text="When one of your Helpers starts a chat or a voice call, it will appear here." />
    @else
        <div class="ui-card-grid">
            @foreach($sessions as $session)
                @php($risk = $session->risk_level ?? 'low')
                <x-ui.card>
                    <div class="ui-badge-row">
                        <x-ui.badge tone="red">Live</x-ui.badge>
                        <x-ui.badge tone="neutral">{{ $session->session_type === 'voice' ? 'Voice call' : 'Chat' }}</x-ui.badge>
                        <x-ui.badge :tone="in_array($risk, ['high', 'emergency']) ? 'red' : ($risk === 'moderate' ? 'amber' : 'neutral')">{{ ucfirst($risk) }} risk</x-ui.badge>
                    </div>
                    <h2 class="ui-card-title">{{ $session->helper?->full_name ?? 'Helper' }}</h2>
                    <p class="ui-card-sub">With {{ $session->seeker?->generated_alias ?? 'Seeker' }} · {{ $session->concern_label ?? 'General concern' }}</p>
                    <p class="ui-card-sub">Started {{ $session->start_time?->copy()->timezone('Asia/Manila')->format('g:i A') }} PHT · {{ $session->start_time?->diffForHumans(null, true) }} ago</p>
                    <div class="ui-card-foot"><a class="btn-primary" href="{{ route('adviser.live.show', $session->id) }}">{{ $session->session_type === 'voice' ? 'Open and listen' : 'Watch live' }}</a></div>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</div>
@endsection
