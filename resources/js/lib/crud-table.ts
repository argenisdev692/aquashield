/**
 * The CRUD table controller: one hook for every list screen.
 *
 * The URL is the single source of truth. The hook parses it on every render,
 * so reload, browser back/forward, a pasted link and a saved view all resolve
 * to the same table without an effect copying server props into local state.
 *
 * Every setter writes the whole state through `buildTableUrl`, which is what
 * keeps `search`, `sort`, `filter[...]`, `page` and `per_page` from erasing
 * each other. `exportUrl` reuses the same serialiser with `paginate: false`,
 * so an export always covers exactly what the screen shows.
 */
import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { requestJson } from '@/lib/http';
import {
    buildTableUrl,
    countActiveFilters,
    isBlankFilter,
    parseTableParams,
    type TableFilters,
    type TableFilterValue,
    type TableState,
    type TableSort,
} from '@/lib/table';
import { tablePreferencesKey } from '@/lib/table-preferences';

export interface CrudTableOptions {
    /** The list endpoint, e.g. `/users`. Also the prefix of its exports. */
    endpoint: string;
    defaults?: {
        perPage?: number;
        sort?: TableSort | null;
    };
}

/** The sort shape `DataTableKit` speaks (`asc`/`desc`, not `1`/`-1`). */
export interface CrudTableSort {
    field: string;
    direction: 'asc' | 'desc';
}

export interface CrudTable {
    /** The current state, read from the URL. Feed it to `CrudFilters`. */
    state: TableState;
    activeFilterCount: number;
    hasActiveFilters: boolean;
    /** Merge one filter; a blank value removes the key. Resets to page 1. */
    setFilter: (key: string, value: TableFilterValue) => void;
    /** Merge several filters atomically (date ranges, presets). Resets to page 1. */
    setFilters: (patch: TableFilters) => void;
    setSearch: (value: string) => void;
    setPage: (page: number) => void;
    setPerPage: (perPage: number) => void;
    setSort: (sort: TableSort | null) => void;
    /** Replace the whole view (used by a saved view). Always starts at page 1. */
    apply: (state: Partial<Omit<TableState, 'page'>>) => void;
    reset: () => void;
    /** `exportUrl('csv')` → the current filters, without pagination. */
    exportUrl: (format: string) => string;
    /** Drop-in props for `DataTableKit`. */
    kitProps: {
        page: number;
        perPage: number;
        search: string;
        sort: CrudTableSort | null;
        setPage: (page: number) => void;
        setPerPage: (perPage: number) => void;
        setSearch: (value: string) => void;
        setSort: (sort: CrudTableSort | null) => void;
        /** localStorage namespace for column visibility and density. */
        preferencesKey: string;
    };
}

const MAX_PER_PAGE = 100;

