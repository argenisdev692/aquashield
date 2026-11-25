// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, cleanup, renderHook, waitFor } from '@testing-library/react';

/**
 * The CRUD controller is the one place where URL state, the server's echoed
 * `perPage` and the Inertia visit meet. A regression here silently changes
 * which records the screen *and* the export describe, so every setter is
 * asserted on the URL it produces, not on local state.
 */
const { visit, reload, requestJson } = vi.hoisted(() => ({
    visit: vi.fn<
        (
            url: string,
            options?: { replace?: boolean; preserveState?: boolean; onFinish?: () => void },
        ) => void
    >(),
    reload: vi.fn<(options?: unknown) => void>(),
    requestJson: vi.fn<(path: string, options?: unknown) => Promise<unknown>>(),
}));

const page: { url: string; props: { perPage?: number } } = { url: '/users', props: {} };

vi.mock('@inertiajs/react', () => ({
    usePage: () => page,
    router: { visit, reload },
}));

vi.mock('@/lib/http', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@/lib/http')>();

    return { ...actual, requestJson };
});

import { useBulkActions, useCrudTable } from './crud-table';

const lastUrl = (): URL => new URL(String(visit.mock.calls.at(-1)?.[0]), 'http://localhost');
const lastOptions = (): {
    replace?: boolean;
    preserveState?: boolean;
    onFinish?: () => void;
} =>
    (visit.mock.calls.at(-1)?.[1] ?? {}) as {
        replace?: boolean;
        preserveState?: boolean;
        onFinish?: () => void;
    };

beforeEach(() => {
    visit.mockReset();
    reload.mockReset();
    requestJson.mockReset();
    requestJson.mockResolvedValue(null);
    page.url = '/users';
    page.props = {};
});

afterEach(() => {
    cleanup();
});

