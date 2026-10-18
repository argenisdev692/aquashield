import { defineConfig } from 'vite';
import { fileURLToPath } from 'node:url';
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

export default defineConfig({
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
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
