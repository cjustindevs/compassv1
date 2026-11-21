// Live voice supervision, Adviser side: listen receive-only to a connected call. The Adviser's browser
// never asks for a microphone. Each participant (Seeker, Helper) offers a copy of their own audio; this
// page answers, through the relay server only, and plays both. Nothing is recorded.
(function () {
    class ListenSession {
        constructor(env, startUrl, hooks) {
            this.env = env;              // { fetch, RTCPeerConnection, csrf, setTimeout, clearTimeout }
            this.startUrl = startUrl;
            this.hooks = hooks || {};     // { onTrack(from, stream), onStatus(text, state), onEnd(text) }
            this.peers = new Map();      // 'seeker' | 'helper' -> { pc, pending: [] }
            this.cursor = 0; this.active = false; this.timer = null;
        }

        async post(url, body) {
            const res = await this.env.fetch(url, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.env.csrf }, body: JSON.stringify(body || {}) });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { const error = new Error(data.message || 'The request could not be completed.'); error.status = res.status; throw error; }
            return data;
        }

        async start(purpose, reason) {
            const data = await this.post(this.startUrl, { purpose, reason });
            this.listenId = data.listen_id; this.iceServers = data.iceServers; this.active = true;
            this.base = `${this.startUrl}/${this.listenId}`;
            this.status('Connecting to the call… The Seeker and the Helper will see that you are listening.', 'connecting');
            await this.poll();
            return data;
        }

        status(text, state) { this.hooks.onStatus?.(text, state); }

        async poll() {
            if (!this.active) return;
            try {
                const res = await this.env.fetch(`${this.base}?after=${this.cursor}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
                const data = await res.json();
                if (!data.active) { this.finish('The call ended, so listening stopped.'); return; }
                for (const signal of data.signals || []) await this.receive(signal);
                this.cursor = Math.max(this.cursor, data.cursor || 0);
            } catch { /* keep listening through a brief network drop */ }
            if (this.active) this.timer = this.env.setTimeout(() => this.poll(), 1500);
        }

        async receive(signal) {
            const from = signal.from === 'helper' ? 'helper' : 'seeker';
            if (signal.type === 'offer') return this.answer(from, signal.sdp);
            if (signal.type !== 'candidate') return;
            const peer = this.peers.get(from);
            if (!peer) return;
            if (!peer.pc.remoteDescription) { if (peer.pending.length < 30) peer.pending.push(signal.candidate); }
            else await peer.pc.addIceCandidate(signal.candidate);
        }

        async answer(from, sdp) {
            this.close(from);
            const pc = new this.env.RTCPeerConnection({ iceServers: this.iceServers, iceTransportPolicy: 'relay' });
            const peer = { pc, pending: [] };
            this.peers.set(from, peer);
            pc.ontrack = (event) => { if (event.track.kind === 'audio') this.hooks.onTrack?.(from, event.streams[0] || null, event.track); };
            pc.onicecandidate = (event) => {
                if (event.candidate && this.peers.get(from) === peer) this.post(`${this.base}/signal`, { type: 'candidate', to: from, candidate: event.candidate.toJSON() }).catch(() => {});
            };
            pc.onconnectionstatechange = () => {
                if (this.peers.get(from) !== peer) return;
                const connected = [...this.peers.values()].filter((p) => p.pc.connectionState === 'connected').length;
                if (connected) this.status(connected === 2 ? 'Listening to the Seeker and the Helper.' : 'Listening. Waiting for the other participant…', 'listening');
            };
            await pc.setRemoteDescription({ type: 'offer', sdp });
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            await this.post(`${this.base}/signal`, { type: 'answer', to: from, sdp: answer.sdp });
            for (const candidate of peer.pending.splice(0)) await pc.addIceCandidate(candidate);
        }

        close(from) {
            const peer = this.peers.get(from);
            if (!peer) return;
            this.peers.delete(from);
            peer.pc.ontrack = peer.pc.onicecandidate = peer.pc.onconnectionstatechange = null;
            try { peer.pc.close(); } catch { /* already closed */ }
        }

        finish(text) {
            this.active = false;
            this.env.clearTimeout(this.timer);
            for (const from of [...this.peers.keys()]) this.close(from);
            this.hooks.onEnd?.(text);
        }

        async stop() {
            if (!this.active) return;
            const base = this.base;
            this.finish('You stopped listening.');
            await this.post(`${base}/stop`).catch(() => {});
        }
    }

    if (typeof module !== 'undefined' && module.exports) module.exports = { ListenSession };
    if (typeof document === 'undefined') return;

    const start = () => {
        const root = document.querySelector('[data-voice-listen]');
        if (!root) return;
        const dialog = document.getElementById('voiceListenDialog');
        const form = dialog?.querySelector('form');
        const error = dialog?.querySelector('[data-listen-error]');
        const openButton = root.querySelector('[data-listen-open]');
        const stopButton = root.querySelector('[data-listen-stop]');
        const playButton = root.querySelector('[data-listen-play]');
        const statusText = root.querySelector('[data-listen-status]');
        const audioBox = root.querySelector('[data-listen-audio]');
        let session = null;
        const setStatus = (text, state) => { statusText.textContent = text; root.dataset.listenState = state; };
        const playAll = () => Promise.all([...audioBox.querySelectorAll('audio')].map((audio) => audio.play().catch(() => { playButton.hidden = false; })));

        openButton?.addEventListener('click', () => { if (error) error.textContent = ''; window.CompassDialogs?.open ? window.CompassDialogs.open(dialog) : dialog.showModal(); });
        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submit = form.querySelector('[type="submit"]');
            submit.disabled = true; error.textContent = '';
            session = new ListenSession({ fetch: window.fetch.bind(window), RTCPeerConnection: window.RTCPeerConnection, csrf: document.querySelector('meta[name="csrf-token"]').content,
                setTimeout: window.setTimeout.bind(window), clearTimeout: window.clearTimeout.bind(window) }, root.dataset.voiceListen, {
                onStatus: setStatus,
                onTrack: (from, stream, track) => {
                    let audio = audioBox.querySelector(`audio[data-from="${from}"]`);
                    if (!audio) { audio = document.createElement('audio'); audio.dataset.from = from; audio.autoplay = true; audio.setAttribute('playsinline', ''); audioBox.append(audio); }
                    audio.srcObject = stream || new MediaStream([track]);
                    playAll();
                },
                onEnd: (text) => {
                    setStatus(text, 'ended');
                    audioBox.querySelectorAll('audio').forEach((audio) => { audio.srcObject = null; audio.remove(); });
                    openButton.hidden = false; stopButton.hidden = true; playButton.hidden = true;
                },
            });
            try {
                await session.start(form.purpose.value, form.reason.value.trim());
                dialog.close(); form.reset();
                openButton.hidden = true; stopButton.hidden = false;
            } catch (err) {
                error.textContent = err.message; session = null;
            } finally { submit.disabled = false; }
        });
        stopButton?.addEventListener('click', () => session?.stop());
        playButton?.addEventListener('click', () => { playButton.hidden = true; playAll(); });
        window.addEventListener('pagehide', () => { if (session?.active) navigator.sendBeacon?.(`${session.base}/stop`, new Blob([JSON.stringify({ _token: document.querySelector('meta[name="csrf-token"]').content })], { type: 'application/json' })); });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
