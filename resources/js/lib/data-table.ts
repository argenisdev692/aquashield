/**
 * Shared TanStack Table v9 adapter for the DataTable kit.
 *
 * The architecture suite forbids `@tanstack/react-table` imports inside
 * `Components/` and `Pages/`; the table instance lives here, outside both.
 * It owns table presentation state only — rows and totals still arrive from
 * the page (Inertia props), never from a fetch in this module.
 *
 * Server-first contract:
 *   - one already-processed page in `data`
 *   - `manualPagination` + `rowCount`
 *   - `manualSorting` + controlled sorting state
 *   - no sorted/paginated/filtered row models
 *
 * TanStack Table v9 is React Compiler compatible. Keep `columns` stable (the
 * pages define them at module scope) so the column model is not rebuilt on
 * every render.
 */
import { useMemo, useState } from 'react';
import {
    columnVisibilityFeature,
    functionalUpdate,
    rowPaginationFeature,
    rowSelectionFeature,
    rowSortingFeature,
    tableFeatures,
    useTable,
    type ColumnDef,
    type ColumnVisibilityState,
    type PaginationState,
    type ReactTable,
    type RowData,
    type RowSelectionState,
    type SortingState,
    type Updater,
} from '@tanstack/react-table';
import type { ReactNode } from 'react';

export type { RowData } from '@tanstack/react-table';

/** Horizontal alignment for a column; headers inherit the cell alignment. */
export type ColumnAlign = 'left' | 'center' | 'right';

/** One server column. `sortable` defaults to `true`. */
export interface SimpleColumn<TData> {
    accessorKey: Extract<keyof TData, string>;
    header: string;
    /**
     * Stable identity for the column. Defaults to `accessorKey`.
     *
     * Use this when the display column and the sort field differ, so column
     * visibility and user preferences are keyed by the thing the user sees
     * (`name`) instead of a database field (`first_name`).
     */
    id?: string;
    cell?: (params: { row: TData; value: unknown }) => ReactNode;
    /** Server field to sort by when it differs from `accessorKey`. */
    sortKey?: string;
    /**
     * Set `false` to keep the column out of the visibility menu. The identity
     * column should stay visible: a table of rows with no label is not a view.
     */
    hidable?: boolean;
    /** Set `false` for actions, badges or computed columns the server cannot sort. */
    sortable?: boolean;
    /** Text left (default), numbers right, short statuses centre. */
    align?: ColumnAlign;
    /** Width utility for the column, e.g. `w-24`. */
    width?: string;
    /** Extra classes for every body cell in this column. */
    className?: string;
    /** Extra classes for this column's header cell. */
    headerClassName?: string;
    /**
     * Progressive disclosure: hide the whole column (header AND body) below the
     * given breakpoint. A phone gets fewer, wider columns instead of a table
     * that is 3× the viewport; the widest layouts get the full set. Declare it
     * here, not twice — a `hidden` in `className` alone leaves the header cell
     * visible and still widens the table.
     */
    hideBelow?: 'sm' | 'md' | 'lg' | 'xl';
}

export interface ServerTableSort {
    field: string;
    direction: 'asc' | 'desc';
}

export interface ServerTableOptions<TData extends RowData> {
    columns: readonly SimpleColumn<TData>[];
    data: TData[];
    total: number;
    page: number;
    perPage: number;
    sort: ServerTableSort | null;
    getRowId?: (row: TData) => string;
    /** Hydration point for a persisted per-column visibility preference. */
    initialColumnVisibility?: ColumnVisibilityState;
    onPageChange: (page: number) => void;
    onPerPageChange: (perPage: number) => void;
    onSortChange: (sort: ServerTableSort | null) => void;
}

/**
 * Only the slices this kit uses. No row models: the server already applied
 * filtering, sorting and pagination to `data`. Column visibility is a
 * presentation slice, so it needs no row model either.
 */
const features = tableFeatures({
    columnVisibilityFeature,
    rowPaginationFeature,
    rowSortingFeature,
    rowSelectionFeature,
});

/** Stable empty identity — a fresh `[]` per render invalidates row models. */
const EMPTY_ROWS: never[] = [];
const EMPTY_SORTING: SortingState = [];

/**
 * `uuid`/`id` are the stable application IDs. The index fallback keeps the
 * table usable for an ad-hoc row without an ID, but selection then only holds
 * for the current page; pass `getRowId` when a row has a different key.
 */
