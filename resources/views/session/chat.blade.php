<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>COMPASS – Chat Session</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        * { font-family: 'Inter', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --green-50: #EAF8F0;
            --green-500: #04A052;
            --green-600: #038A45;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #163B2D;
        }

        body { background: #F8FBF9; font-family: 'Inter', sans-serif; }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 260px;
            height: 100vh;
            background: rgba(255,255,255,0.92);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(4,160,82,0.06);
            box-shadow: 4px 0 40px rgba(0,0,0,0.02);
            z-index: 100;
            transition: transform 0.35s cubic-bezier(0.4,0,0.2,1);
            display: flex;
            flex-direction: column;
            padding: 24px 16px 20px;
        }
        .sidebar.closed { transform: translateX(-100%); }

        .sidebar .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(4,160,82,0.06);
            margin-bottom: 20px;
        }
        .sidebar .logo .icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: linear-gradient(135deg, #38C172, #038A45);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 20px;
            box-shadow: 0 4px 16px rgba(4,160,82,0.2);
        }
        .sidebar .logo span {
            font-weight: 700;
            font-size: 20px;
            color: var(--green-700);
        }

        .sidebar .nav { flex: 1; overflow-y: auto; }
        .sidebar .nav .nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 14px;
            border-radius: 12px;
            color: var(--gray-500);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            text-decoration: none;
            margin-bottom: 2px;
        }
        .sidebar .nav .nav-item i { width: 20px; text-align: center; font-size: 16px; color: var(--gray-400); }
        .sidebar .nav .nav-item:hover { background: var(--green-50); color: var(--gray-800); }
        .sidebar .nav .nav-item.active {
            background: var(--green-50);
            color: var(--green-700);
            font-weight: 600;
        }
        .sidebar .nav .nav-item.active i { color: var(--green-500); }

        .sidebar .user-section {
            border-top: 1px solid rgba(4,160,82,0.06);
            padding-top: 16px;
            margin-top: auto;
        }
        .sidebar .user-section .user-card {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar .user-section .user-card .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #38C172, #038A45);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
            flex-shrink: 0;
        }
        .sidebar .user-section .user-card .info .name {
            font-weight: 600;
            font-size: 14px;
            color: var(--gray-800);
        }
        .sidebar .user-section .user-card .info .role {
            font-size: 12px;
            color: var(--gray-400);
        }
        .sidebar .user-section .logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 12px;
            padding: 8px 12px;
            border-radius: 10px;
            color: var(--gray-500);
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
        }
        .sidebar .user-section .logout-btn:hover {
            background: #FEE2E2;
            color: #DC2626;
        }

        .main-content {
            margin-left: 260px;
            padding: 24px 40px 80px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
            border-radius: 24px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.01);
        }

        .chat-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px 24px;
            background: var(--gray-50);
        }

        .message {
            max-width: 75%;
            padding: 12px 16px;
            border-radius: 16px;
            margin-bottom: 8px;
            word-wrap: break-word;
        }

        .message.helper {
            background: white;
            border: 1px solid var(--gray-200);
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }

        .message.seeker {
            background: var(--green-500);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }

        .message .time {
            font-size: 10px;
            opacity: 0.6;
            margin-top: 4px;
            display: block;
        }

        .message.seeker .time { color: rgba(255,255,255,0.7); }
        .message.helper .time { color: var(--gray-400); }

        .chat-input {
            padding: 16px 24px;
            border-top: 1px solid var(--gray-200);
            background: white;
            display: flex;
            gap: 12px;
        }

        .chat-input input {
            flex: 1;
            padding: 12px 16px;
            border-radius: 14px;
            border: 1.5px solid var(--gray-200);
            outline: none;
            transition: all 0.2s ease;
            font-size: 14px;
        }
        .chat-input input:focus {
            border-color: var(--green-500);
            box-shadow: 0 0 0 3px rgba(4,160,82,0.08);
        }

        .chat-input button {
            padding: 12px 24px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: white;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .chat-input button:hover {
            transform: scale(1.02);
            box-shadow: 0 4px 20px rgba(4,160,82,0.2);
        }

        .hamburger {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--gray-700);
            cursor: pointer;
            padding: 4px;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.25);
            z-index: 99;
        }
        .sidebar-overlay.active { display: block; }

        @media (min-width: 769px) {
            .sidebar-overlay { display: none !important; }
        }
        @media (max-width: 1024px) {
            .main-content { padding: 20px 24px 0; }
            .chat-messages { padding: 16px; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 280px; padding: 16px; }
            .main-content { margin-left: 0; padding: 0 0 100px; height: 100vh; }
            .hamburger { display: block; }
            .chat-container { border-radius: 0; border: none; }
            .chat-header { padding: 12px 16px; }
            .chat-messages { padding: 12px 16px; }
            .chat-input { padding: 12px 16px; }
            .message { max-width: 85%; }
        }
        @media (max-width: 480px) {
            .chat-input input { font-size: 13px; padding: 10px 14px; }
            .chat-input button { padding: 10px 16px; font-size: 13px; }
        }

        .bottom-nav {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(255,255,255,0.94);
            backdrop-filter: blur(16px);
            border-top: 1px solid var(--gray-200);
            padding: 6px 0 env(safe-area-inset-bottom, 6px);
            z-index: 200;
            justify-content: space-around;
        }
        .bottom-nav .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0px;
            color: var(--gray-400);
            text-decoration: none;
            font-size: 10px;
            font-weight: 500;
            padding: 4px 12px;
            transition: all 0.2s ease;
        }
        .bottom-nav .nav-item i { font-size: 20px; }
        .bottom-nav .nav-item.active { color: var(--green-500); }

        @media (max-width: 768px) {
            .bottom-nav { display: flex; }
        }
    </style>
