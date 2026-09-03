@extends('layouts.app')

@section('title', 'COMPASS – Session Monitor')

@push('styles')
<style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--bg-primary, #F8FBF9); }

        .stat-card {
            background: var(--bg-card, #fff);
            border-radius: 16px;
            padding: 16px 20px;
            border: 1px solid var(--border-color, #E5E7EB);
        }
        .stat-label { font-size: 12px; color: var(--text-secondary, #6B7280); }
        .stat-value { font-size: 18px; font-weight: 800; color: var(--text-primary, #1F2937); }

        #chatMessages {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 18px;
            max-height: 60vh;
            overflow-y: auto;
        }
        .message {
            background: var(--bg-secondary, #fff);
            border: 1px solid var(--border-color, #E5E7EB);
            border-radius: 12px;
            padding: 10px 14px;
            max-width: 85%;
            align-self: flex-start;
        }
        .message .sender-name {
            font-size: 12px;
            font-weight: 700;
            color: #04A052;
            display: block;
            margin-bottom: 2px;
        }
        .message .text { font-size: 14px; color: var(--text-primary, #1F2937); white-space: pre-wrap; }
        .message .time { font-size: 10px; color: var(--text-muted, #9CA3AF); margin-left: 8px; }

        .live-dot {
            width: 9px; height: 9px; border-radius: 50%;
            background: #10B981; display: inline-block; margin-right: 6px;
            box-shadow: 0 0 0 0 rgba(16,185,129,0.6);
            animation: livePulse 1.8s infinite;
        }
        @keyframes livePulse {
            0% { box-shadow: 0 0 0 0 rgba(16,185,129,0.5); }
            70% { box-shadow: 0 0 0 8px rgba(16,185,129,0); }
            100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
        }
    </style>
@endpush

@section('content')
<div class="adviser-page-content">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
            <div>
                <a href="{{ route('adviser.dashboard') }}" class="text-sm text-gray-500 hover:text-gray-700">
                    <i class="fas fa-arrow-left mr-1"></i> Back to dashboard
                </a>
                <h1 class="text-2xl font-bold text-gray-800 mt-1">Session Monitor</h1>
                <p class="text-sm text-gray-500 flex items-center mt-1">
                    <span class="live-dot"></span> Live · read-only supervision
                </p>
            </div>
            <div class="flex items-center gap-2">
                @if($session->report)
                    <a href="{{ route('adviser.evaluate', $session->report->id) }}"
                       class="btn-primary" style="background:#04A052;color:#fff;padding:8px 16px;border-radius:20px;font-weight:600;font-size:13px;text-decoration:none;">
                        <i class="fas fa-clipboard-check mr-1"></i> Evaluate Helper
                    </a>
                @endif
            </div>
        </div>

        <!-- Session meta -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="stat-label">Seeker</div>
                <div class="stat-value">{{ $session->seeker?->generated_alias ?? 'Anonymous' }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Helper</div>
                <div class="stat-value">{{ $session->helper?->full_name ?? 'Unassigned' }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Concern</div>
                <div class="stat-value">{{ $session->concern?->concern_name ?? 'General' }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Status / Risk</div>
                <div class="stat-value">
                    {{ ucfirst(str_replace('_', ' ', $session->session_status)) }}
                    <span class="text-xs font-semibold
                        {{ in_array($session->risk_level, ['high','emergency']) ? 'text-red-600' : 'text-emerald-600' }}">
                        · {{ ucfirst($session->risk_level ?? 'low') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Live message thread -->
        <div class="card" style="background:var(--bg-card,#fff);border:1px solid var(--border-color,#E5E7EB);border-radius:20px;padding:8px;">
            <div class="px-4 py-3 border-b" style="border-color:var(--border-color,#E5E7EB);">
                <h3 class="font-bold text-gray-800">Conversation</h3>
                <p class="text-xs text-gray-400">Messages appear here as they are sent — no refresh needed.</p>
            </div>
            <div id="chatMessages">
                @forelse($session->messages as $msg)
                    <div class="message" data-id="{{ $msg->id }}">
                        <span class="sender-name">
                            {{ $msg->sender_role === 'helper' ? ($session->helper?->full_name ?? 'Helper') : ($session->seeker?->generated_alias ?? 'Seeker') }}
                        </span>
                        <div class="text">{{ $msg->message_text }}</div>
                        <span class="time">{{ $msg->time_formatted ?? ($msg->sent_datetime?->format('g:i A') ?? '') }}</span>
                    </div>
                @empty
                    <div class="text-center text-gray-400 py-10" id="emptyChat">
                        <i class="fas fa-comments text-4xl mb-2 block"></i>
                        No messages yet.
                    </div>
                @endforelse
            </div>
        </div>
</div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sessionId = {{ $session->id }};
            const userId = document.querySelector('meta[name="user-id"]')?.content;
            const container = document.getElementById('chatMessages');
            if (!window.Echo || !container) return;

            const channel = window.Echo.private('session.' + sessionId);

            channel.listen('MessageSent', (event) => {
                appendMessage(event);
            });

            channel.listen('SessionUpdated', (event) => {
                if (window.showToast) window.showToast(event.message || 'Session updated', 'info');
            });

            channel.listen('SessionEnded', (event) => {
                if (window.showToast) {
                    window.showToast('Session ended' + (event.ended_by ? ' by ' + event.ended_by : ''), 'info');
                }
                setTimeout(() => {
                    window.location.href = event.seeker_redirect || event.helper_redirect || '{{ route('adviser.dashboard') }}';
                }, 3000);
            });

            function appendMessage(event) {
                if (document.querySelector('[data-id="' + event.id + '"]')) return;
                const empty = document.getElementById('emptyChat');
                if (empty) empty.remove();

                const div = document.createElement('div');
                div.className = 'message';
                div.dataset.id = event.id;

                const mine = String(event.sender_id) === String(userId);
                const name = event.sender_name
                    || (mine ? 'You' : (event.sender_role === 'helper' ? 'Helper' : 'Seeker'));

                div.innerHTML =
                    '<span class="sender-name">' + esc(name) + '</span>' +
                    '<div class="text">' + esc(event.message) + '</div>' +
                    '<span class="time">' + esc(event.sent_datetime || '') + '</span>';

                container.appendChild(div);
                container.scrollTop = container.scrollHeight;
            }

            function esc(text) {
                const d = document.createElement('div');
                d.textContent = text ?? '';
                return d.innerHTML;
            }
        });
    </script>
@endsection
