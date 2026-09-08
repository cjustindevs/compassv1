// Two authenticated local clients; creates and ends one clearly labelled demo session.
import { readFileSync } from 'node:fs';
import assert from 'node:assert/strict';

const base = 'http://127.0.0.1:8000';
const publicKey = readFileSync('.env', 'utf8').match(/^REVERB_APP_KEY=(.+)$/m)?.[1].trim().replace(/^"|"$/g, '');
assert(publicKey, 'Reverb public app key is missing.');

class Client {
    cookies = new Map(); csrf = '';
    async request(path, data) {
        const headers = {Cookie: [...this.cookies].map(([k, v]) => `${k}=${v}`).join('; ')};
        if (data) Object.assign(headers, {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf});
        const response = await fetch(new URL(path, base), {method: data ? 'POST' : 'GET', headers, redirect: 'manual', body: data ? JSON.stringify(data) : undefined});
        for (const item of response.headers.getSetCookie()) {
            const pair = item.split(';')[0], split = pair.indexOf('=');
            this.cookies.set(pair.slice(0, split), pair.slice(split + 1));
        }
        if (response.status >= 300 && response.status < 400) return this.request(response.headers.get('location'));
        assert(response.ok, `Local request ${new URL(path, base).pathname} failed (HTTP ${response.status}).`);
        const text = await response.text();
        this.csrf = text.match(/name="csrf-token" content="([^"]+)"/)?.[1] ?? text.match(/name="_token"\s+value="([^"]+)"/)?.[1] ?? this.csrf;
        return text;
    }
    async login(email, password) {
        await this.request('/login'); await this.request('/login', {email, password});
    }
}

class Channel {
    history = []; waiters = [];
    constructor() {
        this.ws = new WebSocket(`ws://127.0.0.1:8080/app/${encodeURIComponent(publicKey)}?protocol=7&client=compass-smoke&version=1.0`);
        this.ws.addEventListener('error', () => {});
        this.ws.addEventListener('message', event => {
            const packet = JSON.parse(event.data);
            if (typeof packet.data === 'string') { try { packet.data = JSON.parse(packet.data); } catch {} }
            if (packet.event === 'pusher:ping') this.ws.send(JSON.stringify({event: 'pusher:pong', data: {}}));
            this.history.push(packet);
            for (const waiter of [...this.waiters]) if (waiter.predicate(packet)) waiter.resolve(packet);
        });
    }
    wait(predicate) {
        const found = this.history.find(predicate);
        if (found) return Promise.resolve(found);
        return new Promise((resolve, reject) => {
            const waiter = {predicate, resolve: value => { clearTimeout(timer); this.waiters = this.waiters.filter(x => x !== waiter); resolve(value); }};
            const timer = setTimeout(() => { this.waiters = this.waiters.filter(x => x !== waiter); reject(new Error('Timed out waiting for a real Reverb event.')); }, 10000);
            this.waiters.push(waiter);
        });
    }
    async subscribe(client, sessionId) {
        const hello = await this.wait(x => x.event === 'pusher:connection_established');
        const channel = `private-session.${sessionId}`;
        const auth = JSON.parse(await client.request('/broadcasting/auth', {socket_id: hello.data.socket_id, channel_name: channel}));
        this.ws.send(JSON.stringify({event: 'pusher:subscribe', data: {channel, auth: auth.auth}}));
        await this.wait(x => x.event === 'pusher_internal:subscription_succeeded');
    }
    close() { this.ws.close(); }
}

const seeker = new Client(), helper = new Client();
let seekerChannel, helperChannel, started = false;
try {
    await seeker.login('SilentWillow52', 'Seeker@123');
    await helper.login('rina@compass.local', 'Helper@123');
    const screening = await seeker.request('/request/screening');
    const concern = screening.match(/<option value="(\d+)"/);
    assert(concern, 'Demo seeker already has a pending session or screening is unavailable.');
    await seeker.request('/request/screening', {concern_id: Number(concern[1]), description: 'Automated local live-chat verification.',
        current_suicide_plan: false, suicidal_thoughts: false, severe_distress: false, recurring_distress: false, difficulty_coping: false});
    await seeker.request('/request/preferences', {support_mode: 'chat', preferred_language: 'English'});
    const cases = await helper.request('/helper/cases');
    const assignment = cases.match(/\/helper\/cases\/(\d+)\/accept/);
    assert(assignment, 'No demo assignment found. Check helper schedule, readiness, and capacity.');
    const sessionId = Number(assignment[1]);
    await helper.request(`/helper/cases/${sessionId}/accept`, {}); started = true;
    await seeker.request('/session/chat');
    seekerChannel = new Channel(); helperChannel = new Channel();
    await seekerChannel.subscribe(seeker, sessionId);
    await helperChannel.subscribe(helper, sessionId);
    const first = 'Automated local verification: seeker message.';
    const second = 'Automated local verification: helper reply.';
    await seeker.request('/api/chat/send', {session_id: sessionId, message: first});
    await helperChannel.wait(x => x.event === 'MessageSent' && x.data.message === first);
    await helper.request('/api/chat/send', {session_id: sessionId, message: second});
    const reply = await seekerChannel.wait(x => x.event === 'MessageSent' && x.data.message === second);
    assert(reply.data.sender_name.startsWith('Peer Helper '), 'Helper identity was not pseudonymous.');
    const status = JSON.parse(await seeker.request(`/api/chat/status/${sessionId}`));
    assert(status.session.remaining_seconds > 0 && status.session.remaining_seconds <= 5400, 'Countdown is outside the 90-minute limit.');
    await seeker.request('/session/end', {}); started = false;
    await helperChannel.wait(x => x.event === 'SessionEnded');
    console.log('PASS: two authenticated clients exchanged messages through Reverb, helper alias was preserved, server countdown was valid, and session end reached the helper.');
} catch (error) {
    console.error(error.message); process.exitCode = 1;
} finally {
    if (started) { try { await seeker.request('/session/end', {}); } catch {} }
    seekerChannel?.close(); helperChannel?.close();
}