const defaultGetRowId = (row: unknown, index: number): string => {
    if (typeof row === 'object' && row !== null) {
        const record = row as Record<string, unknown>;
        const id = record.uuid ?? record.id;

        if (typeof id === 'string' || typeof id === 'number') {
            return String(id);
        }
    }

    return String(index);
};

const buildColumnDefs = <TData extends RowData>(
    columns: readonly SimpleColumn<TData>[],
): ColumnDef<typeof features, TData>[] =>
    columns.map((column) => {
        const renderCell = column.cell;

        return {
            id: column.id ?? column.accessorKey,
            accessorFn: (row: TData) =>
                (row as unknown as Record<string, unknown>)[column.accessorKey],
            header: column.header,
            enableSorting: column.sortable !== false,
            enableHiding: column.hidable !== false,
            cell: renderCell
                ? ({ row, getValue }) => renderCell({ row: row.original, value: getValue() })
                : ({ getValue }) => getValue() as ReactNode,
        };
    });

export type ServerTable<TData extends RowData> = ReactTable<typeof features, TData>;

export function useServerTable<TData extends RowData>(
    options: ServerTableOptions<TData>,
): ServerTable<TData> {
    const {
        columns,
        data,
        total,
        page,
        perPage,
        sort,
        getRowId,
        initialColumnVisibility,
        onPageChange,
        onPerPageChange,
        onSortChange,
    } = options;

    const [rowSelection, setRowSelection] = useState<RowSelectionState>({});
    const [columnVisibility, setColumnVisibility] = useState<ColumnVisibilityState>(
        () => initialColumnVisibility ?? {},
    );

    const columnDefs = useMemo(() => buildColumnDefs(columns), [columns]);

    /*
     * Controlled slices are memoized: `useTable` treats these objects as
     * model inputs, and a new identity on every render writes the whole state
     * store again.
     */
    const pagination = useMemo<PaginationState>(
        () => ({ pageIndex: Math.max(0, page - 1), pageSize: perPage }),
        [page, perPage],
    );

    const sortField = sort?.field ?? null;
    const sortDirection = sort?.direction ?? null;

    /*
     * The server speaks database fields (`first_name`); the table speaks
     * stable column ids (`name`). Translate on the way in and out so the
     * header shows the sorted state without leaking a DB field into column
     * visibility preferences.
     */
    const sortColumn =
        sortField === null
            ? undefined
            : columns.find((column) => (column.sortKey ?? column.accessorKey) === sortField);
    const sortColumnId = sortColumn?.id ?? sortColumn?.accessorKey ?? sortField;

    const sorting = useMemo<SortingState>(
        () =>
            sortColumnId === null || sortDirection === null
                ? EMPTY_SORTING
                : [{ id: sortColumnId, desc: sortDirection === 'desc' }],
        [sortColumnId, sortDirection],
    );

    const table = useTable({
        features,
        columns: columnDefs,
        data: data.length === 0 ? EMPTY_ROWS : data,
        rowCount: total,
        manualPagination: true,
        manualSorting: true,
        autoResetPageIndex: false,
        enableMultiSort: false,
        enableSortingRemoval: false,
        enableRowSelection: true,
        getRowId: getRowId ?? defaultGetRowId,
        state: { pagination, sorting, rowSelection, columnVisibility },
        onPaginationChange: (updater: Updater<PaginationState>) => {
            const next = functionalUpdate(updater, pagination);

            if (next.pageSize !== pagination.pageSize) {
                onPerPageChange(next.pageSize);
                return;
            }

            if (next.pageIndex !== pagination.pageIndex) {
                onPageChange(next.pageIndex + 1);
            }
        },
        onSortingChange: (updater: Updater<SortingState>) => {
            const next = functionalUpdate(updater, sorting);
            const first = next[0];

            if (first === undefined) {
                onSortChange(null);
                setRowSelection({});

                return;
            }

            const column = columns.find((entry) => (entry.id ?? entry.accessorKey) === first.id);

            onSortChange({
                field: column?.sortKey ?? column?.accessorKey ?? first.id,
                direction: first.desc ? 'desc' : 'asc',
            });
            setRowSelection({});
        },
        onColumnVisibilityChange: setColumnVisibility,
        onRowSelectionChange: setRowSelection,
    });

    return table;
}
