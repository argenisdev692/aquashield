/**
 * Server-side table state — one representation for the query and the export.
 *
 * The 2026 rule for an enterprise list is the same one the skill states in
 * prose: the browser never sorts, filters or paginates. This module owns the
 * only serialisation of that state, so the request that fills the table and
 * the request that fills a CSV/XLSX/PDF cannot drift apart.
 *
 * `filter[...]` is the Laravel-native shape (`$request->input('filter')`),
 * `sort=-created_at` is the Spatie QueryBuilder convention, and `page` /
 * `per_page` are the numbers the server already understands.
 */

export type TableSortOrder = 1 | -1;

export interface TableSort {
    field: string;
    order: TableSortOrder;
}

/**
 * `false` is treated as an inactive toggle, not as a filter value. When the
 * domain genuinely needs a `false` filter, encode it as the string `'0'` so
 * the distinction survives the URL.
 */
export type TableFilterValue =
    string | number | boolean | readonly (string | number)[] | null | undefined;

export type TableFilters = Record<string, TableFilterValue>;

export interface TableState {
    page: number;
    perPage: number;
    search: string;
    sort: TableSort | null;
    filters: TableFilters;
}

export interface BuildTableParamsOptions {
    /**
     * Exports set this to `false`: an export covers every filtered row, not
     * the fifteen rows on the current page.
     */
    paginate?: boolean;
}

const isFilterList = (value: TableFilterValue): value is readonly (string | number)[] =>
    Array.isArray(value);

/** The server default. Kept explicit so the client never guesses silently. */
const DEFAULT_PER_PAGE = 15;

/** A filter is inactive when it is empty, null, `false` or an empty list. */
export const isBlankFilter = (value: TableFilterValue): boolean => {
    if (value === null || value === undefined || value === '' || value === false) {
        return true;
    }

    return isFilterList(value) && value.length === 0;
};

const isBlank = isBlankFilter;

/** Serialise the state into the single query-string shape the server reads. */
export function buildTableParams(
    state: TableState,
    options: BuildTableParamsOptions = {},
): URLSearchParams {
    const params = new URLSearchParams();

    if (options.paginate ?? true) {
        if (state.page > 1) {
            params.set('page', String(state.page));
        }

        // Always explicit. The server owns the value it actually paginated by,
        // and a client that omits this key silently drifts from it whenever the
        // two defaults differ (the Users page used to render 15 rows while its
        // footer, page count and selector all said 10).
        params.set('per_page', String(state.perPage));
    }

    const search = state.search.trim();

    if (search !== '') {
        params.set('search', search);
    }

    if (state.sort !== null) {
        params.set('sort', `${state.sort.order === -1 ? '-' : ''}${state.sort.field}`);
    }

    for (const [key, value] of Object.entries(state.filters)) {
        if (isBlank(value)) {
            continue;
        }

        if (isFilterList(value)) {
            for (const item of value) {
                params.append(`filter[${key}][]`, String(item));
            }

            continue;
        }

        params.set(`filter[${key}]`, String(value));
    }

    return params;
}

/** Append the serialised state to an endpoint that may already carry a query. */
export function buildTableUrl(
    endpoint: string,
    state: TableState,
    options: BuildTableParamsOptions = {},
): string {
    const params = buildTableParams(state, options).toString();

    if (params === '') {
        return endpoint;
    }

    const separator =
        endpoint.endsWith('?') || endpoint.endsWith('&') ? '' : endpoint.includes('?') ? '&' : '?';

    return `${endpoint}${separator}${params}`;
}

const parsePositiveInt = (value: string | null, fallback: number): number => {
    if (value === null) {
        return fallback;
    }

    const parsed = Number.parseInt(value, 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
};

const parseSort = (value: string | null): TableSort | null => {
    if (value === null || value.trim() === '') {
        return null;
    }

    const descending = value.startsWith('-');
    const field = descending ? value.slice(1) : value;

    if (field === '' || !/^[a-zA-Z0-9_.]+$/.test(field)) {
        return null;
    }

    return { field, order: descending ? -1 : 1 };
};

/**
 * The inverse of `buildTableParams`: read the table state back from the URL.
 *
 * The URL is the single source of truth. Parsing it (instead of copying the
 * server's echoed filters into state) makes reload, browser back/forward and a
 * pasted link all resolve to the same table, with no state echo to reconcile.
 * Unknown keys are ignored so a stale bookmark cannot inject a filter the page
 * never asked for.
 */
export function parseTableParams(
    url: string,
    defaults: Partial<Pick<TableState, 'perPage' | 'sort'>> = {},
): TableState {
    const queryStart = url.indexOf('?');
    const params = new URLSearchParams(queryStart === -1 ? '' : url.slice(queryStart + 1));
    const filters: TableFilters = {};

    for (const [key, value] of params.entries()) {
        const match = /^filter\[([^\]]{1,64})\](?:\[\])?$/.exec(key);

        if (match === null) {
            continue;
        }

        const name = match[1];

        if (key.endsWith('[]')) {
            const current = filters[name];
            const list = Array.isArray(current) ? [...current] : [];
            list.push(value);
            filters[name] = list;

            continue;
        }

        filters[name] = value;
    }

    return {
        page: parsePositiveInt(params.get('page'), 1),
        perPage: Math.min(
            100,
            parsePositiveInt(params.get('per_page'), defaults.perPage ?? DEFAULT_PER_PAGE),
        ),
        search: params.get('search') ?? '',
        sort: parseSort(params.get('sort')) ?? defaults.sort ?? null,
        filters,
    };
}

export function countActiveFilters(filters: TableFilters): number {
    return Object.values(filters).filter((value) => !isBlank(value)).length;
}
