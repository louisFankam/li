import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import tailwindcss from 'tailwindcss';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: ['favicon.ico', 'robots.txt', 'pwa-192.png', 'pwa-512.png'],
            manifest: {
                id: '/',
                name: 'PharmaGest',
                short_name: 'PharmaGest',
                description: 'Gestion de pharmacies',
                lang: 'fr',
                theme_color: '#0e9f6e',
                background_color: '#f3f7f4',
                display: 'standalone',
                start_url: '/',
                scope: '/',
                icons: [
                    { src: '/pwa-192.png', sizes: '192x192', type: 'image/png', purpose: 'any maskable' },
                    { src: '/pwa-512.png', sizes: '512x512', type: 'image/png', purpose: 'any maskable' },
                ],
            },
            workbox: {
                navigateFallback: '/',
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
                runtimeCaching: [
                    {
                        urlPattern: /^\/api\/sync\/pull/,
                        handler: 'NetworkFirst',
                        options: { cacheName: 'sync-pull', expiration: { maxEntries: 10, maxAgeSeconds: 86400 } },
                    },
                ],
            },
            devOptions: { enabled: false },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    css: {
        postcss: {
            plugins: [tailwindcss, autoprefixer],
        },
    },
});
