export class VoiceCallClient {
    constructor(root, config, environment) {
        this.root = root; this.config = config; this.env = environment;
        this.call = null; this.pc = null; this.stream = null; this.pendingCandidates = [];
        this.busy = false; this.polling = false; this.generation = 0; this.signalReady = false;
        this.available = false; this.ended = false; this.offerSent = false; this.restarts = 0;
        this.audio = root.querySelector('[data-voice-audio]');
        this.status = root.querySelector('[data-voice-status]');
        this.buttons = Object.fromEntries(['start','accept','decline','mute','leave','play'].map(name => [name,root.querySelector(`[data-voice-${name}]`)]));
        this.buttons.start.addEventListener('click', () => this.begin(false));
        this.buttons.accept.addEventListener('click', () => this.begin(true));
        this.buttons.decline.addEventListener('click', () => this.respond('decline'));
        this.buttons.leave.addEventListener('click', () => this.leave());
        this.buttons.mute.addEventListener('click', () => this.mute());
        this.buttons.play.addEventListener('click', () => this.play());
        this.env.window.addEventListener('pagehide', () => this.destroy());
        this.env.window.addEventListener('offline', () => this.message('Connection interrupted. Reconnecting…'));
        this.env.window.addEventListener('online', () => this.poll());
        this.render();
    }

    async request(name, data = null, query = '') {
        const response = await this.env.fetch(this.config[name] + query, {
            method: data ? 'POST' : 'GET', credentials: 'same-origin', cache: 'no-store',
            headers: {'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':this.env.csrf},
            body: data ? JSON.stringify(data) : undefined,
        });
        if (!response.ok) {
            let message = 'Voice is unavailable. Text chat remains available.';
            try { const json = await response.json(); message = json.message || message; } catch {}
            const error = new Error(message); error.status = response.status; throw error;
        }
        return response.json();
    }

    connect(echo) {
        this.echo = echo;
        this.channel = echo.private(`voice.${this.config.sessionId}.${this.config.userId}`)
            .subscribed(() => { this.signalReady = true; this.render(); this.poll(); })
            .error(() => { this.signalReady = false; this.message('Call connection is unavailable. You can use text chat.'); this.render(); })
            .listen('.VoiceSignal', signal => { this.receive(signal).catch(() => {
                if (this.call?.id === signal.call_id) this.fail('The audio connection failed. Please retry or use text chat.');
            }); });
        echo.private(`session.${this.config.sessionId}`).listen('SessionEnded', () => {
            this.endLocally();
        });
        echo.connector.pusher.connection.bind('disconnected', () => { this.signalReady = false; this.render(); });
        this.interval = this.env.setInterval(() => this.poll(), 4000);
        this.poll();
    }

    message(text) { this.status.textContent = text; }

    endLocally() {
        this.ended = true; this.stopMedia(); this.render();
        this.env.clearTimeout(this.deadlineTimer); this.env.clearInterval(this.interval);
        this.message('This support session has ended.');
    }
    render() {
        const active = this.call && ['ringing','connecting','connected'].includes(this.call.status);
        const incoming = active && this.call.status === 'ringing' && !this.call.is_caller;
        this.buttons.start.hidden = !!active || this.ended;
        this.buttons.start.disabled = !this.available || !this.signalReady || this.busy;
        this.buttons.accept.hidden = !incoming || this.ended;
        this.buttons.accept.disabled = this.busy || !this.signalReady || !this.available;
        this.buttons.decline.hidden = !incoming || this.ended;
        this.buttons.mute.hidden = !this.stream || this.ended;
        this.buttons.leave.hidden = !this.pc || !active || this.ended;
    }

