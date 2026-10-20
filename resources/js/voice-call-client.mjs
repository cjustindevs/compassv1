export class VoiceCallClient {
    constructor(root, config, environment) {
        this.root = root; this.config = config; this.env = environment;
        this.call = null; this.pc = null; this.stream = null; this.pendingCandidates = [];
        this.busy = false; this.polling = false; this.generation = 0; this.signalReady = false;
        this.available = false; this.ended = false; this.offerSent = false; this.restarts = 0;
        this.transportError = null; this.transportAttempt = 0;
        this.httpReady = false; this.signalCursor = 0; this.seenSignals = new Set(); this.signalQueue = Promise.resolve();
        this.callFailure = null;
        this.uiState = 'checking'; this.connectedSince = null;
        this.audio = root.querySelector('[data-voice-audio]');
        this.status = root.querySelector('[data-voice-status]');
        this.badge = root.querySelector('[data-voice-badge]');
        this.title = root.querySelector('[data-voice-title]');
        this.duration = root.querySelector('[data-voice-duration]');
        this.buttons = Object.fromEntries(['start','accept','decline','mute','leave','play'].map(name => [name,root.querySelector(`[data-voice-${name}]`)]));
        this.buttons.start.addEventListener('click', () => this.begin(false));
        this.buttons.accept.addEventListener('click', () => this.begin(true));
        this.buttons.decline.addEventListener('click', () => this.respond('decline'));
        this.buttons.leave.addEventListener('click', () => this.leave());
        this.buttons.mute.addEventListener('click', () => this.mute());
        this.buttons.play.addEventListener('click', () => this.play());
        this.retryButton = root.querySelector('[data-voice-retry]');
        this.retryButton?.addEventListener('click', () => this.retryConnection());
        this.env.window.addEventListener('pagehide', () => this.destroy());
        this.env.window.addEventListener('offline', () => this.message('Connection interrupted. Reconnecting…', 'reconnecting'));
        this.env.window.addEventListener('online', () => this.poll());
        this.render();
    }

    async request(name, data = null, query = '') {
        const controller = this.env.AbortController ? new this.env.AbortController() : null;
        const timeout = controller ? this.env.setTimeout(() => controller.abort(), 15000) : null;
        try { const response = await this.env.fetch(this.config[name] + query, {
            method: data ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store',
            headers: {'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':this.env.csrf},
            body: data ? JSON.stringify(data) : undefined,
            ...(controller ? {signal:controller.signal} : {}),
        });
            if (!response.ok) {
                let message = 'Voice is unavailable. Text chat remains available.';
                try { const json = await response.json(); message = json.message || message; } catch (error) { if (error.name === 'AbortError') throw error; }
                const error = new Error(message); error.status = response.status; throw error;
            }
            return await response.json();
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('The call service did not respond. Retry the connection or use messages.');
            throw error;
        } finally { if (timeout !== null) this.env.clearTimeout(timeout); }
    }

    connect(echo) {
        this.echo = echo;
        if (echo) {
            this.subscribe();
            echo.private(`session.${this.config.sessionId}`).listen('SessionEnded', () => this.endLocally());
            const connection = echo.connector.pusher.connection;
            connection.bind('disconnected', () => {
                if (this.ended) return;
                this.signalReady = false;
                if (this.httpReady) { this.render(); return; }
                if (this.transportError) { this.message(this.transportError, 'unavailable'); this.render(); return; }
                this.watchTransport();
                this.message(this.pc ? 'Call signaling interrupted. Reconnecting…' : 'Connecting to the call service…', this.pc ? 'reconnecting' : 'checking');
                this.render();
            });
            for (const state of ['unavailable', 'failed', 'error']) connection.bind(state, () => {
                if (!this.ended) this.transportFailed('Unable to connect to the call service. Retry the connection or use messages.');
            });
        }
        this.interval = this.env.setInterval(() => this.poll(), 2000);
        this.durationInterval = this.env.setInterval(() => this.updateDuration(), 1000);
        this.poll();
    }

    watchTransport(reset = false) {
        if (this.transportTimer && !reset) return;
        this.env.clearTimeout(this.transportTimer);
        this.transportTimer = this.env.setTimeout(() => {
            if (!this.signalReady && !this.ended) this.transportFailed('The call connection took too long. Retry the connection or use messages.');
        }, 20000);
    }

    transportFailed(message) {
        this.signalReady = false; this.transportError = message;
        this.env.clearTimeout(this.transportTimer);
        this.transportTimer = null;
        if (!this.httpReady) this.message(message, this.pc ? 'reconnecting' : 'unavailable');
        this.render();
    }

    subscribe() {
        const attempt = ++this.transportAttempt;
        this.watchTransport(true);
        this.channel = this.echo.private(`voice.${this.config.sessionId}.${this.config.userId}`)
            .subscribed(() => {
                if (this.ended || attempt !== this.transportAttempt) return;
                this.signalReady = true; this.transportError = null; this.env.clearTimeout(this.transportTimer); this.transportTimer = null;
                this.render(); this.poll();
            })
            .error(error => {
                if (this.ended || attempt !== this.transportAttempt) return;
                this.transportFailed([401,403,419].includes(error?.status)
                    ? 'Unable to authorize this voice connection. Retry, or reopen your assigned session.'
                    : 'The private call connection could not be established. Retry the connection or use messages.');
            })
            .listen('.VoiceSignal', signal => this.enqueueSignal(signal));
    }

    retryConnection() {
        if (this.ended || this.busy) return;
        this.transportError = null; this.signalReady = false;
        if (!this.echo) { this.poll(); return; }
        this.echo.leave(`voice.${this.config.sessionId}.${this.config.userId}`);
        this.message('Retrying the private call connection…', this.pc ? 'reconnecting' : 'checking');
        this.subscribe(); this.echo.connector.pusher.connect(); this.render(); this.poll();
    }

    message(text, state = 'ready') {
        this.status.textContent = text; this.uiState = state; this.renderPresentation();
    }

    renderPresentation() {
        const labels = {
            checking:['Connecting', 'Getting your call ready'], ready:['Ready', 'Start a voice call'],
            permission:['Microphone', 'Allow microphone access'], ringing:['Ringing', 'Calling your session partner'],
            incoming:['Incoming call', 'Your session partner is calling'], connecting:['Connecting', 'Connecting your audio'],
            connected:['On call', 'You are connected'], reconnecting:['Reconnecting', 'Restoring your connection'],
            ended:['Call ended', 'Continue with text chat'], failed:['Call failed', 'Try again or use text chat'],
            unavailable:['Unavailable', 'Text chat is available'], other_tab:['In another tab', 'Call already in progress'],
        };
        const [badge,title] = labels[this.uiState] || labels.ready;
        this.root.setAttribute('data-voice-state', this.uiState);
        if (this.badge) this.badge.textContent = badge;
        if (this.title) this.title.textContent = title;
        this.updateDuration();
    }

    updateDuration() {
        if (!this.duration) return;
        this.duration.hidden = this.connectedSince === null || !this.pc || this.ended;
        if (this.duration.hidden) return;
        const seconds = Math.max(0, Math.floor(((this.env.now?.() ?? Date.now()) - this.connectedSince) / 1000));
        this.duration.textContent = `${String(Math.floor(seconds / 60)).padStart(2,'0')}:${String(seconds % 60).padStart(2,'0')}`;
    }

    endLocally() {
        this.ended = true; this.stopMedia(); this.render();
        this.env.clearTimeout(this.deadlineTimer); this.env.clearInterval(this.interval);
        this.env.clearInterval(this.durationInterval);
        this.env.clearTimeout(this.transportTimer);
        this.message('This support session has ended.', 'ended');
    }
    render() {
        const active = this.call && ['ringing','connecting','connected'].includes(this.call.status);
        const incoming = active && this.call.status === 'ringing' && !this.call.is_caller;
        this.buttons.start.hidden = !!active || this.ended;
        this.buttons.start.disabled = !this.available || !(this.signalReady || this.httpReady) || this.busy;
        if (this.retryButton) { this.retryButton.hidden = !this.transportError || this.httpReady || this.ended; this.retryButton.disabled = this.busy; }
        this.buttons.accept.hidden = !incoming || this.ended;
        this.buttons.accept.disabled = this.busy || !(this.signalReady || this.httpReady) || !this.available;
        this.buttons.decline.hidden = !incoming || this.ended;
        this.buttons.mute.hidden = !this.stream || this.ended;
        this.buttons.leave.hidden = !this.pc || !active || this.ended;
        this.buttons.leave.textContent = this.call?.status === 'ringing' && this.call?.is_caller ? 'Cancel call' : 'Leave call';
        this.renderPresentation();
    }

    async poll() {
        if (this.polling || this.busy || this.ended) return;
        this.polling = true;
        const generation = this.generation;
        try {
            const data = await this.request('state', null, this.pc && this.call ? '?call_id='+encodeURIComponent(this.call.id)+'&after_signal_id='+this.signalCursor : '');
            if (this.ended || this.busy || generation !== this.generation) return;
            this.available = data.available;
            this.httpReady = data.signaling_available === true;
            if (data.session.ended) {
                this.endLocally(); return;
            }
            if (Number.isFinite(data.session.remaining_seconds)) {
                this.env.clearTimeout(this.deadlineTimer);
                this.deadlineTimer = this.env.setTimeout(() => this.endLocally(), Math.max(0,data.session.remaining_seconds) * 1000);
            }
            const next = data.call;
            if (this.call && (!next || this.call.id !== next.id)) this.stopMedia();
            this.call = next;
            for (const signal of data.signals || []) await this.enqueueSignal(signal);
            if (this.ended || generation !== this.generation) return;
            this.signalCursor = Math.max(this.signalCursor, data.signal_cursor || 0);
            if (!next || !['ringing','connecting','connected'].includes(next.status)) {
                this.stopMedia();
                if (!this.available) this.message('Voice is unavailable. Continue using text chat.', 'unavailable');
                else if (this.callFailure) this.message(this.callFailure, 'failed');
                else if (next?.status === 'declined') this.message('Call declined. Text chat remains open.', 'ended');
                else if (next?.status === 'failed') this.message('Call could not connect. Retry or use text chat.', 'failed');
                else if (next?.status === 'ended') this.message('Call ended. You can call again or continue using text chat.', 'ended');
                else if (this.transportError && !this.httpReady) this.message(this.transportError, 'unavailable');
                else this.message((this.signalReady || this.httpReady) ? 'Ready for a live audio call.' : 'Connecting to the call service…', (this.signalReady || this.httpReady) ? 'ready' : 'checking');
            } else if (next.status === 'ringing') {
                if (this.transportError && !this.httpReady) this.message(this.transportError, 'unavailable');
                else this.message(next.is_caller ? 'Waiting for your session partner to answer…' : 'Accept to enable your microphone, or decline to keep using text chat.', next.is_caller ? 'ringing' : 'incoming');
            } else if (!this.pc) {
                this.message('A call is in progress in another tab. Use that tab or wait for it to end.', 'other_tab');
            } else {
                if (this.pc.connectionState === 'connected') this.message('Connected · live audio', 'connected');
                else this.message('Connecting audio. Keep this page open…', this.pc.connectionState === 'disconnected' || this.pc.connectionState === 'failed' ? 'reconnecting' : 'connecting');
                if (next.is_caller && !this.offerSent && (this.signalReady || this.httpReady)) await this.offer();
            }
        } catch (error) {
            if (this.ended || this.busy || generation !== this.generation) return;
            if ([401,403,409,419].includes(error.status)) { this.ended = true; this.stopMedia(); }
            this.transportError = error.message;
            this.httpReady = false;
            this.message(error.message, 'unavailable');
        } finally { this.polling = false; this.render(); }
    }

    async prepare() {
        if (!this.env.navigator.mediaDevices?.getUserMedia || !this.env.RTCPeerConnection) throw new Error('This browser cannot start an audio call. Use HTTPS and allow microphone access, or use text chat.');
        const generation = this.generation;
        const stream = await this.env.navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true},video:false});
        if (generation !== this.generation || this.ended) { stream.getTracks().forEach(track => track.stop()); throw new Error('Call cancelled.'); }
        this.stream = stream;
        const credentials = await this.request('credentials');
        if (generation !== this.generation || this.ended) throw new Error('Call cancelled.');
        this.pc = new this.env.RTCPeerConnection({iceServers:credentials.iceServers,iceTransportPolicy:'relay'});
        const peer = this.pc;
        stream.getTracks().forEach(track => peer.addTrack(track,stream));
        peer.onicecandidate = event => {
            if (event.candidate && this.pc === peer && this.call) {
                this.sendSignal('candidate',{candidate:event.candidate.toJSON()}).catch(() => {
                    if (this.pc === peer) this.fail('Call connection is unavailable. Please use text chat.');
                });
            }
        };
        peer.ontrack = event => {
            if (this.pc !== peer) return;
            if (event.track.kind !== 'audio') { event.track.stop(); return; }
            this.audio.srcObject = event.streams[0] || new this.env.MediaStream([event.track]);
            this.play();
        };
        peer.onconnectionstatechange = () => {
            if (this.pc !== peer) return;
            if (peer.connectionState === 'connected') {
                this.connectedSince ??= this.env.now?.() ?? Date.now();
                this.message('Connected · live audio', 'connected');
                this.request('action',{call_id:this.call.id,action:'connected'}).catch(error => this.fail(error.message));
                this.env.clearTimeout(this.reconnectTimer);
            } else if (peer.connectionState === 'disconnected') {
                this.message('Audio interrupted. Reconnecting…', 'reconnecting');
                this.env.clearTimeout(this.reconnectTimer);
                this.reconnectTimer = this.env.setTimeout(() => this.restart(), 5000);
            } else if (peer.connectionState === 'failed') this.restart();
        };
    }

    async begin(accept) {
        if (this.busy || this.ended || !(this.signalReady || this.httpReady) || !this.available) return;
        this.callFailure = null;
        this.busy = true; this.render();
        try {
            this.message('Allow microphone access to continue…', 'permission');
            await this.prepare();
            const generation = this.generation;
            const data = accept ? await this.request('action',{call_id:this.call.id,action:'accept'}) : await this.request('start',{});
            if (this.ended || generation !== this.generation) return;
            this.call = data.call;
            this.message(accept ? 'Connecting audio…' : 'Waiting for your session partner to answer…', accept ? 'connecting' : 'ringing');
        } catch (error) {
            if (accept && this.call) await this.request('action',{call_id:this.call.id,action:'decline'}).catch(() => {});
            this.stopMedia();
            this.callFailure = error.name === 'NotAllowedError' ? 'Microphone access was denied. Allow it in browser settings, or continue using text chat.' : error.message;
            this.message(this.callFailure, 'failed');
        } finally { this.busy = false; this.render(); }
    }

    async sendSignal(type, payload) {
        if (!this.call || !this.pc || this.ended) return;
        return this.request('signal',{call_id:this.call.id,type,...payload});
    }

    async offer(restart = false) {
        if (!this.pc || this.ended) return;
        this.offerSent = true;
        const peer = this.pc;
        const offer = await peer.createOffer(restart ? {iceRestart:true} : undefined);
        if (this.pc !== peer) return;
        await peer.setLocalDescription(offer);
        if (this.pc !== peer || this.ended) return;
        await this.sendSignal('offer',{sdp:offer.sdp});
        if (peer.connectionState !== 'connected') this.message(restart ? 'Restoring audio. Keep this page open…' : 'Connecting audio…', restart ? 'reconnecting' : 'connecting');
    }

    async receive(signal) {
        if (!this.pc || !this.call || signal.call_id !== this.call.id || this.ended) return;
        const peer = this.pc;
        if (signal.type === 'candidate') {
            if (!peer.remoteDescription) { if (this.pendingCandidates.length < 30) this.pendingCandidates.push(signal.candidate); }
            else await peer.addIceCandidate(signal.candidate);
            return;
        }
        if (!['offer','answer'].includes(signal.type)) return;
        await peer.setRemoteDescription({type:signal.type,sdp:signal.sdp});
        if (this.pc !== peer || this.ended) return;
        for (const candidate of this.pendingCandidates.splice(0)) await peer.addIceCandidate(candidate);
        if (signal.type === 'offer') {
            const answer = await peer.createAnswer();
            await peer.setLocalDescription(answer);
            if (this.pc !== peer || this.ended) return;
            await this.sendSignal('answer',{sdp:answer.sdp});
        }
    }

    enqueueSignal(signal) {
        if (!this.pc || !this.call || signal.call_id !== this.call.id || this.ended) return Promise.resolve();
        if (signal.signal_id && this.seenSignals.has(signal.signal_id)) return this.signalQueue;
        if (signal.signal_id) this.seenSignals.add(signal.signal_id);
        this.signalQueue = this.signalQueue.then(() => this.receive(signal)).catch(() => {
            if (!this.ended && this.call?.id === signal.call_id) return this.fail('The audio connection could not be negotiated. Retry the call or use messages.');
        });
        return this.signalQueue;
    }

    async restart() {
        if (!this.pc || this.ended || this.restarting || this.pc.connectionState === 'connected') return;
        this.env.clearTimeout(this.reconnectTimer);
        if (++this.restarts > 2) return this.fail('Audio could not reconnect. Retry the call or continue using text chat.');
        this.restarting = true;
        if (this.call?.is_caller && (this.signalReady || this.httpReady)) {
            try { await this.offer(true); } catch { return this.fail('Audio could not reconnect. Please use text chat.'); }
            finally { this.restarting = false; }
        }
        this.restarting = false;
        this.reconnectTimer = this.env.setTimeout(() => this.restart(), 10000);
    }

    async respond(action) {
        this.callFailure = null;
        if (!this.call) return;
        try { this.call = (await this.request('action',{call_id:this.call.id,action})).call; this.stopMedia(); this.message(action === 'decline' ? 'Call declined. Text chat remains open.' : 'Call ended. Text chat remains open.', 'ended'); }
        catch (error) { this.message(error.message, 'failed'); }
        this.render();
    }

    async leave() {
        this.callFailure = null;
        const id = this.call?.id;
        this.stopMedia(); this.render();
        if (id) { try { this.call=(await this.request('action',{call_id:id,action:'leave'})).call; } catch {} }
        this.message('You left the call. Your support session and text chat remain open.', 'ended'); this.render();
    }

    async fail(message) {
        const id = this.call?.id;
        this.stopMedia(); this.callFailure = message; this.message(message, 'failed'); this.render();
        if (id) await this.request('action',{call_id:id,action:'fail'}).catch(() => {});
    }

    mute() {
        if (!this.stream) return;
        const tracks = this.stream.getAudioTracks();
        const muted = tracks.some(track => track.enabled);
        tracks.forEach(track => { track.enabled = !muted; });
        this.buttons.mute.textContent = muted ? 'Unmute' : 'Mute';
        this.buttons.mute.setAttribute('aria-pressed',String(muted));
    }

    async play() {
        try { await this.audio.play(); this.buttons.play.hidden = true; }
        catch { this.buttons.play.hidden = false; }
    }

    stopMedia() {
        this.generation++;
        this.env.clearTimeout(this.reconnectTimer);
        const peer = this.pc; this.pc = null;
        if (peer) {
            peer.onicecandidate = peer.ontrack = peer.onconnectionstatechange = null;
            peer.getTransceivers?.().forEach(transceiver => { try { transceiver.stop?.(); } catch {} });
            peer.close();
        }
        this.stream?.getTracks().forEach(track => track.stop()); this.stream = null;
        this.audio.srcObject?.getTracks?.().forEach(track => track.stop());
        this.audio.srcObject = null; this.buttons.play.hidden = true;
        this.buttons.mute.textContent = 'Mute'; this.buttons.mute.setAttribute('aria-pressed','false');
        this.pendingCandidates = []; this.offerSent = false; this.restarts = 0;
        this.signalCursor = 0; this.seenSignals.clear(); this.signalQueue = Promise.resolve();
        this.connectedSince = null;
    }

    destroy() {
        this.ended = true;
        const id = this.call?.id;
        this.stopMedia(); this.env.clearTimeout(this.deadlineTimer); this.env.clearInterval(this.interval); this.echo?.disconnect();
        this.env.clearInterval(this.durationInterval);
        this.env.clearTimeout(this.transportTimer);
        if (id) this.env.fetch(this.config.action,{method:'POST',credentials:'same-origin',keepalive:true,
            headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.env.csrf},body:JSON.stringify({call_id:id,action:'leave'})}).catch(() => {});
    }
}
