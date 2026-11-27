/**
 * Global Inertia visit state.
 *
 * A page visit is not a component-local fetch: `router.visit` owns the request,
 * so the page needs one shared flag to disable controls and show skeleton rows
 * while the next page/props are in flight. `useVisitPending` is that flag.
 *
 * The flag is an external store, not `useState` in an effect. Router events can
 * fire between render and commit; `useSyncExternalStore` reads the store after
 * commit and cannot miss a start/finish pair. One listener pair serves every
 * consumer instead of one pair per page.
 */
import { useSyncExternalStore } from 'react';
import { router } from '@inertiajs/react';

let pending = 0;
let bound = false;
const listeners = new Set<() => void>();
const pendingReloads = new Map<string, () => void>();

type TableReloadOptions = {
    only?: string[];
    onFinish?: () => void;
};

const notify = (): void => {
    listeners.forEach((listener) => listener());
};

const setPending = (delta: 1 | -1): void => {
    pending = Math.max(0, pending + delta);

    if (pending === 0 || delta === 1) {
        notify();
    }
};

const currentReloadKey = (): string => window.location.pathname.replace(/\/+$/, '') || '/';

/** Cancel a background table refresh before a newer URL-owned visit starts. */
export const cancelPendingReload = (path: string = currentReloadKey()): void => {
    const key = path.replace(/\/+$/, '') || '/';
    const cancel = pendingReloads.get(key);

    if (cancel === undefined) {
        return;
    }

    pendingReloads.delete(key);
    cancel();
};

/**
 * Reload table props without letting an older response overwrite a newer URL.
 * Inertia 3 reloads are async; ordinary table visits remain synchronous.
 */
export const reloadCurrentPage = (options: TableReloadOptions = {}): void => {
    const key = currentReloadKey();
    cancelPendingReload(key);

    let active = true;
    let cancelToken: (() => void) | null = null;
    const cancel = (): void => {
        if (!active) {
            return;
        }

        active = false;
        cancelToken?.();
    };

    pendingReloads.set(key, cancel);

    router.reload({
        ...(options.only === undefined ? {} : { only: options.only }),
        onCancelToken: (token) => {
            if (!active) {
                token.cancel();

                return;
            }

            cancelToken = token.cancel;
        },
        onFinish: () => {
            active = false;

            if (pendingReloads.get(key) === cancel) {
                pendingReloads.delete(key);
            }

            options.onFinish?.();
        },
    });
};

const subscribe = (listener: () => void): (() => void) => {
    listeners.add(listener);

    /*
     * Bind once for the life of the app. Tearing the router listeners down
     * when the last consumer unmounts would let a visit finish while nothing
     * is listening and leave `pending` stuck at `true`.
     */
    if (!bound) {
        bound = true;
        router.on('start', () => setPending(1));
        router.on('finish', () => setPending(-1));
    }

    return () => {
        listeners.delete(listener);
    };
};

const getSnapshot = (): boolean => pending > 0;
const getServerSnapshot = (): boolean => false;

export function useVisitPending(): boolean {
    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
