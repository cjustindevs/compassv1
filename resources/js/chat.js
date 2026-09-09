/**
 * Consolidated chat logic for both the Seeker and Helper chat rooms.
 *
 * Expects these hidden inputs on the page:
 *   #sessionId        – the counseling session id
 *   #currentUserId    – auth()->id()
 *   #currentUserRole  – auth()->user()->role ('seeker' | 'helper')
 *   #peerName         – display name of the other party
 *
 * Renders message bubbles into #chatMessages, listens on the private
 * `session.{id}` Echo channel, shows a typing indicator via whispers,
 * and falls back to polling when the websocket is unavailable.
 */
class ChatApp {
    constructor(sessionId, currentUserId, currentUserRole, peerName) {
        this.sessionId = sessionId;
        this.currentUserId = String(currentUserId);
        this.currentUserRole = currentUserRole;
        this.peerName = peerName || 'Peer';

        this.messagesContainer = document.getElementById('chatMessages');
        this.messageInput = document.getElementById('messageInput');
        this.sendButton = document.getElementById('sendButton');
        this.emptyState = document.getElementById('emptyChat');
        this.typingIndicator = document.getElementById('typingIndicator');
        this.typingLabel = document.getElementById('typingLabel');

        this.renderedIds = new Set();
        this.lastRenderedDay = null;
        this.typingTimeout = null;
        this.channel = null;
        this.ended = false;
        this.deadlineReached = false;
        this.statusPending = false;
        this.remainingSeconds = null;
        this.statusReceivedAt = null;

        this.initEcho();
        this.loadMessages();
        this.bindEvents();
        this.checkSessionStatus();
        this.statusInterval = setInterval(() => this.checkSessionStatus(), 5000);
        this.timerInterval = setInterval(() => this.updateSessionTimer(), 1000);
    }

    async checkSessionStatus() {
        if (this.ended || this.statusPending) return;
        this.statusPending = true;
        try {
            const response = await fetch(`/api/chat/status/${this.sessionId}`, {
                headers: { 'Accept': 'application/json' }, cache: 'no-store',
            });
            if (response.ok) this.applySessionState((await response.json()).session);
        } catch (_) { /* Keep the composer locked at the deadline and retry. */ }
        finally { this.statusPending = false; }
    }

    applySessionState(state) {
        if (!state || this.ended) return;
        if (state.ended) {
            this.handleSessionEnded(state);
            return;
        }
        this.remainingSeconds = state.remaining_seconds;
        this.statusReceivedAt = performance.now();
        this.updateSessionTimer();
    }

    updateSessionTimer() {
        if (this.ended) return;
        const timer = document.getElementById('sessionTimer');
        if (this.remainingSeconds == null) {
            if (timer) timer.textContent = 'Waiting to start';
            return;
        }
        const remaining = Math.max(0, Math.ceil(this.remainingSeconds - (performance.now() - this.statusReceivedAt) / 1000));
        if (timer) timer.textContent = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')} remaining`;
        if (remaining === 0) {
            this.deadlineReached = true;
            this.setInputDisabled(true);
            this.checkSessionStatus();
        }
    }

    // ─────────────────────────── Websocket ───────────────────────────

    initEcho() {
        if (!window.Echo) {
            this.enablePollingFallback(6000);
            return;
        }

        // The backend broadcasts MessageSent on a PRIVATE channel.
        this.channel = window.Echo.private(`session.${this.sessionId}`);

        this.channel
            .listen('MessageSent', (event) => {
                this.appendMessage(event);
            })
            .listenForWhisper('typing', (event) => {
                this.showTyping(!!event.typing);
            })
            .listen('SessionEnded', (event) => {
                this.handleSessionEnded(event);
            })
            .listen('SessionUpdated', (event) => {
                if (window.showToast) {
                    window.showToast(event.message || 'Session updated', 'info');
                }
            });

        // Safety net: if the websocket ever drops, keep the conversation flowing.
        this.enablePollingFallback(15000);
    }

    enablePollingFallback(interval) {
        const state = () => window.Echo?.connector?.pusher?.connection?.state;
        setInterval(() => {
            if (state() !== 'connected') {
                this.loadMessages(false);
            }
        }, interval);
    }

    // ─────────────────────────── Messages ───────────────────────────

