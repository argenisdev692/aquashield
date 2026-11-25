/**
 * Saved views — the client boundary for the server-side collection.
 *
 * A view is user-owned data that follows the account, not the browser:
 * `localStorage` is readable by any script on the origin, is wiped by clearing
 * site data and cannot be shared across devices. The server stores the view,
 * checks ownership on every read/write and validates the filter keys.
 *
 * TanStack Query owns this small collection because it outlives a table visit
 * (save/apply/delete does not require reloading the list). The list itself
 * still comes from Inertia.
 */
import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { requestJson } from '@/lib/http';
import type { TableFilters, TableState } from '@/lib/table';

export type SavedView = App.Data.SavedViewData;

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const isFilterMap = (value: unknown): value is Record<string, string | string[]> =>
    isRecord(value) &&
    Object.values(value).every(
        (item) =>
            typeof item === 'string' ||
            (Array.isArray(item) && item.every((entry) => typeof entry === 'string')),
    );

const isSavedView = (value: unknown): value is SavedView =>
    isRecord(value) &&
    typeof value.uuid === 'string' &&
    typeof value.resource === 'string' &&
    typeof value.name === 'string' &&
    (value.search === null || typeof value.search === 'string') &&
    (value.sort_field === null || typeof value.sort_field === 'string') &&
    typeof value.sort_order === 'number' &&
    typeof value.per_page === 'number' &&
    isFilterMap(value.filters);

/** Filter values are stored as text (or a list of text); numbers are coerced. */
const serializeFilters = (filters: TableFilters): Record<string, string | string[]> => {
    const serialized: Record<string, string | string[]> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (typeof value === 'string') {
            if (value !== '') {
                serialized[key] = value;
            }

            continue;
        }

        if (typeof value === 'number') {
            serialized[key] = String(value);

            continue;
        }

        if (Array.isArray(value) && value.length > 0) {
            serialized[key] = value.map(String);
        }
    }

    return serialized;
};

const queryOptionsFor = (resource: string) =>
    queryOptions({
        queryKey: ['saved-views', resource] as const,
        queryFn: async ({ signal }): Promise<SavedView[]> => {
            const payload = await requestJson(
                `/saved-views?resource=${encodeURIComponent(resource)}`,
                { signal },
            );

            /*
             * The controller always answers `{ views: [] }`, so a different
             * envelope is a contract violation. Filtering quietly made a view
             * the client could not parse vanish from the menu; the panel owns
             * an error state and a retry, so fail into it.
             */
            if (!isRecord(payload) || !Array.isArray(payload.views)) {
                throw new Error('The saved-views endpoint returned an unexpected payload.');
            }

            const views: SavedView[] = [];

            for (const view of payload.views) {
                if (!isSavedView(view)) {
                    throw new Error(
                        'The saved-views endpoint returned a view this client cannot read.',
                    );
                }

                views.push(view);
            }

            return views;
        },
        staleTime: 60_000,
        gcTime: 300_000,
    });

export interface SavedViewsController {
    views: SavedView[];
    isLoading: boolean;
    isError: boolean;
    retry: () => void;
    save: {
        mutate: (
            input: { name: string; state: TableState },
            options?: { onSuccess?: () => void },
        ) => void;
        isPending: boolean;
        error: string | null;
    };
    remove: {
        mutate: (uuid: string) => void;
        isPending: boolean;
        error: string | null;
    };
}

export function useSavedViews(resource: string): SavedViewsController {
    const queryClient = useQueryClient();
    const list = useQuery(queryOptionsFor(resource));

    const invalidate = (): void => {
        void queryClient.invalidateQueries({ queryKey: ['saved-views', resource] });
    };

    const saveMutation = useMutation({
        mutationFn: (input: { name: string; state: TableState }) =>
            requestJson('/saved-views', {
                method: 'POST',
                body: {
                    resource,
                    name: input.name,
                    query: {
                        search: input.state.search,
                        sort_field: input.state.sort?.field ?? null,
                        sort_order: input.state.sort?.order ?? 1,
                        per_page: input.state.perPage,
                        filters: serializeFilters(input.state.filters),
                    },
                },
            }),
        onSettled: invalidate,
    });

    const removeMutation = useMutation({
        mutationFn: (uuid: string) =>
            requestJson(`/saved-views/${encodeURIComponent(uuid)}`, { method: 'DELETE' }),
        onSettled: invalidate,
    });

    return {
        views: list.data ?? [],
        isLoading: list.isPending,
        isError: list.isError,
        retry: () => {
            void list.refetch();
        },
        save: {
            mutate: (input, options) => {
                saveMutation.mutate(input, options);
            },
            isPending: saveMutation.isPending,
            error: saveMutation.error?.message ?? null,
        },
        remove: {
            mutate: (uuid) => {
                removeMutation.mutate(uuid);
            },
            isPending: removeMutation.isPending,
            error: removeMutation.error?.message ?? null,
        },
    };
}
