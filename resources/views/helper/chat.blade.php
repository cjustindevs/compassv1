@extends('layouts.helper')

@section('title', 'Live Chat')

@section('heading', 'Live Chat')
@section('subheading', isset($activeSession) ? 'Conversation with ' . ($seekerName ?? 'Seeker') : 'Choose a session to start chatting.')

@section('styles')
    @vite(['resources/js/app.js', 'resources/js/chat.js'])
    <style>
        .chat-messages .message { max-width: 72%; padding: 10px 14px; border-radius: 16px; font-size: 14px; line-height: 1.5; word-break: break-word; }
        .chat-messages .message.seeker { align-self: flex-end; background: var(--green-500); color: white; border-bottom-right-radius: 4px; }
        .chat-messages .message.helper { align-self: flex-start; background: var(--gray-100); color: var(--gray-800); border-bottom-left-radius: 4px; }
        .chat-messages .message .sender-name { display: block; font-size: 11px; font-weight: 600; margin-bottom: 2px; }
        .chat-messages .message.seeker .sender-name { color: rgba(255,255,255,0.85); }
        .chat-messages .message.helper .sender-name { color: var(--green-700); }
        .chat-messages .message p { margin: 0; }
        .chat-messages .message .time { display: block; font-size: 11px; opacity: 0.7; margin-top: 4px; text-align: right; }
    </style>
@endsection

@section('content')

    @if(isset($activeSession))

        <div class="card" style="padding:0;overflow:hidden;">
            <!-- Chat header -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-bottom:1px solid var(--gray-200);gap:12px;flex-wrap:wrap;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div class="avatar-sm">{{ \Illuminate\Support\Str::substr($seekerName ?? 'S', 0, 2) }}</div>
                    <div>
                        <div class="font-semibold text-gray-800">{{ $seekerName ?? 'Seeker' }}</div>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <span class="risk-badge {{ $activeSession->risk_level ?? 'low' }}">{{ ucfirst($activeSession->risk_level ?? 'Low') }}</span>
                            <span class="text-xs text-gray-400">{{ $activeSession->mode_label }} · {{ $activeSession->status_label }}</span>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:8px;">
                    <a href="{{ route('helper.session.notes', ['id' => $activeSession->id]) }}" class="btn btn-secondary btn-sm"><i class="fas fa-edit"></i> Notes</a>
                    @if($activeSession->session_status !== 'completed')
                        <form method="POST" action="{{ route('helper.session.end', ['id' => $activeSession->id]) }}" onsubmit="return confirm('End this session?');">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-stop-circle"></i> End</button>
                        </form>
                    @endif
                </div>
            </div>

            <!-- Messages -->
            <div class="chat-window">
                <div class="chat-messages" id="chatMessages">
                    <div class="empty-state chat-empty"><i class="fas fa-comments"></i><p>No messages yet. Say hello to the seeker.</p></div>
                </div>

                <!-- Input -->
                <div class="chat-input-row">
                    <input type="text" id="messageInput" class="form-control" placeholder="Type a message..." autocomplete="off">
                    <button id="sendButton" class="btn btn-primary" style="padding:10px 20px;"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>

        <input type="hidden" id="sessionId" value="{{ $activeSession->id }}">
        <input type="hidden" id="currentUserId" value="{{ auth()->id() }}">
        <input type="hidden" id="currentUserRole" value="{{ auth()->user()->role }}">

    @else

        <div class="card">
            <div class="card-header">
                <h3>Select a session</h3>
                <span class="text-xs text-gray-400">{{ count($sessions ?? []) }} session(s)</span>
            </div>
            @if(isset($sessions) && $sessions->isNotEmpty())
                <div style="display:flex;flex-direction:column;">
                    @foreach($sessions as $session)
                        <a href="{{ route('helper.chat.show', ['id' => $session->id]) }}" style="display:flex;align-items:center;gap:14px;padding:14px;border-radius:14px;text-decoration:none;color:var(--gray-700);transition:all .2s ease;" onmouseover="this.style.background='var(--green-50)'" onmouseout="this.style.background='transparent'">
                            <div class="avatar-sm">{{ \Illuminate\Support\Str::substr($session->seeker->generated_alias ?? 'S', 0, 2) }}</div>
                            <div style="flex:1;min-width:0;">
                                <div class="font-semibold text-gray-800">{{ $session->seeker->generated_alias ?? 'Seeker' }}</div>
                                <div class="text-xs text-gray-400">{{ $session->concern->concern_name ?? 'Session' }} · {{ $session->created_at?->diffForHumans() }}</div>
                                @if($session->messages->isNotEmpty())
                                    <div class="text-xs text-gray-500 mt-1 trnucate" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;">{{ $session->messages->sortByDesc('sent_datetime')->first()->message_text }}</div>
                                @endif
                            </div>
                            <span class="status-badge {{ str_replace('_', '-', $session->session_status) }}">{{ $session->status_label }}</span>
                            <i class="fas fa-chevron-right text-gray-300"></i>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <i class="fas fa-comment-slash"></i>
                    <h3 style="font-size:16px;font-weight:700;color:var(--gray-800);margin-bottom:6px;">No active sessions</h3>
                    <p>No active sessions. Please check your assigned cases.</p>
                    <a href="{{ route('helper.cases') }}" class="btn btn-primary btn-sm" style="margin-top:14px;">
                        <i class="fas fa-folder-open mr-1"></i> View Assigned Cases
                    </a>
                </div>
            @endif
        </div>

    @endif

@endsection
