class ChatApp {
    constructor(sessionId, currentUserId, currentUserRole) {
        this.sessionId = sessionId;
        this.currentUserId = currentUserId;
        this.currentUserRole = currentUserRole;
        this.messageContainer = document.getElementById('chatMessages');
        this.messageInput = document.getElementById('messageInput');
        this.sendButton = document.getElementById('sendButton');
        this.renderedIds = new Set();

        this.initEcho();
        this.loadMessages();
        this.bindEvents();
    }

    initEcho() {
        if (!window.Echo) {
            this.enablePollingFallback(6000);
            return;
        }

        window.Echo.private(`session.${this.sessionId}`)
            .listen('MessageSent', (event) => {
                this.appendMessage(event);
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

    loadMessages(initial = true) {
        fetch(`/api/chat/messages/${this.sessionId}`, {
            headers: { 'Accept': 'application/json' }
        })
            .then((response) => response.json())
            .then((data) => {
                if (!data.success) return;
                data.messages.forEach((msg) => this.renderMessage(msg));
                if (initial || this.renderedIds.size > 0) {
                    this.scrollToBottom();
                }
            })
            .catch(() => { /* transient network errors are fine */ });
    }

    sendMessage() {
        const message = this.messageInput.value.trim();
        if (!message) return;

        this.setInputDisabled(true);

        fetch('/api/chat/send', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
            },
            body: JSON.stringify({
                session_id: this.sessionId,
                message: message
            })
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    this.messageInput.value = '';
                    // Render locally so the sender gets instant feedback, even if the
                    // websocket is momentarily unavailable. Echo will re-append the
                    // same message with the same id, which the dedup guard skips.
                    this.renderMessage(data.message);
                } else {
                    alert(data.error || 'Could not send the message. Please try again.');
                }
            })
            .catch(() => alert('Network error — could not send the message.'))
            .finally(() => {
                this.setInputDisabled(false);
                this.messageInput.focus();
            });
    }

    appendMessage(event) {
        this.renderMessage({
            id: event.id,
            message: event.message,
            sender_id: event.sender_id,
            sender_name: event.sender_name,
            sender_role: event.sender_role,
            sent_datetime: event.sent_datetime
        });
    }

    renderMessage(msg) {
        if (!msg || msg.id == null || this.renderedIds.has(msg.id)) return;
        this.renderedIds.add(msg.id);

        const isCurrentUser = String(msg.sender_id) === String(this.currentUserId)
            || (msg.sender_id == null && msg.sender_role === this.currentUserRole);
        const messageClass = isCurrentUser ? 'seeker' : 'helper';
        const senderName = isCurrentUser
            ? 'You'
            : (msg.sender_name || (msg.sender_role === 'helper' ? 'Peer Helper' : 'Seeker'));

        const empty = this.messageContainer.querySelector('.empty-state, .chat-empty');
        if (empty) empty.remove();

        const div = document.createElement('div');
        div.className = `message ${messageClass}`;
        div.dataset.id = msg.id;
        div.innerHTML = `
            <strong class="sender-name">${this.escapeHtml(senderName)}</strong>
            <p>${this.escapeHtml(msg.message)}</p>
            <span class="time">${this.escapeHtml(msg.sent_datetime || '')}</span>
        `;
        this.messageContainer.appendChild(div);
        this.scrollToBottom();
    }

    setInputDisabled(disabled) {
        this.messageInput.disabled = disabled;
        this.sendButton.disabled = disabled;
    }

    scrollToBottom() {
        this.messageContainer.scrollTop = this.messageContainer.scrollHeight;
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    bindEvents() {
        this.sendButton.addEventListener('click', () => this.sendMessage());
        this.messageInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const sessionId = document.getElementById('sessionId')?.value;
    const currentUserId = document.getElementById('currentUserId')?.value;
    const currentUserRole = document.getElementById('currentUserRole')?.value;

    if (sessionId && currentUserId) {
        window.chatApp = new ChatApp(sessionId, currentUserId, currentUserRole);
    }
});
