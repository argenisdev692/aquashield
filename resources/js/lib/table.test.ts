import { describe, expect, it } from 'vitest';
import {
    buildTableParams,
    buildTableUrl,
    countActiveFilters,
    isBlankFilter,
    parseTableParams,
    type TableState,
} from './table';

/**
 * The table contract is a round trip, not a formatting detail: the URL is the
 * only state, the export reuses the same serialiser, and the server reads
 * `filter[...]` / `-sort` / `per_page` exactly once. A regression here silently
 * changes which records the screen and the CSV describe.
 */
const baseState: TableState = {
    page: 1,
    perPage: 15,
    search: '',
    sort: null,
    filters: {},
};

const urlOf = (state: TableState, options?: { paginate?: boolean }): string =>
    `/users?${buildTableParams(state, options).toString()}`;

describe('buildTableParams', () => {
    it('always sends per_page and only sends page above the first', () => {
        const first = buildTableParams(baseState);

        expect(first.get('per_page')).toBe('15');
        expect(first.has('page')).toBe(false);

        const third = buildTableParams({ ...baseState, page: 3 });

        expect(third.get('page')).toBe('3');
        expect(third.get('per_page')).toBe('15');
    });

    it('drops pagination when the caller asks for an export', () => {
        const params = buildTableParams(
            { ...baseState, page: 4, perPage: 50 },
            { paginate: false },
        );

        expect(params.has('page')).toBe(false);
        expect(params.has('per_page')).toBe(false);
    });

    it('trims the search and omits an empty one', () => {
        expect(buildTableParams({ ...baseState, search: '   ' }).has('search')).toBe(false);
        expect(buildTableParams({ ...baseState, search: '  acme  ' }).get('search')).toBe('acme');
    });

    it('serialises a descending sort with the Spatie minus prefix', () => {
        expect(
            buildTableParams({ ...baseState, sort: { field: 'created_at', order: -1 } }).get(
                'sort',
            ),
        ).toBe('-created_at');
        expect(
            buildTableParams({ ...baseState, sort: { field: 'name', order: 1 } }).get('sort'),
        ).toBe('name');
    });

    it('repeats an array filter and skips every blank shape', () => {
        const params = buildTableParams({
            ...baseState,
            filters: {
                status: 'active',
                role: ['admin', 'dispatcher'],
                empty: '',
                nothing: null,
                flagged: false,
                none: [],
            },
        });

        expect(params.getAll('filter[role][]')).toEqual(['admin', 'dispatcher']);
        expect(params.get('filter[status]')).toBe('active');
        expect(params.has('filter[empty]')).toBe(false);
        expect(params.has('filter[nothing]')).toBe(false);
        expect(params.has('filter[flagged]')).toBe(false);
        expect(params.has('filter[none][]')).toBe(false);
    });
});

describe('parseTableParams', () => {
    it('returns the defaults for a bare URL', () => {
        expect(parseTableParams('/users')).toEqual({
            page: 1,
            perPage: 15,
            search: '',
            sort: null,
            filters: {},
        });
    });

    it('round-trips everything buildTableParams writes', () => {
        const state: TableState = {
            page: 2,
            perPage: 25,
            search: 'a & b',
            sort: { field: 'created_at', order: -1 },
            filters: { status: 'active', role: ['admin', 'dispatcher'] },
        };

        expect(parseTableParams(urlOf(state))).toEqual(state);
    });

    it('clamps per_page instead of trusting the URL', () => {
        expect(parseTableParams('/users?per_page=500').perPage).toBe(100);
        expect(parseTableParams('/users?per_page=0').perPage).toBe(15);
        expect(parseTableParams('/users?per_page=abc').perPage).toBe(15);
        expect(parseTableParams('/users?page=-3').page).toBe(1);
    });

    it('honours the page-specific default sort only when the URL has none', () => {
        const defaults = { sort: { field: 'created_at', order: -1 as const } };

        expect(parseTableParams('/users', defaults).sort).toEqual({
            field: 'created_at',
            order: -1,
        });
        expect(parseTableParams('/users?sort=name', defaults).sort).toEqual({
            field: 'name',
            order: 1,
        });
    });

    it('rejects a sort field that is not a column path', () => {
        expect(parseTableParams('/users?sort=-created_at').sort).toEqual({
            field: 'created_at',
            order: -1,
        });
        expect(parseTableParams('/users?sort=name;drop').sort).toBeNull();
        expect(parseTableParams('/users?sort=').sort).toBeNull();
    });

    it('reads only filter keys and ignores anything else', () => {
        const state = parseTableParams('/users?filter[status]=active&filter[role][]=admin&page=2');

        expect(state.filters).toEqual({ status: 'active', role: ['admin'] });
        expect(state.page).toBe(2);
    });
});

describe('buildTableUrl', () => {
    it('returns the bare endpoint when nothing is serialised', () => {
        expect(buildTableUrl('/users', baseState, { paginate: false })).toBe('/users');
    });

    it('appends with ? or & depending on the endpoint', () => {
        const state: TableState = { ...baseState, search: 'acme', page: 1 };

        expect(buildTableUrl('/users', state)).toContain('/users?');
        expect(buildTableUrl('/users?tab=all', state)).toContain('/users?tab=all&');
        expect(buildTableUrl('/users?', state)).toContain('/users?per_page=');
    });

    it('builds an export URL without pagination', () => {
        const url = buildTableUrl(
            '/users/export/csv',
            { ...baseState, page: 3, search: 'acme' },
            { paginate: false },
        );

        expect(url).toContain('search=acme');
        expect(url).not.toContain('per_page');
        expect(url).not.toContain('page=');
    });
});

describe('filter helpers', () => {
    it('treats every inactive shape as blank', () => {
        expect(isBlankFilter(null)).toBe(true);
        expect(isBlankFilter(undefined)).toBe(true);
        expect(isBlankFilter('')).toBe(true);
        expect(isBlankFilter(false)).toBe(true);
        expect(isBlankFilter([])).toBe(true);
        expect(isBlankFilter('0')).toBe(false);
        expect(isBlankFilter(['x'])).toBe(false);
        expect(isBlankFilter(0)).toBe(false);
    });

    it('counts an active list once, not once per item', () => {
        expect(
            countActiveFilters({ status: 'active', role: ['admin', 'dispatcher'], empty: '' }),
        ).toBe(2);
    });
});
