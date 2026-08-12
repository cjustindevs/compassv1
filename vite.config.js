import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
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