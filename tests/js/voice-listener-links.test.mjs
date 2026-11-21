import test from 'node:test';
import assert from 'node:assert/strict';
import { ListenerLinks } from '../../resources/js/voice-listener-links.mjs';

function fakeEnv() {
    const created = [];
    class FakePeer {
        constructor(config) { this.config = config; this.transceivers = []; this.remoteDescription = null; this.candidates = []; this.closed = false; created.push(this); }
        addTransceiver(track, init) { this.transceivers.push({ track, direction: init.direction }); }
        async createOffer() { return { type: 'offer', sdp: 'v=0\r\nm=audio 9 UDP/TLS/RTP/SAVPF 111\r\na=sendonly\r\n' }; }
        async setLocalDescription(desc) { this.localDescription = desc; }
        async setRemoteDescription(desc) { this.remoteDescription = desc; }
        async addIceCandidate(candidate) { this.candidates.push(candidate); }
        close() { this.closed = true; }
    }
    return { env: { RTCPeerConnection: FakePeer }, created };
}
const track = { kind: 'audio', id: 'mic' };
const stream = { getAudioTracks: () => [track] };
const ctx = { stream, iceServers: [{ urls: 'turn:relay.example:443' }], callId: 'call-1' };

test('each listening Adviser gets a send-only, relay-only copy of the microphone', async () => {
    const { env, created } = fakeEnv();
    const sent = [];
    const links = new ListenerLinks(env, async (payload) => { sent.push(payload); });
    await links.sync([{ id: 7 }], ctx);
    assert.equal(links.count, 1);
    assert.equal(created[0].config.iceTransportPolicy, 'relay');
    assert.deepEqual(created[0].transceivers, [{ track, direction: 'sendonly' }]);
    assert.deepEqual(sent.map((s) => [s.type, s.listener_id, s.call_id]), [['offer', 7, 'call-1']]);
    await links.sync([{ id: 7 }], ctx);
    assert.equal(created.length, 1, 'an existing link is not reopened');
});

test('answers and candidates reach the right link; early candidates wait for the answer', async () => {
    const { env, created } = fakeEnv();
    const links = new ListenerLinks(env, async () => {});
    await links.sync([{ id: 7 }, { id: 8 }], ctx);
    await links.receive({ listener_id: 8, call_id: 'call-1', type: 'candidate', candidate: { candidate: 'c1' } });
    assert.deepEqual(created[1].candidates, []);
    await links.receive({ listener_id: 8, call_id: 'call-1', type: 'answer', sdp: 'answer-sdp' });
    assert.equal(created[1].remoteDescription.sdp, 'answer-sdp');
    assert.deepEqual(created[1].candidates, [{ candidate: 'c1' }]);
    assert.equal(created[0].remoteDescription, null, 'the other link is untouched');
    await links.receive({ listener_id: 8, call_id: 'other-call', type: 'answer', sdp: 'x' });
    assert.equal(created[1].remoteDescription.sdp, 'answer-sdp', 'signals for another call are ignored');
});

test('links close when the Adviser stops, the call changes, or this tab leaves the call', async () => {
    const { env, created } = fakeEnv();
    const links = new ListenerLinks(env, async () => {});
    await links.sync([{ id: 7 }, { id: 8 }], ctx);
    await links.sync([{ id: 8 }], ctx);
    assert.equal(created[0].closed, true);
    assert.equal(links.count, 1);
    await links.sync([{ id: 8 }], { ...ctx, callId: 'call-2' });
    assert.equal(created[1].closed, true, 'a new call reconnects from scratch');
    await links.sync([{ id: 8 }], null);
    assert.equal(links.count, 0);
});
