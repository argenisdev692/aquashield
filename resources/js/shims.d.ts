/**
 * Ambient type shims for the React frontend.
 *
 * The Vue-era shims (`@inertiajs/vue3`, `@pinia/colada`, `pinia`, `@vueuse/core`,
 * `motion-v`, `primevue/*`, `volt-vue`) were removed when the app moved to
 * React 19 + Inertia React + TanStack Query: the real packages ship their own
 * types, and the app augments `@inertiajs/core` in `inertia.d.ts`.
 */

import '@inertiajs/react';
