import { precacheAndRoute } from 'workbox-precaching';
import { registerRoute } from 'workbox-routing';
import { CacheFirst } from 'workbox-strategies';
import { ExpirationPlugin } from 'workbox-expiration';

// Precache all build assets (injected by vite-plugin-pwa at build time).
precacheAndRoute(self.__WB_MANIFEST);

// ── Offline page fallback ──
// Cache-first for previously visited pages, network otherwise,
// and /offline.html as the graceful fallback when there is no connection.
const offlinePageHandler = async ({ event }) => {
    try {
        return await fetch(event.request);
    } catch (error) {
        return caches.match('/offline.html');
    }
};
registerRoute(({ request }) => request.mode === 'navigate', offlinePageHandler);

// ── Runtime caching (Cache-first for CDN assets, Network-first for APIs) ──
registerRoute(
    /^https:\/\/fonts\.googleapis\.com\/.*/i,
    new CacheFirst({
        cacheName: 'google-fonts-cache',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 10,
                maxAgeSeconds: 60 * 60 * 24 * 365,
            }),
        ],
    })
);

registerRoute(
    /^https:\/\/fonts\.bunny\.net\/.*/i,
    new CacheFirst({
        cacheName: 'bunny-fonts-cache',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 10,
                maxAgeSeconds: 60 * 60 * 24 * 365,
            }),
        ],
    })
);

registerRoute(
    /^https:\/\/cdnjs\.cloudflare\.com\/.*/i,
    new CacheFirst({
        cacheName: 'cdn-cache',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 10,
                maxAgeSeconds: 60 * 60 * 24 * 30,
            }),
        ],
    })
);

registerRoute(
    /^https:\/\/cdn\.tailwindcss\.com\/.*/i,
    new CacheFirst({
        cacheName: 'tailwind-cache',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 5,
                maxAgeSeconds: 60 * 60 * 24 * 7,
            }),
        ],
    })
);

// Same-origin images, fonts, and styles that are not precached.
registerRoute(
    ({ request }) =>
        request.destination === 'image' ||
        request.destination === 'font' ||
        request.destination === 'style',
    new CacheFirst({
        cacheName: 'static-cache',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 100,
                maxAgeSeconds: 60 * 60 * 24 * 30,
            }),
        ],
    })
);

// ── Push notifications ──
self.addEventListener('push', (event) => {
    let data = {};
    if (event.data) {
        try {
            data = event.data.json();
        } catch (error) {
            data = { body: event.data.text() };
        }
    }

    const options = {
        body: data.body || 'You have a new notification',
        icon: '/icons/icon-192x192.png',
        badge: '/icons/icon-72x72.png',
        vibrate: [200, 100, 200],
        data: {
            url: data.url || '/',
        },
        actions: data.actions || [
            { action: 'open', title: 'Open' }
        ],
    };

    event.waitUntil(
        self.registration.showNotification(data.title || 'COMPASS', options)
    );
});

// ── Notification click ──
self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data.url || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window' })
            .then((windowClients) => {
                for (const client of windowClients) {
                    if (client.url === url && 'focus' in client) {
                        return client.focus();
                    }
                }
                if (clients.openWindow) {
                    return clients.openWindow(url);
                }
            })
    );
});

// ── Lifecycle: take control immediately (used with registerType: autoUpdate) ──
self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim());
});
