@extends('layouts.helper')

@section('title', 'Live Chat')

@section('heading', 'Live Chat')
@section('subheading', 'Conversation with ' . ($seekerName ?? 'Seeker'))

@section('styles')
    @vite(['resources/js/chat.js'])
    <style>
        .room-card { padding: 0; overflow: hidden; display: flex; flex-direction: column; }

        /* ─── Room header ─── */
        .room-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 20px;
            border-bottom: 1px solid var(--gray-200);
            background: #ffffff;
            flex-shrink: 0;
        }
        .room-header .back-link {
            color: var(--gray-500);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: color 0.2s;
            flex-shrink: 0;
        }
        .room-header .back-link:hover { color: var(--gray-800); }
        .room-header .peer-info { display: flex; align-items: center; gap: 12px; min-width: 0; flex: 1; }
        .room-header .peer-info .avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #38C172, #038A45);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 17px;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .room-header .peer-info .meta { min-width: 0; }
        .room-header .peer-info .name {
            font-weight: 600;
            font-size: 15px;
            color: var(--gray-800);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .room-header .peer-info .status {
            font-size: 12px;
            color: #059669;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .room-header .peer-info .status .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            display: inline-block;
            animation: pulse-dot 2s infinite;
        }
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .room-header .session-badges { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
        .room-header .session-timer { font-size: 12px; color: #9ca3af; font-variant-numeric: tabular-nums; }
        .room-header .session-badges .end-btn {
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
            text-decoration: none;
        }
        .room-header .session-badges .end-btn:hover { background: #fef2f2; }
        .room-header .session-badges .action-btn {
            font-size: 13px;
            font-weight: 600;
            background: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            transition: all 0.2s;
            text-decoration: none;
            border: 1px solid;
        }
        .room-header .session-badges .action-btn.emergency-btn {
            color: #b45309;
            border-color: #fcd34d;
        }
        .room-header .session-badges .action-btn.emergency-btn:hover { background: #fffbeb; }
        .room-header .session-badges .action-btn.referral-btn {
            color: #1d4ed8;
            border-color: #bfdbfe;
        }
        .room-header .session-badges .action-btn.referral-btn:hover { background: #eff6ff; }

        /* ─── Action modals ─── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            z-index: 300;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: #ffffff;
            border-radius: 20px;
            padding: 28px 26px;
            width: 100%;
            max-width: 460px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            animation: modalIn 0.25s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(14px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-box h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .modal-box .modal-sub { font-size: 13px; color: var(--gray-500); margin-bottom: 18px; }
        .modal-box .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 18px; }
        .modal-box .modal-actions .btn { border: none; cursor: pointer; border-radius: 12px; font-size: 14px; font-weight: 600; padding: 10px 18px; transition: all 0.2s; }
        .modal-box .modal-actions .btn-cancel { background: var(--gray-100); color: var(--gray-600); }
        .modal-box .modal-actions .btn-cancel:hover { background: var(--gray-200); }
        .modal-box .modal-actions .btn-danger { background: #dc2626; color: white; }
        .modal-box .modal-actions .btn-danger:hover { background: #b91c1c; }
        .modal-box .modal-actions .btn-info { background: #2563eb; color: white; }
        .modal-box .modal-actions .btn-info:hover { background: #1d4ed8; }
        .modal-box .form-label { font-size: 13px; }
        .modal-box textarea,
        .modal-box select {
            width: 100%;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 14px;
            outline: none;
            transition: border 0.2s;
            resize: vertical;
        }
        .modal-box textarea:focus,
        .modal-box select:focus { border-color: var(--green-500); }
        .modal-box .checkbox-row { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--gray-600); margin-top: 10px; cursor: pointer; }

        /* ─── Messages ─── */
        .chat-window { height: calc(100vh - 330px); min-height: 380px; }

        .empty-chat {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .chat-messages .message {
            max-width: 75%;
            padding: 10px 14px;
            border-radius: 16px;
            word-wrap: break-word;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .chat-messages .message.helper {
            background: var(--green-500);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }
        .chat-messages .message.seeker {
            background: #ffffff;
            border: 1px solid var(--gray-200);
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
        .chat-messages .message.helper .sender-name { color: rgba(255, 255, 255, 0.85); }
        .chat-messages .message.seeker .sender-name { color: var(--gray-500); }
        .chat-messages .message .text { font-size: 14px; line-height: 1.5; word-break: break-word; }
        .chat-messages .message .time {
            font-size: 10px;
            opacity: 0.65;
            display: block;
            text-align: right;
            margin-top: 4px;
        }
        .chat-messages .message.helper .time { color: rgba(255, 255, 255, 0.75); }
        .chat-messages .message.seeker .time { color: #9ca3af; }

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
            border: 1px solid var(--gray-200);
            padding: 3px 14px;
            border-radius: 20px;
        }

        /* ─── Typing indicator ─── */
        .typing-indicator {
            align-self: flex-start;
            background: #ffffff;
            border: 1px solid var(--gray-200);
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
        .typing-indicator .label { font-size: 12px; color: var(--gray-500); }

        @media (max-width: 768px) {
            .chat-window { height: calc(100vh - 260px); min-height: 320px; }
            .chat-messages .message { max-width: 85%; padding: 8px 12px; }
            .chat-messages .message .text { font-size: 13px; }
            .room-header { padding: 12px 14px; }
            .room-header .peer-info .avatar { width: 34px; height: 34px; font-size: 15px; }
            .room-header .peer-info .name { font-size: 14px; }
        }
        @media (max-width: 480px) {
            .chat-messages .message { max-width: 90%; }
        }
    </style>
@endsection

@section('content')

    <input type="hidden" id="sessionId" value="{{ $session->id }}">
    <input type="hidden" id="currentUserId" value="{{ auth()->id() }}">
    <input type="hidden" id="currentUserRole" value="{{ auth()->user()->role }}">
    <input type="hidden" id="peerName" value="{{ $seekerName }}">

    <div class="card room-card">

        <!-- Room header -->
        <div class="room-header">
            <a href="{{ route('helper.chat') }}" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Chats
            </a>
            <div class="peer-info">
                <div class="avatar">{{ \Illuminate\Support\Str::substr($seekerName ?? 'S', 0, 1) }}</div>
                <div class="meta">
                    <div class="name">{{ $seekerName ?? 'Seeker' }}</div>
                    <div class="status"><span class="dot"></span> Online</div>
                </div>
            </div>
            <div class="session-badges">
                @if($session->session_status !== 'completed')
                    <span class="session-timer" id="sessionTimer">00:00</span>
                @endif
                <span class="status-badge {{ $session->session_status === 'active' ? 'active' : 'helper-assigned' }}">{{ $session->status_label }}</span>
                @if($session->session_status !== 'completed')
                    <button type="button" class="action-btn referral-btn" id="referralBtn"><i class="fas fa-arrow-right"></i> Referral</button>
                    <button type="button" class="action-btn emergency-btn" id="emergencyBtn"><i class="fas fa-exclamation-triangle"></i> Emergency</button>
                    <form method="POST" action="{{ route('helper.session.end', ['id' => $session->id]) }}" onsubmit="return confirm('End this session?');">
                        @csrf
                        <button type="submit" class="end-btn"><i class="fas fa-stop-circle"></i> End</button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Messages -->
        <div class="chat-window">
            <div class="chat-messages" id="chatMessages">
                <div class="empty-chat chat-empty" id="emptyChat">
                    <i class="fas fa-comments"></i>
                    <h3 style="font-size:16px;font-weight:700;color:var(--gray-800);margin-bottom:6px;">No messages yet</h3>
                    <p>Say hello to {{ $seekerName ?? 'the seeker' }} to get started.</p>
                </div>

                <!-- Typing indicator (shown via JavaScript) -->
                <div class="typing-indicator" id="typingIndicator" style="display:none;">
                    <div class="dots"><span></span><span></span><span></span></div>
                    <div class="label" id="typingLabel"></div>
                </div>
            </div>

            <!-- Input -->
            <div class="chat-input-row">
                <input type="text" id="messageInput" class="form-control" placeholder="Type a message..." autocomplete="off">
                <button id="sendButton" class="btn btn-primary" style="padding:10px 20px;"><i class="fas fa-paper-plane"></i> Send</button>
            </div>
        </div>

    </div>

    <!-- ═══════════════ REFERRAL MODAL ═══════════════ -->
    <div class="modal-overlay" id="referralModal">
        <div class="modal-box">
            <h3><i class="fas fa-arrow-right" style="color:#2563eb;"></i> Recommend a Referral</h3>
            <p class="modal-sub">Recommend professional support for this seeker. An adviser will review the referral.</p>
            <form method="POST" action="{{ route('helper.session.referral', ['id' => $session->id]) }}">
                @csrf
                <label class="form-label" for="referralReason">Reason for referral</label>
                <textarea id="referralReason" name="referral_reason" rows="4" required placeholder="Describe why a professional referral is recommended..."></textarea>
                <label class="form-label" style="margin-top:14px;" for="referralPriority">Priority level</label>
                <select id="referralPriority" name="priority_level" required>
                    <option value="low">Low</option>
                    <option value="moderate">Moderate</option>
                    <option value="high">High</option>
                    <option value="emergency">Emergency</option>
                </select>
                <label class="checkbox-row">
                    <input type="checkbox" name="help_seeker_consent" value="1"> The seeker has consented to this referral
                </label>
                <label class="checkbox-row">
                    <input type="checkbox" name="identity_disclosed" value="1"> The seeker's identity has been disclosed
                </label>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel modal-close" data-modal="referralModal">Cancel</button>
                    <button type="submit" class="btn btn-info"><i class="fas fa-paper-plane" style="margin-right:6px;"></i> Submit Referral</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══════════════ EMERGENCY MODAL ═══════════════ -->
    <div class="modal-overlay" id="emergencyModal">
        <div class="modal-box">
            <h3><i class="fas fa-exclamation-triangle" style="color:#dc2626;"></i> Flag as Emergency</h3>
            <p class="modal-sub">This immediately notifies a support coordinator. Use only when the seeker is at immediate risk.</p>
            <form method="POST" action="{{ route('helper.session.emergency', ['id' => $session->id]) }}">
                @csrf
                <label class="form-label" for="emergencyDesc">What is happening?</label>
                <textarea id="emergencyDesc" name="description" rows="4" required placeholder="Describe the situation and any immediate risk..."></textarea>
                <label class="form-label" style="margin-top:14px;" for="emergencyAction">Immediate action taken (optional)</label>
                <textarea id="emergencyAction" name="immediate_action" rows="2" placeholder="e.g. kept the seeker talking, checked their location, called support..."></textarea>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel modal-close" data-modal="emergencyModal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-exclamation-triangle" style="margin-right:6px;"></i> Flag Emergency</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // ── Session timer ──
            const timerDisplay = document.getElementById('sessionTimer');
            const startedAt = {{ $session->start_time?->timestamp ?? 'null' }};
            if (timerDisplay) {
                const tick = function () {
                    const seconds = startedAt
                        ? Math.max(0, Math.floor(Date.now() / 1000) - startedAt)
                        : 0;
                    const mins = String(Math.floor(seconds / 60)).padStart(2, '0');
                    const secs = String(seconds % 60).padStart(2, '0');
                    timerDisplay.textContent = mins + ':' + secs;
                };
                tick();
                setInterval(tick, 1000);
            }

            const openers = {
                emergencyBtn: 'emergencyModal',
                referralBtn: 'referralModal',
            };

            Object.entries(openers).forEach(([btnId, modalId]) => {
                const btn = document.getElementById(btnId);
                const modal = document.getElementById(modalId);
                if (!btn || !modal) return;
                btn.addEventListener('click', () => modal.classList.add('open'));
            });

            document.querySelectorAll('.modal-close').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById(btn.dataset.modal).classList.remove('open');
                });
            });

            document.querySelectorAll('.modal-overlay').forEach(overlay => {
                overlay.addEventListener('click', e => {
                    if (e.target === overlay) overlay.classList.remove('open');
                });
            });
        });
    </script>

@endsection