    async poll() {
        if (this.polling || this.busy || this.ended) return;
        this.polling = true;
        try {
            const data = await this.request('state', null, this.pc && this.call ? '?call_id='+encodeURIComponent(this.call.id) : '');
            this.available = data.available;
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
            if (!next || !['ringing','connecting','connected'].includes(next.status)) {
                this.stopMedia();
                this.message(!this.available ? 'Voice is unavailable. Continue using text chat.' : next?.status === 'declined' ? 'Call declined. Text chat remains open.' : next?.status === 'failed' ? 'Call could not connect. Retry or use text chat.' : 'Ready for a live audio call.');
            } else if (next.status === 'ringing') {
                this.message(next.is_caller ? 'Calling your session partner…' : 'Your session partner is calling. Accept to enable your microphone.');
            } else if (!this.pc) {
                this.message('A call is in progress in another tab. Use that tab or wait for it to end.');
            } else if (next.is_caller && !this.offerSent && this.signalReady) {
                await this.offer();
            }
        } catch (error) {
            if ([401,403,409,419].includes(error.status)) { this.ended = true; this.stopMedia(); }
            this.message(error.message);
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
                this.message('Connected · live audio');
                this.request('action',{call_id:this.call.id,action:'connected'}).catch(error => this.fail(error.message));
                this.env.clearTimeout(this.reconnectTimer);
            } else if (peer.connectionState === 'disconnected') {
                this.message('Audio interrupted. Reconnecting…');
                this.env.clearTimeout(this.reconnectTimer);
                this.reconnectTimer = this.env.setTimeout(() => this.restart(), 5000);
            } else if (peer.connectionState === 'failed') this.restart();
        };
    }

    async begin(accept) {
        if (this.busy || this.ended || !this.signalReady || !this.available) return;
        this.busy = true; this.render();
        try {
            this.message('Allow microphone access to continue…');
            await this.prepare();
            const data = accept ? await this.request('action',{call_id:this.call.id,action:'accept'}) : await this.request('start',{});
            this.call = data.call;
            this.message(accept ? 'Connecting audio…' : 'Calling your session partner…');
        } catch (error) {
            if (accept && this.call) await this.request('action',{call_id:this.call.id,action:'decline'}).catch(() => {});
            this.stopMedia();
            this.message(error.name === 'NotAllowedError' ? 'Microphone access was denied. Allow it in browser settings, or continue using text chat.' : error.message);
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
        if (peer.connectionState !== 'connected') this.message('Connecting audio…');
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

    async restart() {
        if (!this.pc || this.ended || this.restarting || this.pc.connectionState === 'connected') return;
        this.env.clearTimeout(this.reconnectTimer);
        if (++this.restarts > 2) return this.fail('Audio could not reconnect. Retry the call or continue using text chat.');
        this.restarting = true;
        if (this.call?.is_caller && this.signalReady) {
            try { await this.offer(true); } catch { return this.fail('Audio could not reconnect. Please use text chat.'); }
            finally { this.restarting = false; }
        }
        this.restarting = false;
        this.reconnectTimer = this.env.setTimeout(() => this.restart(), 10000);
    }

    async respond(action) {
        if (!this.call) return;
        try { this.call = (await this.request('action',{call_id:this.call.id,action})).call; this.stopMedia(); this.message(action === 'decline' ? 'Call declined. Text chat remains open.' : 'Call ended. Text chat remains open.'); }
        catch (error) { this.message(error.message); }
        this.render();
    }

    async leave() {
        const id = this.call?.id;
        this.stopMedia(); this.render();
        if (id) { try { this.call=(await this.request('action',{call_id:id,action:'leave'})).call; } catch {} }
        this.message('You left the call. Your support session and text chat remain open.'); this.render();
    }

    async fail(message) {
        const id = this.call?.id;
        this.stopMedia(); this.message(message); this.render();
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
        const peer = this.pc; this.pc = null; peer?.close();
        this.stream?.getTracks().forEach(track => track.stop()); this.stream = null;
        this.audio.srcObject = null; this.buttons.play.hidden = true;
        this.buttons.mute.textContent = 'Mute'; this.buttons.mute.setAttribute('aria-pressed','false');
        this.pendingCandidates = []; this.offerSent = false; this.restarts = 0;
    }

    destroy() {
        this.ended = true;
        const id = this.call?.id;
        this.stopMedia(); this.env.clearTimeout(this.deadlineTimer); this.env.clearInterval(this.interval); this.echo?.disconnect();
        if (id) this.env.fetch(this.config.action,{method:'POST',credentials:'same-origin',keepalive:true,
            headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':this.env.csrf},body:JSON.stringify({call_id:id,action:'leave'})}).catch(() => {});
    }
}