    loadMessages(initial = true) {
        fetch(`/api/chat/messages/${this.sessionId}`, {
            headers: { 'Accept': 'application/json' },
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) return;
                this.applySessionState(data.session);
                this.removeEmptyState();
                data.messages.forEach((msg) => this.renderMessage(msg));
                if (initial || this.renderedIds.size > 0) {
                    this.scrollToBottom();
                }
            })
            .catch(() => { /* transient network errors are fine */ });
    }

    appendMessage(event) {
        this.removeEmptyState();
        this.renderMessage({
            id: event.id,
            message: event.message,
            sender_id: event.sender_id,
            sender_role: event.sender_role,
            sender_name: event.sender_name,
            sent_datetime: event.sent_datetime,
            sent_datetime_iso: event.sent_datetime_iso,
        });
    }

    renderMessage(msg) {
        if (!msg || msg.id == null || this.renderedIds.has(msg.id)) return;
        this.renderedIds.add(msg.id);

        const isCurrentUser = this.isCurrentUser(msg);
        const role = this.roleFor(msg, isCurrentUser);
        const senderName = isCurrentUser
            ? 'You'
            : (msg.sender_name || (role === 'helper' ? 'Peer Helper' : 'Seeker'));

        // Day divider (only when the backend provides a full timestamp)
        if (msg.sent_datetime_iso) {
            const day = new Date(msg.sent_datetime_iso).toDateString();
            if (this.lastRenderedDay !== day) {
                this.lastRenderedDay = day;
                this.messagesContainer.appendChild(this.buildDayDivider(msg.sent_datetime_iso));
            }
        }

        const div = document.createElement('div');
        div.className = `message ${role}`;
        div.dataset.id = msg.id;
        div.innerHTML = `
            <strong class="sender-name">${this.escapeHtml(senderName)}</strong>
            <div class="text">${this.escapeHtml(msg.message)}</div>
            <span class="time">${this.formatTime(msg)}</span>
        `;
        this.messagesContainer.appendChild(div);

        if (this.isNearBottom()) {
            this.scrollToBottom();
        }
    }

    buildDayDivider(iso) {
        const divider = document.createElement('div');
        divider.className = 'date-divider';
        divider.innerHTML = `<span>${this.escapeHtml(this.dayLabel(iso))}</span>`;
        return divider;
    }

    sendMessage() {
        if (this.ended || this.deadlineReached) return;
        const message = this.messageInput.value.trim();
        if (!message) return;

        this.setInputDisabled(true);

        fetch('/api/chat/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
            body: JSON.stringify({ session_id: this.sessionId, message }),
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    this.messageInput.value = '';
                    // Render locally so the sender gets instant feedback, even if
                    // the websocket is momentarily unavailable. Echo will re-append
                    // the same message with the same id, which the dedup guard skips.
                    this.renderMessage(data.message);
                } else {
                    this.checkSessionStatus();
                    alert(data.error || 'Could not send the message. Please try again.');
                }
            })
            .catch(() => alert('Network error — could not send the message.'))
            .finally(() => {
                this.setInputDisabled(false);
                this.messageInput.focus();
            });
    }

    // ─────────────────────────── Session ended ───────────────────────────

    handleSessionEnded(event) {
        if (this.ended) return;
        this.ended = true;
        clearInterval(this.statusInterval);
        clearInterval(this.timerInterval);
        this.setInputDisabled(true);
        this.showTyping(false);

        const endedBy = event.ended_by || 'the other party';
        const message = event.message || `The session has been ended by ${endedBy}.`;

        this.showEndedOverlay(message);

        // Give the user a moment to read the notice, then move them along:
        // seeker → evaluation page, helper → session notes.
        const url = this.safePath(this.currentUserRole === 'helper'
            ? (event.helper_redirect || `/helper/session/${this.sessionId}/notes`)
            : (event.seeker_redirect || '/session/evaluation'));

        setTimeout(() => {
            window.location.href = url;
        }, 3000);
    }

    showEndedOverlay(message) {
        const existing = document.getElementById('sessionEndedOverlay');
        if (existing) existing.remove();

        const overlay = document.createElement('div');
        overlay.id = 'sessionEndedOverlay';
        overlay.style.cssText = [
            'position:fixed',
            'inset:0',
            'z-index:9999',
            'background:rgba(22,59,45,0.55)',
            'backdrop-filter:blur(6px)',
            'display:flex',
            'align-items:center',
            'justify-content:center',
            'padding:20px',
        ].join(';');

        overlay.innerHTML = `
            <div style="background:#ffffff;border-radius:20px;padding:32px 28px;max-width:420px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.25);">
                <div style="width:64px;height:64px;border-radius:50%;background:#FEF2F2;display:flex;align-items:center;justify-content:center;font-size:26px;margin:0 auto 16px;"><i class="fas fa-hand" aria-hidden="true"></i></div>
                <h3 style="margin:0 0 8px;font-size:18px;font-weight:800;color:#163B2D;">Session Ended</h3>
                <p style="margin:0 0 20px;font-size:14px;color:#6B7280;line-height:1.6;">${this.escapeHtml(message)}</p>
                <p style="margin:0;font-size:13px;color:#9CA3AF;">Redirecting you…</p>
            </div>
        `;

        document.body.appendChild(overlay);
    }

    // ─────────────────────────── Typing indicator ───────────────────────────

    emitTyping() {
        if (!this.channel) return;

        this.channel.whisper('typing', { typing: true });

        clearTimeout(this.typingTimeout);
        this.typingTimeout = setTimeout(() => {
            this.channel.whisper('typing', { typing: false });
        }, 1500);
    }

    showTyping(visible) {
        if (!this.typingIndicator) return;

        if (visible) {
            if (this.typingLabel) {
                this.typingLabel.textContent = `${this.peerName} is typing`;
            }
            // Keep the indicator at the bottom, below the newest messages.
            this.messagesContainer.appendChild(this.typingIndicator);
            this.typingIndicator.style.display = 'flex';
            this.scrollToBottom();
        } else {
            this.typingIndicator.style.display = 'none';
        }
    }

    // ─────────────────────────── Helpers ───────────────────────────

    isCurrentUser(msg) {
        return String(msg.sender_id) === this.currentUserId
            || (msg.sender_id == null && msg.sender_role === this.currentUserRole);
    }

    roleFor(msg, isCurrentUser) {
        if (isCurrentUser) return this.currentUserRole;
        if (msg.sender_role === 'seeker' || msg.sender_role === 'helper') return msg.sender_role;
        return this.currentUserRole === 'helper' ? 'seeker' : 'helper';
    }

    removeEmptyState() {
        if (this.emptyState) {
            this.emptyState.remove();
            this.emptyState = null;
        }
    }

    setInputDisabled(disabled) {
        this.messageInput.disabled = disabled || this.ended || this.deadlineReached;
        this.sendButton.disabled = disabled || this.ended || this.deadlineReached;
    }

    isNearBottom() {
        return this.messagesContainer.scrollHeight
            - this.messagesContainer.scrollTop
            - this.messagesContainer.clientHeight < 120;
    }

    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }

    formatTime(msg) {
        if (msg.sent_datetime_iso) {
            try {
                return new Date(msg.sent_datetime_iso).toLocaleTimeString([], {
                    hour: 'numeric',
                    minute: '2-digit',
                });
            } catch (e) { /* fall through */ }
        }
        return msg.sent_datetime || msg.time || '';
    }

    dayLabel(iso) {
        const date = new Date(iso);
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);

        const same = (a, b) => a.toDateString() === b.toDateString();

        if (same(date, today)) return 'Today';
        if (same(date, yesterday)) return 'Yesterday';

        return date.toLocaleDateString([], {
            month: 'short',
            day: 'numeric',
            year: date.getFullYear() === today.getFullYear() ? undefined : 'numeric',
        });
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    safePath(url, fallback = '/') {
        if (typeof url !== 'string' || !url.startsWith('/') || url.startsWith('//')) {
            return fallback;
        }

        return url;
    }

    // ─────────────────────────── Events ───────────────────────────

    bindEvents() {
        this.sendButton.addEventListener('click', () => this.sendMessage());
        this.messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
        this.messageInput.addEventListener('input', () => this.emitTyping());
        this.messageInput.addEventListener('blur', () => {
            if (this.channel) {
                this.channel.whisper('typing', { typing: false });
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const sessionId = document.getElementById('sessionId')?.value;
    const currentUserId = document.getElementById('currentUserId')?.value;
    const currentUserRole = document.getElementById('currentUserRole')?.value;
    const peerName = document.getElementById('peerName')?.value;

    if (sessionId && currentUserId) {
        window.chatApp = new ChatApp(sessionId, currentUserId, currentUserRole, peerName);
    }
});
