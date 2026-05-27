/// <reference types="vitest/config" />
import { resolve } from 'node:path';

import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

// A standalone config for the frontend test run. We deliberately skip the
// Laravel / Inertia / Wayfinder Vite plugins here: they expect a built
// manifest and a running backend, neither of which exists under jsdom.
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['resources/js/test/setup.ts'],
        include: ['resources/js/**/*.{test,spec}.{ts,tsx}'],
        css: false,
        restoreMocks: true,
        coverage: {
            provider: 'v8',
            reportsDirectory: './coverage',
            include: ['resources/js/**/*.{ts,tsx}'],
            exclude: [
                'resources/js/**/*.{test,spec}.{ts,tsx}',
                'resources/js/test/**',
                'resources/js/components/ui/**',
                'resources/js/actions/**',
                'resources/js/routes/**',
                'resources/js/wayfinder/**',
                'resources/js/types/**',
                'resources/js/**/*.d.ts',
            ],
        },
    },
});
