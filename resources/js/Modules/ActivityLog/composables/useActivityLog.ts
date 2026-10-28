import { computed, type ComputedRef } from 'vue';
import { useQuery, useQueryCache } from '@pinia/colada';

/**
 * Activity log read boundary. There are no mutations on purpose: an audit
 * trail the panel can rewrite is not an audit trail.
 */

export type ActivityEntry = Modules.ActivityLog.Data.ActivityEntryData;

export interface ActivityListPayload {
    rows: ActivityEntry[];
    total: number;
    page: number;
    per_page: number;
}

export interface ActivityShowPayload {
    activity: ActivityEntry;
}

const ENDPOINT = '/activity';

export class ActivityRequestError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
        this.name = 'ActivityRequestError';
    }
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const isActivityListPayload = (value: unknown): value is ActivityListPayload => {
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

const isActivityShowPayload = (value: unknown): value is ActivityShowPayload =>
    isRecord(value) && isRecord(value.activity);

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
        return 'You do not have permission to read the audit trail.';
    }

    if (status === 404) {
        return 'That audit entry no longer exists.';
    }

    if (status === 429) {
        return 'Too many requests. Wait a moment and try again.';
    }

    return 'The request failed. Try again.';
}

async function request<T>(url: string, signal?: AbortSignal): Promise<T> {
    const headers = new Headers();
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');

    const response = await fetch(url, {
        credentials: 'same-origin',
        signal,
        headers,
    });

    if (!response.ok) {
        const payload: unknown = await response.json().catch(() => null);

        throw new ActivityRequestError(messageFrom(payload, response.status), response.status);
    }

    const payload: unknown = await response.json().catch(() => null);

    return payload as T;
}

export async function fetchActivity(
    params: URLSearchParams,
    signal?: AbortSignal,
): Promise<ActivityListPayload> {
    const query = params.toString();
    const url = query === '' ? ENDPOINT : `${ENDPOINT}?${query}`;
    const payload = await request<unknown>(url, signal);

    if (!isActivityListPayload(payload)) {
        throw new ActivityRequestError('The server returned an unexpected response.', 500);
    }

    return payload;
}

export async function fetchActivityEntry(
    uuid: string,
    signal?: AbortSignal,
): Promise<ActivityShowPayload> {
    const payload = await request<unknown>(`${ENDPOINT}/${encodeURIComponent(uuid)}`, signal);

    if (!isActivityShowPayload(payload)) {
        throw new ActivityRequestError('The server returned an unexpected response.', 500);
    }

    return payload;
}

export function useActivityQuery(
    params: ComputedRef<URLSearchParams>,
    initial?: ActivityListPayload,
) {
    const queryCache = useQueryCache();
    const key = (): string[] => ['activity', 'list', params.value.toString()];

    if (
        initial !== undefined &&
        queryCache.getQueryData<ActivityListPayload>(key()) === undefined
    ) {
        queryCache.setQueryData<ActivityListPayload>(key(), initial);
    }

    return useQuery({
        key,
        query: ({ signal }) => fetchActivity(params.value, signal),
        staleTime: 30_000,
        gcTime: 300_000,
        placeholderData: (previous) => previous,
    });
}

export function useActivityEntryQuery(uuid: string, initial?: ActivityShowPayload) {
    const queryCache = useQueryCache();
    const key = (): string[] => ['activity', 'detail', uuid];

    if (
        initial !== undefined &&
        queryCache.getQueryData<ActivityShowPayload>(key()) === undefined
    ) {
        queryCache.setQueryData<ActivityShowPayload>(key(), initial);
    }

    return useQuery({
        key,
        query: ({ signal }) => fetchActivityEntry(uuid, signal),
        staleTime: 30_000,
        gcTime: 300_000,
        placeholderData: (previous) => previous,
    });
}
