import { fileURLToPath } from 'node:url';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

/**
 * Unit tests for the pure frontend logic, plus the DOM behaviour that no
 * architecture grep can see (a dialog that never receives focus, a hook that
 * invents a success). The default environment stays `node`: it is faster, and a
 * component test opts into jsdom with the standard `// @vitest-environment
 * jsdom` docblock, so adding one does not slow the pure suites down.
 *
 * A separate config from `vite.config.js` on purpose: that one boots the
 * laravel-vite plugin (manifest, refresh), which a test run neither needs nor
 * should depend on. The alias list mirrors it — same order, same targets — so a
 * test and the app resolve the same file.
 */
export default defineConfig({
    /*
     * `@vitejs/plugin-react` is the only plugin a test run needs: it provides
     * the JSX transform for `.tsx` suites. The Laravel, Tailwind and React
     * Compiler plugins stay out — a test does not build CSS, and the compiler's
     * memoisation is not what these assertions measure.
     */
    plugins: [react()],
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
        include: ['resources/js/**/*.test.{ts,tsx}'],
    },
});
