import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

/**
 * Unit tests for the pure frontend logic.
 *
 * A separate config from `vite.config.js` on purpose: that one boots the
 * laravel-vite plugin (manifest, refresh, fonts), which a node-environment test
 * run neither needs nor should depend on. The alias list mirrors it — same
 * order, same targets — so a test and the app resolve the same file.
 */
export default defineConfig({
    resolve: {
        alias: [
            {
                find: '@/pages',
                replacement: fileURLToPath(new URL('./resources/js/Pages/', import.meta.url)),
            },
            {
                find: '@/layouts',
                replacement: fileURLToPath(new URL('./resources/js/Layouts/', import.meta.url)),
            },
            { find: '@', replacement: fileURLToPath(new URL('./resources/js/', import.meta.url)) },
        ],
    },
    test: {
        environment: 'node',
        include: ['resources/js/**/*.test.ts'],
    },
});
