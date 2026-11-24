import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

// Order matters: Vite's alias uses the first string-prefix match and never
// falls through to a later entry when the file is missing. `@/pages` is listed
// first so pages resolve before the app folder catches the prefix.
// `find` must NOT end in "/" - the resolver appends it when matching.
const alias = [
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
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
    ],
    resolve: {
        alias,
    },
    build: {
        // Production bundles ship no source maps: they would expose the
        // original TSX to anyone with the asset URL (OWASP A02/A05 policy).
        sourcemap: false,
    },
    server: {
        // Bind IPv4 explicitly. Vite otherwise writes `http://[::1]:5173`
        // into public/hot on Windows, and a CSP host-source cannot express an
        // IPv6 literal — the dev page loses its stylesheet and fonts while
        // the built assets keep working.
        host: '127.0.0.1',
        // The CSP allowlist in SecurityHeaders is origin-scoped. Silently
        // moving to 5174/5175 leaves the font manifest and the policy on
        // different ports, so fail loudly instead of shipping a font-less page.
        port: 5173,
        strictPort: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
}));
