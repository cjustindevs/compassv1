import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// CSRF token for the broadcasting auth endpoint (private channels).
const csrfToken = document.head.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.content;
}

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Runtime public settings work with Render Docker builds without baking secrets into Vite.
const socket = JSON.parse(document.querySelector('meta[name="compass-broadcast"]')?.content || 'null');
const socketKey = socket?.key || import.meta.env.VITE_REVERB_APP_KEY;
window.Echo = socketKey ? new Echo({
    broadcaster: 'reverb',
    key: socketKey,
    wsHost: socket?.host || import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: socket?.port ?? import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: socket?.port ?? 443,
    forceTLS: socket ? socket.secure : (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    withCredentials: true,
    authEndpoint: '/broadcasting/auth',
}) : null;
