import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';

/**
 * One cache for the whole app. The per-module hooks still declare their own
 * `staleTime`/`gcTime`; these defaults stop an unconfigured query from
 * refetching on every mount and keep a page's cache alive across a visit.
 */
const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 30_000,
            gcTime: 300_000,
            retry: 1,
        },
    },
});

type InertiaPageModule = { default: ComponentType };

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob<InertiaPageModule>('./Pages/**/*.tsx');
        const pagePath = `./Pages/${name}.tsx`;
        const pageImport = pages[pagePath];

        if (!pageImport) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        return pageImport().then((module) => module.default);
    },
    setup({ el, App, props }) {
        createRoot(el).render(
            <QueryClientProvider client={queryClient}>
                <App {...props} />
            </QueryClientProvider>,
        );
    },
    progress: {
        color: 'var(--color-aq-accent)',
    },
});
