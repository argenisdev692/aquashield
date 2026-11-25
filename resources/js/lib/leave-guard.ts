/**
 * Guard an Inertia page visit while a form has unsaved changes.
 *
 * `useUnsavedChangesWarning` covers tab close and reload (`beforeunload`); the
 * common loss path in this app is an in-app visit (Cancel, the sidebar, a
 * `router.visit`). Inertia's global `before` event is cancelable, so the page
 * can ask before leaving. Form submissions are deliberately not guarded: the
 * POST/PUT is the save, not a navigation away from it.
 *
 * Browser Back/Forward is a history event rather than a visit; Inertia restores
 * the cached page and `before` does not fire. That path is outside this hook.
 */
import { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';

export interface NavigationGuard {
    /** The URL a dirty form is trying to leave for, or null. */
    pendingUrl: string | null;
    confirmLeave: () => void;
    cancelLeave: () => void;
}

interface BeforeEventDetail {
    visit?: {
        url: URL;
        method?: string;
        prefetch?: boolean;
    };
}

export function useNavigationGuard(isDirty: boolean): NavigationGuard {
    const [pendingUrl, setPendingUrl] = useState<string | null>(null);
    const dirtyRef = useRef(isDirty);
    const allowNext = useRef(false);

    useEffect(() => {
        dirtyRef.current = isDirty;
    }, [isDirty]);

    useEffect(() => {
        return router.on('before', (event) => {
            const visit = (event as CustomEvent<BeforeEventDetail>).detail?.visit;

            if (visit === undefined) {
                return;
            }

            // A prefetch warms a page; it is not a navigation away from this form.
            if (visit.prefetch === true) {
                return;
            }

            if (allowNext.current) {
                allowNext.current = false;

                return;
            }

            if (!dirtyRef.current) {
                return;
            }

            if ((visit.method ?? 'get').toUpperCase() !== 'GET') {
                return;
            }

            event.preventDefault();
            setPendingUrl(String(visit.url));
        });
    }, []);

    const confirmLeave = (): void => {
        if (pendingUrl === null) {
            return;
        }

        allowNext.current = true;
        setPendingUrl(null);
        router.visit(pendingUrl);
    };

    const cancelLeave = (): void => {
        allowNext.current = false;
        setPendingUrl(null);
    };

    return { pendingUrl, confirmLeave, cancelLeave };
}
