// Live voice supervision, participant side: for each Adviser listening to the call, send a copy of
// this participant's own microphone track over a separate send-only connection (through the relay
// server only). The Adviser never sends audio back. Muting the call mutes these copies too, because
// they share the same microphone track.
export class ListenerLinks {
    constructor(env, send) {
        this.env = env;
        this.send = send;          // (payload) => Promise, posts to the call's signal endpoint
        this.links = new Map();    // listener id -> { pc, callId, pending }
    }

    get count() { return this.links.size; }

    // listeners: [{id}] from the call state; ctx: {stream, iceServers, callId} while this tab is in the call, else null.
    async sync(listeners, ctx) {
        const wanted = new Set((listeners || []).map((listener) => Number(listener.id)));
        for (const [id, link] of this.links) {
            if (!wanted.has(id) || !ctx || link.callId !== ctx.callId) this.close(id);
        }
        if (!ctx || !ctx.stream || !ctx.iceServers || !ctx.callId) return;
        for (const id of wanted) {
            if (!this.links.has(id)) await this.open(id, ctx);
        }
    }

    async open(id, ctx) {
        const pc = new this.env.RTCPeerConnection({ iceServers: ctx.iceServers, iceTransportPolicy: 'relay' });
        const link = { pc, callId: ctx.callId, pending: [] };
        this.links.set(id, link);
        for (const track of ctx.stream.getAudioTracks()) {
            if (typeof pc.addTransceiver === 'function') pc.addTransceiver(track, { direction: 'sendonly', streams: [ctx.stream] });
            else pc.addTrack(track, ctx.stream);
        }
        pc.onicecandidate = (event) => {
            if (event.candidate && this.links.get(id) === link) {
                this.send({ call_id: ctx.callId, listener_id: id, type: 'candidate', candidate: event.candidate.toJSON() }).catch(() => {});
            }
        };
        const offer = await pc.createOffer();
        if (this.links.get(id) !== link) return;
        await pc.setLocalDescription(offer);
        if (this.links.get(id) !== link) return;
        await this.send({ call_id: ctx.callId, listener_id: id, type: 'offer', sdp: offer.sdp });
    }

    // An answer or ICE candidate from the listening Adviser.
    async receive(signal) {
        const link = this.links.get(Number(signal.listener_id));
        if (!link || signal.call_id !== link.callId) return;
        if (signal.type === 'candidate') {
            if (!link.pc.remoteDescription) { if (link.pending.length < 30) link.pending.push(signal.candidate); }
            else await link.pc.addIceCandidate(signal.candidate);
            return;
        }
        if (signal.type !== 'answer') return;
        await link.pc.setRemoteDescription({ type: 'answer', sdp: signal.sdp });
        for (const candidate of link.pending.splice(0)) await link.pc.addIceCandidate(candidate);
    }

    close(id) {
        const link = this.links.get(id);
        if (!link) return;
        this.links.delete(id);
        link.pc.onicecandidate = null;
        try { link.pc.close(); } catch { /* already closed */ }
    }

    closeAll() { for (const id of [...this.links.keys()]) this.close(id); }
}
