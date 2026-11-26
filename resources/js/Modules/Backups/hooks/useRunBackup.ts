/**
 * Backups module network boundary (architecture invariant 1).
 *
 * Queuing a dump is a JSON call followed by a reload of the current view: the
 * filters, sort and page the user is looking at survive the action, and the
 * new archive appears in place once the worker finishes. Pages never call
 * `requestJson` themselves — this hook owns the request, the pending state and
 * the user-facing copy.
 */
import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { HttpRequestError, requestJson } from '@/lib/http';

/** `queued` means the job was accepted; the archive is not on disk yet. */
export type RunBackupStatus = 'idle' | 'queued';

export interface RunBackup {
    /** Queue a database-only dump. Resolves `true` when the job was accepted. */
    run: () => Promise<boolean>;
    pending: boolean;
    status: RunBackupStatus;
    /** Product copy, never the server's raw message. */
    error: string | null;
    reset: () => void;
}

/**
 * Map a failure to copy the panel can show. The status is the only contract:
 * the response body may be a Laravel validation bag or an HTML error page,
 * and neither belongs in the UI verbatim.
 */
const messageFor = (cause: unknown): string => {
    if (cause instanceof HttpRequestError) {
        switch (cause.status) {
            case 403:
                return 'You do not have permission to run backups.';
            case 419:
            case 401:
                return 'Your session expired. Reload the page and sign in again.';
            case 429:
                return 'Too many backup requests. Wait a minute and try again.';
            default:
                break;
        }
    }

    return 'The backup could not be queued. Check that the queue worker is running and try again.';
};

export function useRunBackup(): RunBackup {
    const [pending, setPending] = useState(false);
    const [status, setStatus] = useState<RunBackupStatus>('idle');
    const [error, setError] = useState<string | null>(null);
    /*
     * `pending` only disables the button after the next render; a same-tick
     * double invocation could queue two dumps. The ref is the synchronous guard
     * (the bulk-actions hook uses the same pattern).
     */
    const inFlight = useRef(false);

    const run = async (): Promise<boolean> => {
        if (inFlight.current) {
            return false;
        }

        inFlight.current = true;
        setPending(true);
        setError(null);

        try {
            await requestJson('/backups/run', { method: 'POST' });
            setStatus('queued');

            /*
             * Partial reload: only the props this screen renders. The dialog
             * stays open so the queue confirmation is actually read.
             */
            router.reload({
                only: ['rows', 'total', 'currentPage', 'perPage', 'lastPage', 'filters', 'disks'],
            });

            return true;
        } catch (cause) {
            setStatus('idle');
            setError(messageFor(cause));

            return false;
        } finally {
            inFlight.current = false;
            setPending(false);
        }
    };

    const reset = (): void => {
        setStatus('idle');
        setError(null);
    };

    return { run, pending, status, error, reset };
}
