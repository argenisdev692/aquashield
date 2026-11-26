/**
 * Saved-view ↔ table-state translation.
 *
 * A view stores search, sort, page size and filters. "Active" must therefore
 * mean all four dimensions match, not just search and filters: changing the
 * sort after applying a view used to leave the toolbar showing that view's
 * name for a state it no longer described.
 *
 * Kept free of React and UI imports so the comparison is unit-testable.
 */
import type { TableFilters, TableState } from '@/lib/table';
import type { SavedView } from './hooks/useSavedViews';

/** The four state dimensions a saved view stores. `page` is never saved. */
export type SavedViewState = Partial<Omit<TableState, 'page'>>;

export const viewToState = (view: SavedView): SavedViewState => ({
    search: view.search ?? '',
    perPage: view.per_page,
    sort:
        view.sort_field === null
            ? null
            : { field: view.sort_field, order: view.sort_order === -1 ? -1 : 1 },
    filters: view.filters,
});

const normalizeFilterMap = (filters: TableFilters): Record<string, string> => {
    const out: Record<string, string> = {};

    for (const [key, value] of Object.entries(filters)) {
        if (typeof value === 'string' && value.trim() !== '') {
            out[key] = value.trim();
        } else if (typeof value === 'number') {
            out[key] = String(value);
        } else if (Array.isArray(value) && value.length > 0) {
            out[key] = value.map(String).join(',');
        }
    }

    return out;
};

const sortSignature = (field: string | null, order: number): string =>
    field === null ? 'none' : `${field}:${order === -1 ? -1 : 1}`;

export const isViewActive = (view: SavedView, state: TableState): boolean => {
    if ((view.search ?? '').trim() !== state.search.trim()) {
        return false;
    }

    if (view.per_page !== state.perPage) {
        return false;
    }

    const viewSort = sortSignature(view.sort_field, view.sort_order);
    const stateSort =
        state.sort === null ? 'none' : sortSignature(state.sort.field, state.sort.order);

    if (viewSort !== stateSort) {
        return false;
    }

    const viewFilters = normalizeFilterMap(view.filters);
    const stateFilters = normalizeFilterMap(state.filters);
    const viewKeys = Object.keys(viewFilters);
    const stateKeys = Object.keys(stateFilters);

    if (viewKeys.length !== stateKeys.length) {
        return false;
    }

    return viewKeys.every((key) => viewFilters[key] === stateFilters[key]);
};
