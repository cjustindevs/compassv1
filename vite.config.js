import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/breathing-exercise.js', 'resources/js/chat.js', 'resources/js/helper-notifications.js', 'resources/js/seeker-notifications.js', 'resources/js/adviser-notifications.js', 'resources/js/moderator-notifications.js'],
            refresh: true,
        }),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'public',
            filename: 'serviceworker.js',
            registerType: 'autoUpdate',
            injectRegister: false,
            includeAssets: ['favicon.ico', 'robots.txt'],
            manifest: {
                name: 'COMPASS - Peer Support System',
                short_name: 'COMPASS',
                description: 'Competency Oversight, Monitoring, Peer Assistance, Support, and Supervision',
                theme_color: '#04A052',
                background_color: '#F8FBF9',
                display: 'standalone',
                orientation: 'portrait',
                start_url: '/',
                scope: '/',
                icons: [
                    {
                        src: '/icons/icon-72x72.png',
                        sizes: '72x72',
                        type: 'image/png',
                        purpose: 'any'
                    },
                    {
                        src: '/icons/icon-96x96.png',
                        sizes: '96x96',
                        type: 'image/png',
                        purpose: 'any'
                    },
                    {
                        src: '/icons/icon-128x128.png',
                        sizes: '128x128',
                        type: 'image/png',
                        purpose: 'any'
                    },
                    {
                        src: '/icons/icon-144x144.png',
                        sizes: '144x144',
                        type: 'image/png',
                        purpose: 'any'
                    },
                    {
                        src: '/icons/icon-152x152.png',
                        sizes: '152x152',
                        type: 'image/png',
                        purpose: 'any'
                    },
                    {
                        src: '/icons/icon-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'any'
                    },
                    {
                        src: '/icons/icon-384x384.png',
                        sizes: '384x384',
                        type: 'image/png',
                        purpose: 'any'
                    },
                    {
                        src: '/icons/icon-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any'
                    }
                ],
                screenshots: [
                    {
                        src: '/screenshots/dashboard-mobile.png',
                        sizes: '1080x1920',
                        type: 'image/png'
                    }
                ]
            },
            injectManifest: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2,woff,ttf,json}'],
                additionalManifestEntries: [
                    { url: '/offline.html', revision: '2026-08-19' }
                ],
                maximumFileSizeToCacheInBytes: 10 * 1024 * 1024,
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        // Keep HMR on the same host so external browsers can reach the dev server.
        // `localStorage` / session-based Vite clients resolve 'localhost' to IPv6 (::1)
        // in Chrome, which silently kills hot-reload assets unless host + hmr.host match.
        hmr: {
            host: '127.0.0.1',
        },
    },
});