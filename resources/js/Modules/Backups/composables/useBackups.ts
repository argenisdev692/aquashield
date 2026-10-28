import { computed, type ComputedRef } from 'vue';
import { useMutation, useQuery, useQueryCache } from '@pinia/colada';
import { xsrfToken } from '@/lib/http';

/**
 * The Backups module's only network boundary (frontend invariant 1).
 *
 * Reads are Colada queries; writes invalidate the `['backups']` prefix so the
 * list and the detail page cannot disagree after a quarantine/restore. The
 * pages never import this module's URL strings.
 */

export type BackupArchive = Modules.Backups.Data.BackupArchiveData;
export type BackupActivity = Modules.Backups.Data.BackupActivityData;

export interface BackupListPayload {
    rows: BackupArchive[];
    total: number;
    page: number;
    per_page: number;
}

export interface BackupShowPayload {
    backup: BackupArchive;
    activities: BackupActivity[];
}

export interface BackupAbilities {
    create: boolean;
    delete: boolean;
    bulk_delete: boolean;
    restore: boolean;
    bulk_restore: boolean;
    download: boolean;
    export: boolean;
    view: boolean;
}

const ENDPOINT = '/backups';

export class BackupRequestError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
        this.name = 'BackupRequestError';
    }
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const isBackupListPayload = (value: unknown): value is BackupListPayload => {
    if (!isRecord(value)) {
        return false;
    }

    return (
        Array.isArray(value.rows) &&
        typeof value.total === 'number' &&
        typeof value.page === 'number' &&
        typeof value.per_page === 'number'
    );
};

const isBackupShowPayload = (value: unknown): value is BackupShowPayload =>
    isRecord(value) && isRecord(value.backup) && Array.isArray(value.activities);

function messageFrom(payload: unknown, status: number): string {
    if (isRecord(payload)) {
        const errors = payload.errors;

        if (isRecord(errors)) {
            for (const value of Object.values(errors)) {
                if (Array.isArray(value) && typeof value[0] === 'string') {
                    return value[0];
                }

                if (typeof value === 'string' && value !== '') {
                    return value;
                }
            }
        }

        if (typeof payload.message === 'string' && payload.message !== '') {
            return payload.message;
        }
    }

    if (status === 401 || status === 419) {
        return 'Your session expired. Reload the page and try again.';
    }

    if (status === 403) {
        return 'You do not have permission to do that.';
    }

    if (status === 404) {
        return 'That backup no longer exists. Refresh the list.';
    }

    if (status === 422) {
        return 'The selected backup can no longer be changed. Refresh the list and try again.';
    }

    if (status === 429) {
        return 'Too many requests. Wait a moment and try again.';
    }

    return 'The request failed. Try again.';
}

async function request(url: string, init: RequestInit = {}): Promise<Response> {
    const method = (init.method ?? 'GET').toUpperCase();
    const headers = new Headers(init.headers);

    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    if (method !== 'GET') {
        headers.set('X-XSRF-TOKEN', xsrfToken());
    }

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...init,
        headers,
    });

    if (!response.ok) {
        const payload: unknown = await response.json().catch(() => null);

        throw new BackupRequestError(messageFrom(payload, response.status), response.status);
    }

    return response;
}

async function requestJson<T>(url: string, init: RequestInit = {}): Promise<T> {
    const response = await request(url, init);
    const payload: unknown = await response.json().catch(() => null);

    return payload as T;
}

const requestNoContent = async (url: string, init: RequestInit = {}): Promise<void> => {
    await request(url, init);
};

export async function fetchBackups(
    params: URLSearchParams,
    signal?: AbortSignal,
): Promise<BackupListPayload> {
    const query = params.toString();
    const url = query === '' ? ENDPOINT : `${ENDPOINT}?${query}`;
    const payload = await requestJson<unknown>(url, { signal });

    if (!isBackupListPayload(payload)) {
        throw new BackupRequestError('The server returned an unexpected response.', 500);
    }

    return payload;
}

export async function fetchBackup(uuid: string, signal?: AbortSignal): Promise<BackupShowPayload> {
    const payload = await requestJson<unknown>(`${ENDPOINT}/${encodeURIComponent(uuid)}`, {
        signal,
    });

    if (!isBackupShowPayload(payload)) {
        throw new BackupRequestError('The server returned an unexpected response.', 500);
    }

    return payload;
}

export function useBackupsQuery(params: ComputedRef<URLSearchParams>, initial?: BackupListPayload) {
    const queryCache = useQueryCache();
    const key = (): string[] => ['backups', 'list', params.value.toString()];

    if (initial !== undefined && queryCache.getQueryData<BackupListPayload>(key()) === undefined) {
        queryCache.setQueryData<BackupListPayload>(key(), initial);
    }

    return useQuery({
        key,
        query: ({ signal }) => fetchBackups(params.value, signal),
        staleTime: 30_000,
        gcTime: 300_000,
        placeholderData: (previous) => previous,
    });
}

export function useBackupQuery(uuid: string, initial?: BackupShowPayload) {
    const queryCache = useQueryCache();
    const key = (): string[] => ['backups', 'detail', uuid];

    if (initial !== undefined && queryCache.getQueryData<BackupShowPayload>(key()) === undefined) {
        queryCache.setQueryData<BackupShowPayload>(key(), initial);
    }

    return useQuery({
        key,
        query: ({ signal }) => fetchBackup(uuid, signal),
        staleTime: 30_000,
        gcTime: 300_000,
        placeholderData: (previous) => previous,
    });
}

export function useBackupMutations() {
    const queryCache = useQueryCache();

    const invalidate = async (): Promise<void> => {
        await queryCache.invalidateQueries({ key: ['backups'] });
    };

    const remove = useMutation({
        mutation: (uuid: string) => requestNoContent(`${ENDPOINT}/${uuid}`, { method: 'DELETE' }),
        onSettled: invalidate,
    });

    const restore = useMutation({
        mutation: (uuid: string) =>
            requestNoContent(`${ENDPOINT}/${uuid}/restore`, { method: 'PATCH' }),
        onSettled: invalidate,
    });

    const bulkRemove = useMutation({
        mutation: (uuids: string[]) =>
            requestNoContent(`${ENDPOINT}/bulk-delete`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ uuids }),
            }),
        onSettled: invalidate,
    });

    const bulkRestore = useMutation({
        mutation: (uuids: string[]) =>
            requestNoContent(`${ENDPOINT}/bulk-restore`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ uuids }),
            }),
        onSettled: invalidate,
    });

    const run = useMutation({
        mutation: () => requestJson<{ status: string }>(`${ENDPOINT}/run`, { method: 'POST' }),
        onSettled: invalidate,
    });

    return {
        deleteBackup: remove.mutateAsync,
        restoreBackup: restore.mutateAsync,
        bulkDelete: bulkRemove.mutateAsync,
        bulkRestore: bulkRestore.mutateAsync,
        runBackup: run.mutateAsync,
        busy: computed(
            () =>
                remove.isLoading.value ||
                restore.isLoading.value ||
                bulkRemove.isLoading.value ||
                bulkRestore.isLoading.value ||
                run.isLoading.value,
        ),
    };
}

export function downloadBackupUrl(uuid: string): string {
    return `${ENDPOINT}/${encodeURIComponent(uuid)}/download`;
}
