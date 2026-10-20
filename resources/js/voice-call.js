import '../css/voice-call.css';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { VoiceCallClient } from './voice-call-client.mjs';

function initialize() {
    document.querySelectorAll('[data-voice-call]').forEach(root => {
        const config = JSON.parse(root.querySelector('[data-voice-config]').textContent);
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const client = new VoiceCallClient(root,config,{window,navigator,RTCPeerConnection:window.RTCPeerConnection,
            MediaStream:window.MediaStream,AbortController:window.AbortController,csrf,fetch:window.fetch.bind(window),setInterval,clearInterval,setTimeout,clearTimeout});
        if (!config.socket.key) { client.connect(null); return; }
        try {
        const pusher = new Pusher(config.socket.key,{cluster:'mt1',wsHost:config.socket.host || window.location.hostname,
            wsPort:config.socket.port,wssPort:config.socket.port,forceTLS:config.socket.secure,enabledTransports:['ws','wss'],
            disableStats:true,channelAuthorization:{endpoint:'/broadcasting/auth',transport:'ajax',headers:{'X-CSRF-TOKEN':csrf}}});
        client.connect(new Echo({broadcaster:'reverb',client:pusher}));
        } catch { if (!client.interval) client.connect(null); client.transportFailed('The realtime connection could not start. Checking the secure call connection.'); }
    });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',initialize,{once:true});
else initialize();