</head>
<body>

    @include('partials.sidebar', [
        'active' => ['session.chat'],
        'role'   => 'Help Seeker',
    ])

    <!-- ══════════════════════════════════════════════ -->
    <!-- MAIN CONTENT                                 -->
    <!-- ══════════════════════════════════════════════ -->

    <main class="main-content">

        <!-- Top Bar (Mobile) -->
        <div class="flex items-center justify-between py-3 px-4 bg-white border-b border-gray-200 md:hidden">
            <div class="flex items-center gap-3">
                <button class="hamburger" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white font-bold text-sm">
                        {{ substr($helperName, 0, 1) }}
                    </div>
                    <div>
                        <p class="font-semibold text-sm text-gray-800">{{ $helperName }}</p>
                        <p class="text-xs text-green-600">🟢 Online</p>
                    </div>
                </div>
            </div>
            <form method="POST" action="{{ route('session.end') }}">
                @csrf
                <button type="submit" class="text-red-500 text-sm font-medium hover:text-red-700 transition">
                    <i class="fas fa-phone-slash mr-1"></i> End
                </button>
            </form>
        </div>

        <!-- Chat Container -->
        <div class="chat-container">

            <!-- Header -->
            <div class="chat-header hidden md:flex">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center text-white font-bold text-lg">
                        {{ substr($helperName, 0, 1) }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800">{{ $helperName }}</p>
                        <p class="text-xs text-green-600">🟢 Online · Peer Helper</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-400" id="sessionTimer">00:00</span>
                    <form method="POST" action="{{ route('session.end') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-red-500 text-sm font-medium hover:text-red-700 transition">
                            <i class="fas fa-phone-slash mr-1"></i> End Session
                        </button>
                    </form>
                </div>
            </div>

            <!-- Messages -->
            <div class="chat-messages" id="chatMessages">
                @forelse($messages as $msg)
                    <div class="message {{ $msg->sender }}">
                        {{ $msg->message_text }}
                        <span class="time">{{ $msg->time_formatted }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-400 text-center py-8">No messages yet. Say hello to your helper to get started.</p>
                @endforelse
            </div>

            <!-- Input -->
            <div class="chat-input">
                <input type="text" id="messageInput" placeholder="Type a message..." onkeydown="if(event.key==='Enter') sendMessage()">
                <button onclick="sendMessage()">
                    <i class="fas fa-paper-plane mr-2"></i> Send
                </button>
            </div>

        </div>

    </main>

    <!-- ══════════════════════════════════════════════ -->
    <!-- JAVASCRIPT                                   -->
    <!-- ══════════════════════════════════════════════ -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const messagesContainer = document.getElementById('chatMessages');
            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            // ── Scroll to bottom of messages ──
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            // ── Session Timer ──
            let seconds = 0;
            const timerDisplay = document.getElementById('sessionTimer');
            const timerInterval = setInterval(function() {
                seconds++;
                const mins = String(Math.floor(seconds / 60)).padStart(2, '0');
                const secs = String(seconds % 60).padStart(2, '0');
                if (timerDisplay) timerDisplay.textContent = mins + ':' + secs;
            }, 1000);

            function appendMessage(sender, text, time) {
                const msgDiv = document.createElement('div');
                msgDiv.className = 'message ' + sender;
                msgDiv.innerHTML = '<span class="text"></span><span class="time"></span>';
                msgDiv.querySelector('.text').textContent = text;
                msgDiv.querySelector('.time').textContent = time;
                messagesContainer.appendChild(msgDiv);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }

            // ── Send Message (persisted to the database) ──
            window.sendMessage = async function() {
                const input = document.getElementById('messageInput');
                const message = input.value.trim();
                if (!message) return;

                try {
                    const response = await fetch('{{ route('session.chat.send') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ message: message })
                    });

                    if (!response.ok) {
                        const data = await response.json();
                        alert(data.error || 'Could not send the message. Please try again.');
                        return;
                    }

                    const data = await response.json();
                    input.value = '';
                    appendMessage('seeker', data.message, data.time);
                } catch (err) {
                    alert('Network error — could not send the message.');
                }
            };

            // ── Poll for new helper messages every 5 seconds ──
            async function pollMessages() {
                try {
                    const response = await fetch(window.location.href, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (!response.ok) return;
                    const html = await response.text();

                    // Count helper messages currently rendered vs. fresh page
                    const currentCount = messagesContainer.querySelectorAll('.message.helper').length;
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const fresh = doc.getElementById('chatMessages');
                    if (!fresh) return;
                    const helperCount = fresh.querySelectorAll('.message.helper').length;

                    if (helperCount > currentCount) {
                        // Simplest reliable refresh — full reload with the new rows
                        window.location.reload();
                    }
                } catch (err) {
                    // ignore transient network errors
                }
            }

            setInterval(pollMessages, 5000);

        });
    </script>

</body>
</html>