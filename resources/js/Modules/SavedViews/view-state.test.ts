import { describe, expect, it } from 'vitest';
import type { TableState } from '@/lib/table';
import type { SavedView } from './hooks/useSavedViews';
import { isViewActive, viewToState } from './view-state';

const state = (overrides: Partial<TableState> = {}): TableState => ({
    page: 1,
    perPage: 15,
    search: '',
    sort: { field: 'created_at', order: -1 },
    filters: {},
    ...overrides,
});

const view = (overrides: Partial<SavedView> = {}): SavedView => ({
    uuid: 'view-1',
    resource: 'users',
    name: 'Weekly review',
    search: null,
    sort_field: 'created_at',
    sort_order: -1,
    per_page: 15,
    filters: {},
    ...overrides,
});

describe('isViewActive', () => {
    it('matches when search, filters, sort and page size all agree', () => {
        expect(
            isViewActive(
                view({ search: 'ada', filters: { status: 'active' } }),
                state({ search: 'ada', filters: { status: 'active' } }),
            ),
        ).toBe(true);
    });

    it('does not match when the sort differs', () => {
        expect(
            isViewActive(view({ sort_field: 'created_at', sort_order: -1 }), state({ sort: null })),
        ).toBe(false);
        expect(
            isViewActive(
                view({ sort_field: 'created_at', sort_order: 1 }),
                state({ sort: { field: 'created_at', order: -1 } }),
            ),
        ).toBe(false);
    });

    it('does not match when the page size differs', () => {
        expect(isViewActive(view({ per_page: 25 }), state({ perPage: 15 }))).toBe(false);
    });

    it('treats a stored null sort as "no sort"', () => {
        expect(isViewActive(view({ sort_field: null }), state({ sort: null }))).toBe(true);
        expect(isViewActive(view({ sort_field: null }), state())).toBe(false);
    });

    it('normalises filter values before comparing', () => {
        expect(
            isViewActive(
                view({ filters: { status: ' active ', roles: ['ADMIN', 'STAFF'] } }),
                state({ filters: { status: 'active', roles: ['ADMIN', 'STAFF'] } }),
            ),
        ).toBe(true);
    });

    it('ignores a blank stored search and a blank live search', () => {
        expect(isViewActive(view({ search: null }), state({ search: '   ' }))).toBe(true);
    });
});

describe('viewToState', () => {
    it('maps the stored view back onto table state', () => {
        expect(
            viewToState(
                view({
                    search: 'ada',
                    sort_field: 'email',
                    sort_order: 1,
                    per_page: 50,
                    filters: { status: 'all' },
                }),
            ),
        ).toEqual({
            search: 'ada',
            perPage: 50,
            sort: { field: 'email', order: 1 },
            filters: { status: 'all' },
        });
    });

    it('maps a null sort to no sort', () => {
        expect(viewToState(view({ sort_field: null })).sort).toBeNull();
    });
});
