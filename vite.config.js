import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';
import { templateCompilerOptions } from '@tresjs/core';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

// Order matters: Vite's alias uses the first string-prefix match and never
// falls through to a later entry when the file is missing. `@/volt` is listed
// first so Volt components (src/volt) win over the app folder (resources/js).
// `find` must NOT end in "/" - the resolver appends it when matching.
const alias = [
    { find: '@/volt', replacement: fileURLToPath(new URL('./src/volt/', import.meta.url)) },
    {
        find: '@/pages',
        replacement: fileURLToPath(new URL('./resources/js/Pages/', import.meta.url)),
    },
    {
        find: '@/layouts',
        replacement: fileURLToPath(new URL('./resources/js/Layouts/', import.meta.url)),
    },
    { find: '@', replacement: fileURLToPath(new URL('./resources/js/', import.meta.url)) },
];

export default defineConfig(({ command }) => ({
    // Laravel serves `public/` itself. Vite only needs to serve it in dev so
    // CSS `url('/img/...')` masks and the shield texture resolve on the Vite
    // origin; laravel-vite-plugin disables publicDir by default, which made
    // those images 404 in dev while the production build worked. On build it
    // stays off so `public/` is never copied into the build directory.
    publicDir: command === 'serve' ? 'public' : false,
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        // TresJS registers `Tres*` and `primitive` on its own custom renderer.
        // Without these compiler options Vue tries to resolve them as Vue
        // components, warns, and the camera never reaches the scene.
        vue({
            ...templateCompilerOptions,
        }),
        tailwindcss(),
    ],
    resolve: {
        alias,
    },
    // Three ships hundreds of ES modules. Without pre-bundling, the first
    // scroll to the brand moment triggers a mid-session dependency optimize
    // and a full reload — the exact moment the scene is supposed to appear.
    optimizeDeps: {
        include: ['three', '@tresjs/core'],
    },
    server: {
        // Bind IPv4 explicitly. Vite otherwise writes `http://[::1]:5173`
        // into public/hot on Windows, and a CSP host-source cannot express an
        // IPv6 literal — the dev page loses its stylesheet and fonts while
        // the built assets keep working.
        host: '127.0.0.1',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
}));
