import { computed, type ComputedRef } from 'vue';
import { useMutation, useQuery, useQueryCache } from '@pinia/colada';
import { xsrfToken } from '@/lib/http';

/**
 * The Users module's only network boundary.
 *
 * Invariant 1: pages never call `fetch`; they declare filters and call this
 * composable. The list endpoint and the DataTable share `lib/table.ts`, so the
 * query that fills the table and the export that reuses its URL cannot drift.
 *
 * Reads go through Pinia Colada. Writes invalidate the `['users']` prefix so
 * every cached page/filter combination refetches, instead of a local row
 * mutation that leaves a different page stale.
 */

export type ManagedUser = Modules.Users.Data.ManagedUserData;

export interface UserListPayload {
    rows: ManagedUser[];
    total: number;
    page: number;
    per_page: number;
}

const ENDPOINT = '/users';

export class UserRequestError extends Error {
    constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
        this.name = 'UserRequestError';
    }
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const isUserListPayload = (value: unknown): value is UserListPayload => {
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
        return 'That user no longer exists.';
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

        throw new UserRequestError(messageFrom(payload, response.status), response.status);
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

export async function fetchUsers(
    params: URLSearchParams,
    signal?: AbortSignal,
): Promise<UserListPayload> {
    const query = params.toString();
    const url = query === '' ? ENDPOINT : `${ENDPOINT}?${query}`;
    const payload = await requestJson<unknown>(url, { signal });

    if (!isUserListPayload(payload)) {
        throw new UserRequestError('The server returned an unexpected response.', 500);
    }

    return payload;
}

/**
 * The list query. `initial` is the page Inertia already rendered, seeded into
 * the exact cache key for the current filters; the first paint therefore shows
 * data, and only a filter change triggers a new request. Seeding never
 * overwrites a populated entry: an Inertia write invalidates the `['users']`
 * prefix, so a newer cache value is not clobbered by older history props.
 */
export function useUsersQuery(params: ComputedRef<URLSearchParams>, initial?: UserListPayload) {
    const queryCache = useQueryCache();
    const key = (): string[] => ['users', 'list', params.value.toString()];

    if (initial !== undefined && queryCache.getQueryData<UserListPayload>(key()) === undefined) {
        queryCache.setQueryData<UserListPayload>(key(), initial);
    }

    return useQuery({
        key,
        query: ({ signal }) => fetchUsers(params.value, signal),
        staleTime: 30_000,
        gcTime: 300_000,
        placeholderData: (previous) => previous,
    });
}

/**
 * Marks every cached Users list stale after an Inertia write (create, profile
 * update, access assignment). Those writes redirect instead of going through a
 * Colada mutation, so without this the browser-back cache could show the old
 * row for the rest of `staleTime`.
 */
export function useUserListInvalidation(): () => Promise<unknown> {
    const queryCache = useQueryCache();

    return () => queryCache.invalidateQueries({ key: ['users'] });
}

export function useUserMutations() {
    const queryCache = useQueryCache();

    const invalidate = async (): Promise<void> => {
        await queryCache.invalidateQueries({ key: ['users'] });
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

    return {
        deleteUser: remove.mutateAsync,
        restoreUser: restore.mutateAsync,
        bulkDelete: bulkRemove.mutateAsync,
        bulkRestore: bulkRestore.mutateAsync,
        busy: computed(
            () =>
                remove.isLoading.value ||
                restore.isLoading.value ||
                bulkRemove.isLoading.value ||
                bulkRestore.isLoading.value,
        ),
    };
}
