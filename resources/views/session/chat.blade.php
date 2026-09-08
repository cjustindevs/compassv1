<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.pwa-meta')
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Chat with {{ $helperName }}</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #F8FBF9; height: 100vh; overflow: hidden; }

        .chat-wrapper {
            display: flex;
            flex-direction: column;
            height: 100vh;
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            box-shadow: 0 0 40px rgba(0, 0, 0, 0.05);
        }

        /* ─── Header ─── */
        .chat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 20px;
            border-bottom: 1px solid #e5e7eb;
            background: #ffffff;
            flex-shrink: 0;
        }
        .chat-header .back-btn {
            color: #6b7280;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
            flex-shrink: 0;
        }
        .chat-header .back-btn:hover { color: #1f2937; }
        .chat-header .helper-info { display: flex; align-items: center; gap: 12px; min-width: 0; flex: 1; }
        .chat-header .helper-info .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #04A052, #038A45);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .chat-header .helper-info .meta { min-width: 0; }
        .chat-header .helper-info .name {
            font-weight: 600;
            font-size: 15px;
            color: #1f2937;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .chat-header .helper-info .status {
            font-size: 12px;
            color: #059669;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .chat-header .helper-info .status .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            display: inline-block;
            animation: pulse-dot 2s infinite;
        }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .chat-header .session-timer { font-size: 12px; color: #9ca3af; font-variant-numeric: tabular-nums; }
        .chat-header .end-btn {
            color: #ef4444;
            font-size: 13px;
            font-weight: 600;
            background: none;
            border: 1px solid #fecaca;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .chat-header .end-btn:hover { background: #fef2f2; }

        /* ─── Messages ─── */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px 24px;
            background: #f8fbf9;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .chat-messages .message {
            max-width: 75%;
            padding: 10px 14px;
            border-radius: 16px;
            word-wrap: break-word;
            position: relative;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .chat-messages .message.seeker {
            background: #04A052;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        .chat-messages .message.helper {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }
        .chat-messages .message .sender-name {
            font-size: 11px;
            font-weight: 700;
            display: block;
            margin-bottom: 3px;
            letter-spacing: 0.01em;
        }
        .chat-messages .message.seeker .sender-name { color: rgba(255, 255, 255, 0.85); }
        .chat-messages .message.helper .sender-name { color: #6b7280; }
        .chat-messages .message .text { font-size: 14px; line-height: 1.5; word-break: break-word; }
        .chat-messages .message .time {
            font-size: 10px;
            opacity: 0.65;
            display: block;
            text-align: right;
            margin-top: 4px;
        }
        .chat-messages .message.seeker .time { color: rgba(255, 255, 255, 0.75); }
        .chat-messages .message.helper .time { color: #9ca3af; }

        /* ─── Date dividers ─── */
        .date-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 8px 0;
            gap: 12px;
        }
        .date-divider span {
            font-size: 11px;
            font-weight: 600;
            color: #9ca3af;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            padding: 3px 14px;
            border-radius: 20px;
        }

        /* ─── Typing indicator ─── */
        .typing-indicator {
            align-self: flex-start;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            border-bottom-left-radius: 4px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            max-width: 75%;
        }
        .typing-indicator .dots { display: flex; gap: 4px; }
        .typing-indicator .dots span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #9ca3af;
            display: inline-block;
            animation: typing 1.4s infinite ease-in-out;
        }
        .typing-indicator .dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator .dots span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30% { transform: translateY(-5px); opacity: 1; }
        }
        .typing-indicator .label { font-size: 12px; color: #6b7280; }

        /* ─── Empty state ─── */
        .empty-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            padding: 40px;
            text-align: center;
        }
        .empty-chat .icon { font-size: 56px; margin-bottom: 14px; opacity: 0.5; }
        .empty-chat h3 { font-size: 18px; font-weight: 700; color: #1f2937; margin-bottom: 6px; }
        .empty-chat p { font-size: 14px; max-width: 320px; line-height: 1.5; }

        /* ─── Input ─── */
        .chat-input {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            border-top: 1px solid #e5e7eb;
            background: #ffffff;
            flex-shrink: 0;
        }
        .chat-input input {
            flex: 1;
            padding: 11px 18px;
            border-radius: 24px;
            border: 1.5px solid #e5e7eb;
            outline: none;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
            min-width: 0;
        }
        .chat-input input:focus {
            border-color: #04A052;
            box-shadow: 0 0 0 3px rgba(4, 160, 82, 0.08);
        }
        .chat-input input:disabled { opacity: 0.6; cursor: not-allowed; }
        .chat-input button {
            padding: 11px 22px;
            border-radius: 24px;
            background: #04A052;
            color: white;
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .chat-input button:hover { background: #038A45; }
        .chat-input button:disabled { opacity: 0.5; cursor: not-allowed; }

        /* ─── Responsive ─── */
        @media (max-width: 640px) {
            .chat-header { padding: 12px 14px; }
            .chat-header .helper-info .avatar { width: 34px; height: 34px; font-size: 15px; }
            .chat-header .helper-info .name { font-size: 14px; }
            .chat-header .session-timer { display: inline; }
            .chat-messages { padding: 14px 14px; }
            .chat-messages .message { max-width: 85%; padding: 8px 12px; }
            .chat-messages .message .text { font-size: 13px; }
            .chat-input { padding: 12px 14px; gap: 8px; }
            .chat-input button { padding: 11px 16px; font-size: 13px; }
        }
        @media (max-width: 400px) {
            .chat-messages .message { max-width: 90%; }
            .chat-header .back-btn span { display: none; }
            .chat-header .back-btn { font-size: 16px; }
        }
    </style>

    @vite(['resources/js/app.js', 'resources/js/chat.js'])
</head>
<body>

    <input type="hidden" id="sessionId" value="{{ $session->id }}">
    <input type="hidden" id="currentUserId" value="{{ auth()->id() }}">
    <input type="hidden" id="currentUserRole" value="{{ auth()->user()->role }}">
    <input type="hidden" id="peerName" value="{{ $helperName }}">

    <div class="chat-wrapper">

        <!-- Header -->
        <div class="chat-header">
            <a href="{{ route('seeker.dashboard') }}" class="back-btn">
                <i class="fas fa-arrow-left"></i> <span>Back</span>
            </a>
            <div class="helper-info">
                <div class="avatar">{{ substr($helperName, 0, 1) }}</div>
                <div class="meta">
                    <div class="name">{{ $helperName }}</div>
                    <div class="status"><span class="dot"></span> Online</div>
                </div>
            </div>
            <span class="session-timer" id="sessionTimer" data-started-at="{{ optional($session->start_time)->timestamp }}">00:00</span>
            <form method="POST" action="{{ route('session.end') }}"
                  data-confirm="End session?"
                  data-confirm-message="This will end the session for both you and the helper."
                  data-confirm-text="End session"
                  data-confirm-class="bg-red-600 hover:bg-red-700 focus:ring-red-500">
                @csrf
                <button type="submit" class="end-btn"><i class="fas fa-phone-slash"></i> End</button>
            </form>
        </div>

        <!-- Messages -->
        <div class="chat-messages" id="chatMessages">
            <div class="empty-chat" id="emptyChat">
                <div class="icon">💬</div>
                <h3>No messages yet</h3>
                <p>Say hello to your helper to get started.</p>
            </div>

            <!-- Typing indicator (shown via JavaScript) -->
            <div class="typing-indicator" id="typingIndicator" style="display:none;">
                <div class="dots"><span></span><span></span><span></span></div>
                <div class="label" id="typingLabel"></div>
            </div>
        </div>

        <!-- Input -->
        <div class="chat-input">
            <input type="text" id="messageInput" placeholder="Type a message..." autocomplete="off" autofocus>
            <button id="sendButton"><i class="fas fa-paper-plane"></i> Send</button>
        </div>

    </div>



    @include('layouts.partials.pwa-banner')

@include('session.referral-prompt')
</body>
</html>
