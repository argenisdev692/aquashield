import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { createInertiaApp, router } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { useEffect } from 'react';
import type { ComponentType } from 'react';
import ErrorBoundary from '@/Components/ErrorBoundary';
import { Button } from '@/Components/ui/button';
import { getCspNonce } from '@/lib/csp';

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

const cspNonce = getCspNonce();

/**
 * One place that turns an Inertia transport failure into a diagnostic line.
 * The default modal still owns the user-facing copy; this only records what the
 * browser knew (status, route, field names — never values or payloads).
 */
function InertiaErrorReporting() {
    useEffect(() => {
        const offHttpException = router.on('httpException', (event) => {
            console.error('[inertia] http exception', {
                status: event.detail.response.status,
            });
        });

        const offNetworkError = router.on('networkError', (event) => {
            console.error('[inertia] network error', event.detail.error);
        });

        const offError = router.on('error', (event) => {
            console.error('[inertia] request errors', Object.keys(event.detail.errors));
        });

        return () => {
            offHttpException();
            offNetworkError();
            offError();
        };
    }, []);

    return null;
}

function AppErrorFallback({ reset }: { reset: () => void }) {
    return (
        <main className="grid min-h-dvh place-items-center bg-surface-50 px-4 py-10 dark:bg-surface-950">
            <div
                role="alert"
                className="w-full max-w-md rounded-xl border border-aq-shell-line bg-surface-0 p-6 shadow-sm dark:bg-surface-900"
            >
                <h1 className="text-lg font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                    Something went wrong
                </h1>
                <p className="mt-2 text-sm text-surface-600 dark:text-surface-300">
                    The page could not be rendered. Your data was not changed.
                </p>
                <div className="mt-5 flex flex-wrap gap-2">
                    <Button type="button" onClick={() => window.location.reload()}>
                        Reload page
                    </Button>
                    <Button type="button" variant="outline" onClick={reset}>
                        Try again
                    </Button>
                </div>
            </div>
        </main>
    );
}

// react-remove-scroll (used by Base UI dialogs/popovers) reads this
// webpack-style global when it injects its scroll-lock styles. Without it the
// injected `<style>` has no nonce and the CSP blocks it.
if (cspNonce) {
    (globalThis as typeof globalThis & { __webpack_nonce__?: string }).__webpack_nonce__ = cspNonce;
}

createInertiaApp({
    nonce: cspNonce,
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
                <InertiaErrorReporting />
                <ErrorBoundary fallback={({ reset }) => <AppErrorFallback reset={reset} />}>
                    <App {...props} />
                </ErrorBoundary>
            </QueryClientProvider>,
        );
    },
    progress: {
        color: 'var(--color-aq-accent)',
    },
});
