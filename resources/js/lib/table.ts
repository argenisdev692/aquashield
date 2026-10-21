import { computed, reactive, ref } from 'vue';

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

const isBlank = (value: TableFilterValue): boolean => {
    if (value === null || value === undefined || value === '' || value === false) {
        return true;
    }

    return isFilterList(value) && value.length === 0;
};

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

        if (state.perPage !== 15) {
            params.set('per_page', String(state.perPage));
        }
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

export function hasActiveFilters(filters: TableFilters): boolean {
    return Object.values(filters).some((value) => !isBlank(value));
}

export function countActiveFilters(filters: TableFilters): number {
    return Object.values(filters).filter((value) => !isBlank(value)).length;
}

/** Delete in place so the reactive object survives every caller's reference. */
export function clearTableFilters(filters: TableFilters): void {
    for (const key of Object.keys(filters)) {
        delete filters[key];
    }
}

export function useTableState<F extends TableFilters = TableFilters>(
    initial: Partial<Omit<TableState, 'filters'>> & { filters?: F } = {},
) {
    const page = ref<number>(initial.page ?? 1);
    const perPage = ref<number>(initial.perPage ?? 15);
    const search = ref<string>(initial.search ?? '');
    const sort = ref<TableSort | null>(initial.sort ?? null);
    /**
     * Keep the caller's object identity so a page can declare a typed filter
     * record once and bind `v-model` to its own fields. `reactive` does not add
     * refs to plain string/number/array values, so the cast is structural.
     */
    const filters = reactive((initial.filters ?? {}) as F) as F;

    const snapshot = (): TableState => ({
        page: page.value,
        perPage: perPage.value,
        search: search.value,
        sort: sort.value,
        filters,
    });

    const params = computed(() => buildTableParams(snapshot()));
    const activeFilterCount = computed(() => countActiveFilters(filters));

    const resetFilters = (): void => clearTableFilters(filters);

    const reset = (): void => {
        page.value = 1;
        search.value = '';
        sort.value = null;
        resetFilters();
    };

    /**
     * The button in the toolbar and the query share this helper. `paginate:
     * false` is the export contract: all filtered rows, never just this page.
     */
    const exportUrl = (endpoint: string): string =>
        buildTableUrl(endpoint, snapshot(), { paginate: false });

    return {
        page,
        perPage,
        search,
        sort,
        filters,
        params,
        activeFilterCount,
        resetFilters,
        reset,
        exportUrl,
    };
}
