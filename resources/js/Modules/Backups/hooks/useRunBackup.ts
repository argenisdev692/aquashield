/**
 * Backups module network boundary (architecture invariant 1).
 *
 * Queuing a dump is a JSON call followed by a coordinated refresh of the
 * current view. While the job is active, the hook polls the lightweight status
 * endpoint and refreshes the rows once when the run reaches a terminal state.
 * Pages never call `requestJson` themselves.
 */
import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { HttpRequestError, requestJson } from '@/lib/http';
import { reloadCurrentPage } from '@/lib/visits';

export type RunBackupStatus = Modules.Backups.Domain.Enums.BackupRunStatus;

const REQUEST_TIMEOUT_MS = 30_000;
const POLL_INTERVAL_MS = 5_000;
const POLL_TIMEOUT_MS = 30 * 60_000;
const TABLE_PROPS = ['rows', 'total', 'currentPage', 'perPage', 'lastPage', 'filters', 'disks'];
const RUN_STATUSES: ReadonlySet<string> = new Set([
    'idle',
    'queued',
    'running',
    'succeeded',
    'failed',
    'skipped',
]);
const TERMINAL_STATUSES: ReadonlySet<RunBackupStatus> = new Set(['succeeded', 'failed', 'skipped']);
const isActiveStatus = (status: RunBackupStatus): boolean =>
    status === 'queued' || status === 'running';

export interface RunBackup {
    /** Queue a database-only dump. Resolves `true` when the job was accepted. */
    run: () => Promise<boolean>;
    pending: boolean;
    polling: boolean;
    status: RunBackupStatus;
    /** Product copy, never the server's raw message. */
    error: string | null;
    reset: () => void;
}

const isRunStatusData = (value: unknown): value is Modules.Backups.Data.BackupRunStatusData => {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const record = value as Record<string, unknown>;

    return (
        typeof record.status === 'string' &&
        RUN_STATUSES.has(record.status) &&
        (record.updated_at === null || typeof record.updated_at === 'string')
    );
};

/** Map transport failures to copy the panel can safely show. */
const messageFor = (cause: unknown): string => {
    if (cause instanceof DOMException && cause.name === 'TimeoutError') {
        return 'The backup request timed out. Check your connection and try again.';
    }

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

const terminalError = (status: RunBackupStatus): string | null => {
    switch (status) {
        case 'failed':
            return 'The backup failed. Check the audit trail and queue worker, then try again.';
        case 'skipped':
            return 'Another backup process held the lock, so this run was skipped.';
        default:
            return null;
    }
};

export function useRunBackup(): RunBackup {
    const page = usePage<{ runStatus: Modules.Backups.Data.BackupRunStatusData }>();
    const initialStatus = page.props.runStatus.status;
    const initiallyActive = isActiveStatus(initialStatus);
    const [pending, setPending] = useState(false);
    const [polling, setPolling] = useState(initiallyActive);
    const [status, setStatus] = useState<RunBackupStatus>(initiallyActive ? initialStatus : 'idle');
    const [error, setError] = useState<string | null>(null);
    const inFlight = useRef(false);
    const pollingRef = useRef(initiallyActive);
    const pollTimer = useRef<number | null>(null);
    const pollDeadline = useRef<number | null>(null);
    const pollAbort = useRef<AbortController | null>(null);

    const clearPolling = (): void => {
        pollingRef.current = false;
        pollAbort.current?.abort();
        pollAbort.current = null;

        if (pollTimer.current !== null) {
            window.clearTimeout(pollTimer.current);
            pollTimer.current = null;
        }

        if (pollDeadline.current !== null) {
            window.clearTimeout(pollDeadline.current);
            pollDeadline.current = null;
        }
    };

    const applyStatus = (next: RunBackupStatus): void => {
        setStatus(next);

        if (!TERMINAL_STATUSES.has(next)) {
            return;
        }

        clearPolling();
        setPolling(false);
        setError(terminalError(next));

        if (next === 'succeeded') {
            reloadCurrentPage({ only: TABLE_PROPS });
        }
    };

    const schedulePoll = (): void => {
        if (!pollingRef.current) {
            return;
        }

        pollTimer.current = window.setTimeout(() => {
            pollTimer.current = null;

            if (!pollingRef.current) {
                return;
            }

            const controller = new AbortController();
            pollAbort.current = controller;

            void requestJson('/backups/run-status', {
                signal: AbortSignal.any([
                    controller.signal,
                    AbortSignal.timeout(REQUEST_TIMEOUT_MS),
                ]),
            })
                .then((payload: unknown) => {
                    if (!isRunStatusData(payload)) {
                        throw new Error('Invalid backup run status response.');
                    }

                    if (!controller.signal.aborted) {
                        applyStatus(payload.status);
                    }
                })
                .catch((cause: unknown) => {
                    if (controller.signal.aborted || !pollingRef.current) {
                        return;
                    }

                    if (
                        cause instanceof HttpRequestError &&
                        [401, 403, 419].includes(cause.status)
                    ) {
                        clearPolling();
                        setPolling(false);
                        setStatus('failed');
                        setError(messageFor(cause));

                        return;
                    }

                    setError('Backup status could not be refreshed. Retrying…');
                })
                .finally(() => {
                    if (pollAbort.current === controller) {
                        pollAbort.current = null;
                    }

                    if (pollingRef.current) {
                        schedulePoll();
                    }
                });
        }, POLL_INTERVAL_MS);
    };

    const startPolling = (): void => {
        clearPolling();
        pollingRef.current = true;
        setPolling(true);
        pollDeadline.current = window.setTimeout(() => {
            clearPolling();
            setPolling(false);
            setStatus('failed');
            setError('The backup is still queued or running. Refresh later to check its status.');
        }, POLL_TIMEOUT_MS);
        schedulePoll();
    };

    const resumePolling = useEffectEvent(() => {
        if (initiallyActive) {
            startPolling();
        }
    });

    useEffect(() => {
        resumePolling();
    }, []);

    useEffect(() => {
        return () => {
            pollingRef.current = false;
            pollAbort.current?.abort();

            if (pollTimer.current !== null) {
                window.clearTimeout(pollTimer.current);
            }

            if (pollDeadline.current !== null) {
                window.clearTimeout(pollDeadline.current);
            }
        };
    }, []);

    const run = async (): Promise<boolean> => {
        if (inFlight.current || pollingRef.current) {
            return false;
        }

        inFlight.current = true;
        setPending(true);
        setError(null);

        try {
            await requestJson('/backups/run', {
                method: 'POST',
                signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
            });
            setStatus('queued');
            startPolling();
            reloadCurrentPage({ only: TABLE_PROPS });

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
        clearPolling();
        setPolling(false);
        setStatus('idle');
        setError(null);
    };

    return { run, pending, polling, status, error, reset };
}
