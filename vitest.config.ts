import path from 'node:path';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['resources/js/offline/__tests__/setup.ts'],
        include: ['resources/js/**/*.test.ts'],
        clearMocks: true,
    },
});
