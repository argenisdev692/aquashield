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

let pending = false;
let bound = false;
const listeners = new Set<() => void>();

const notify = (): void => {
    listeners.forEach((listener) => listener());
};

const setPending = (value: boolean): void => {
    if (pending === value) {
        return;
    }

    pending = value;
    notify();
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
        router.on('start', () => setPending(true));
        router.on('finish', () => setPending(false));
    }

    return () => {
        listeners.delete(listener);
    };
};

const getSnapshot = (): boolean => pending;
const getServerSnapshot = (): boolean => false;

export function useVisitPending(): boolean {
    return useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);
}