export function useCrudTable({ endpoint, defaults }: CrudTableOptions): CrudTable {
    const { url, props: pageProps } = usePage<{ perPage?: number }>();
    /*
     * The server is the authority on the page size it actually used. If it
     * echoes `perPage`, that beats every client default; otherwise the page
     * default applies. The URL still wins over both when `per_page` is present.
     */
    const serverPerPage = typeof pageProps.perPage === 'number' ? pageProps.perPage : undefined;
    const defaultPerPage = Math.min(
        MAX_PER_PAGE,
        Math.max(1, serverPerPage ?? defaults?.perPage ?? 15),
    );
    const defaultSortField = defaults?.sort?.field ?? null;
    const defaultSortOrder = defaults?.sort?.order ?? null;

    const state = parseTableParams(url, {
        perPage: defaultPerPage,
        sort:
            defaultSortField === null || defaultSortOrder === null
                ? null
                : {
                      field: defaultSortField,
                      order: defaultSortOrder === -1 ? -1 : 1,
                  },
    });

    const visit = (next: TableState, replace = false): void => {
        router.visit(buildTableUrl(endpoint, next), {
            preserveScroll: true,
            preserveState: true,
            replace,
        });
    };

    const commit = (patch: Partial<TableState>, replace = false): void => {
        visit({ ...state, ...patch }, replace);
    };

    const setFilters = (patch: TableFilters): void => {
        const filters: TableFilters = { ...state.filters };

        for (const [key, value] of Object.entries(patch)) {
            if (isBlankFilter(value)) {
                delete filters[key];

                continue;
            }

            filters[key] = value;
        }

        commit({ filters, page: 1 });
    };

    const setFilter = (key: string, value: TableFilterValue): void => {
        setFilters({ [key]: value });
    };

    const setSearch = (value: string): void => {
        const search = value.trim();

        if (search === state.search) {
            return;
        }

        // Debounced by the table input; `replace` keeps history free of one
        // entry per paused keystroke while back still restores the last visit.
        commit({ search, page: 1 }, true);
    };

    const setPage = (page: number): void => {
        commit({ page: Math.max(1, page) });
    };

    const setPerPage = (perPage: number): void => {
        commit({ perPage: Math.min(MAX_PER_PAGE, Math.max(1, perPage)), page: 1 });
    };

    const setSort = (sort: TableSort | null): void => {
        commit({ sort, page: 1 });
    };

    const apply = (next: Partial<Omit<TableState, 'page'>>): void => {
        visit({
            page: 1,
            perPage: Math.min(MAX_PER_PAGE, Math.max(1, next.perPage ?? defaultPerPage)),
            search: (next.search ?? '').trim(),
            sort: next.sort ?? null,
            filters: { ...next.filters },
        });
    };

    const reset = (): void => {
        router.visit(endpoint, { preserveScroll: true, preserveState: true, replace: true });
    };

    const exportUrl = (format: string): string =>
        buildTableUrl(`${endpoint}/export/${format}`, state, { paginate: false });

    const activeFilterCount = countActiveFilters(state.filters);

    return {
        state,
        activeFilterCount,
        hasActiveFilters: activeFilterCount > 0 || state.search.trim() !== '',
        setFilter,
        setFilters,
        setSearch,
        setPage,
        setPerPage,
        setSort,
        apply,
        reset,
        exportUrl,
        kitProps: {
            page: state.page,
            perPage: state.perPage,
            search: state.search,
            sort:
                state.sort === null
                    ? null
                    : {
                          field: state.sort.field,
                          direction: state.sort.order === -1 ? 'desc' : 'asc',
                      },
            setPage,
            setPerPage,
            setSearch,
            setSort: (sort: CrudTableSort | null): void => {
                setSort(
                    sort === null
                        ? null
                        : { field: sort.field, order: sort.direction === 'desc' ? -1 : 1 },
                );
            },
            preferencesKey: tablePreferencesKey(endpoint),
        },
    };
}

export interface CrudBulkActionsOptions {
    deleteUrl?: string;
    restoreUrl?: string;
    /** Runs after the partial reload, e.g. to invalidate a client-owned panel. */
    onSettled?: () => void;
}

export interface CrudBulkActions {
    processing: boolean;
    error: string | null;
    deleteRows: (rows: readonly { uuid: string }[]) => void;
    restoreRows: (rows: readonly { uuid: string }[]) => void;
}

/**
 * Bulk delete/restore over a JSON endpoint, followed by an Inertia partial
 * reload of the current URL. The endpoint already answers 204 to a JSON
 * request, so this path never fights Inertia's redirect contract.
 */
export function useBulkActions({
    deleteUrl,
    restoreUrl,
    onSettled,
}: CrudBulkActionsOptions): CrudBulkActions {
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const run = async (
        url: string | undefined,
        rows: readonly { uuid: string }[],
    ): Promise<void> => {
        if (url === undefined || rows.length === 0) {
            return;
        }

        setProcessing(true);
        setError(null);

        try {
            await requestJson(url, {
                method: 'POST',
                body: { uuids: rows.map((row) => row.uuid) },
            });

            // Reload the props the table renders; the URL (and therefore the
            // active filters) stays exactly where the user left it.
            router.reload({
                only: ['rows', 'total', 'currentPage', 'perPage', 'filters'],
            });

            onSettled?.();
        } catch (cause) {
            setError(
                cause instanceof Error
                    ? cause.message
                    : 'The bulk action could not be completed. Try again.',
            );
        } finally {
            setProcessing(false);
        }
    };

    return {
        processing,
        error,
        deleteRows: (rows) => {
            void run(deleteUrl, rows);
        },
        restoreRows: (rows) => {
            void run(restoreUrl, rows);
        },
    };
}