describe('useCrudTable', () => {
    it('seeds the page size from the server prop, not from a client default', () => {
        page.props = { perPage: 25 };

        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        expect(result.current.state.perPage).toBe(25);
        expect(result.current.kitProps.preferencesKey).toBe('aq:table:users');
    });

    it('reads page, search, sort and filters back from the URL', () => {
        page.url =
            '/users?page=3&per_page=50&search=acme&sort=-created_at&filter[status]=active&filter[role][]=admin';

        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        expect(result.current.state).toEqual({
            page: 3,
            perPage: 50,
            search: 'acme',
            sort: { field: 'created_at', order: -1 },
            filters: { status: 'active', role: ['admin'] },
        });
    });

    it('keeps the whole state when one filter changes', () => {
        page.url = '/users?page=3&search=acme&filter[status]=active';

        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        act(() => {
            result.current.setFilters({ role: ['admin'] });
        });

        const url = lastUrl();

        expect(url.searchParams.get('filter[status]')).toBe('active');
        expect(url.searchParams.get('filter[role][]')).toBe('admin');
        expect(url.searchParams.get('search')).toBe('acme');
        // A filter change always returns to the first page.
        expect(url.searchParams.has('page')).toBe(false);
        expect(lastOptions().preserveState).toBe(true);
    });

    it('composes two commits made before the server responds', () => {
        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        act(() => {
            result.current.setFilters({ status: 'active' });
            result.current.setSearch('acme');
        });

        const url = lastUrl();

        expect(url.searchParams.get('filter[status]')).toBe('active');
        expect(url.searchParams.get('search')).toBe('acme');
        expect(url.searchParams.has('page')).toBe(false);
    });

    it('tracks only the visits it started and clears pending on finish', () => {
        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        expect(result.current.pending).toBe(false);

        act(() => {
            result.current.setFilters({ status: 'active' });
        });

        expect(result.current.pending).toBe(true);

        act(() => {
            lastOptions().onFinish?.();
        });

        expect(result.current.pending).toBe(false);
    });

    it('removes a filter when its value is blank', () => {
        page.url = '/users?filter[status]=active';

        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        act(() => {
            result.current.setFilter('status', '');
        });

        expect(lastUrl().searchParams.has('filter[status]')).toBe(false);
    });

    it('writes the search with replace so history has one entry, not one per keystroke', () => {
        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        act(() => {
            result.current.setSearch('  acme  ');
        });

        expect(lastUrl().searchParams.get('search')).toBe('acme');
        expect(lastOptions().replace).toBe(true);
        expect(lastOptions().preserveState).toBe(true);
    });

    it('does not visit when the trimmed search is unchanged', () => {
        page.url = '/users?search=acme';

        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        act(() => {
            result.current.setSearch(' acme ');
        });

        expect(visit).not.toHaveBeenCalled();
    });

    it('maps a sort to the Spatie minus prefix and returns to page 1', () => {
        page.url = '/users?page=4';

        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        act(() => {
            result.current.setSort({ field: 'name', order: -1 });
        });

        expect(lastUrl().searchParams.get('sort')).toBe('-name');
        expect(lastUrl().searchParams.has('page')).toBe(false);
    });

    it('maps the kit sort shape back to order -1/1', () => {
        page.url = '/users?sort=-created_at';

        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        expect(result.current.kitProps.sort).toEqual({ field: 'created_at', direction: 'desc' });

        act(() => {
            result.current.kitProps.setSort({ field: 'name', direction: 'asc' });
        });

        expect(lastUrl().searchParams.get('sort')).toBe('name');
    });

    it('clamps the page size and starts again at page 1', () => {
        page.url = '/users?page=5';

        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        act(() => {
            result.current.setPerPage(500);
        });

        expect(lastUrl().searchParams.get('per_page')).toBe('100');
        expect(lastUrl().searchParams.has('page')).toBe(false);
    });

    it('resets to the bare endpoint with replace', () => {
        page.url = '/users?page=2&search=acme&filter[status]=active';

        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        act(() => {
            result.current.reset();
        });

        expect(visit.mock.calls.at(-1)?.[0]).toBe('/users');
        expect(lastOptions().replace).toBe(true);
    });

    it('builds the export URL from the same state without pagination', () => {
        page.url = '/users?page=3&per_page=50&search=acme&filter[status]=active';

        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        const url = new URL(result.current.exportUrl('csv'), 'http://localhost');

        expect(url.pathname).toBe('/users/export/csv');
        expect(url.searchParams.get('search')).toBe('acme');
        expect(url.searchParams.get('filter[status]')).toBe('active');
        expect(url.searchParams.has('per_page')).toBe(false);
        expect(url.searchParams.has('page')).toBe(false);
    });

    it('applies a saved view and always starts at page 1', () => {
        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        act(() => {
            result.current.apply({
                search: 'boiler',
                perPage: 50,
                sort: { field: 'name', order: 1 },
                filters: { status: 'active' },
            });
        });

        const url = lastUrl();

        expect(url.searchParams.get('search')).toBe('boiler');
        expect(url.searchParams.get('per_page')).toBe('50');
        expect(url.searchParams.get('sort')).toBe('name');
        expect(url.searchParams.get('filter[status]')).toBe('active');
        expect(url.searchParams.has('page')).toBe(false);
    });

    it('counts active filters and a non-empty search', () => {
        page.url = '/users?search=acme&filter[status]=active&filter[role][]=admin&filter[empty]=';

        const { result } = renderHook(() => useCrudTable({ endpoint: '/users' }));

        expect(result.current.activeFilterCount).toBe(2);
        expect(result.current.hasActiveFilters).toBe(true);
    });
});

describe('useBulkActions', () => {
    const rows = [{ uuid: 'a' }, { uuid: 'b' }];

    it('posts the selected ids and reloads only the table props', async () => {
        const { result } = renderHook(() => useBulkActions({ deleteUrl: '/users/bulk-delete' }));

        act(() => {
            result.current.deleteRows(rows);
        });

        await waitFor(() => expect(reload).toHaveBeenCalledTimes(1));

        expect(requestJson).toHaveBeenCalledWith('/users/bulk-delete', {
            method: 'POST',
            body: { uuids: ['a', 'b'] },
        });
        expect(reload.mock.calls[0][0]).toEqual({
            only: ['rows', 'total', 'currentPage', 'perPage', 'filters'],
        });
    });

    it('surfaces the error and clears processing', async () => {
        requestJson.mockRejectedValueOnce(new Error('Network is down'));

        const { result } = renderHook(() => useBulkActions({ restoreUrl: '/users/bulk-restore' }));

        act(() => {
            result.current.restoreRows([{ uuid: 'a' }]);
        });

        await waitFor(() => expect(result.current.error).toBe('Network is down'));
        expect(result.current.processing).toBe(false);
        expect(reload).not.toHaveBeenCalled();
    });

    it('does nothing without a URL or a selection', async () => {
        const { result } = renderHook(() => useBulkActions({}));

        act(() => {
            result.current.deleteRows([{ uuid: 'a' }]);
            result.current.restoreRows([]);
        });

        await Promise.resolve();

        expect(requestJson).not.toHaveBeenCalled();
    });
});
