/**
 * DataTableKit — composed server-first table on TanStack Table v9.
 *
 * The page owns the query. It passes one already-processed page as `rows`, the
 * filtered count as `total`, and URL-changing callbacks for page, search, sort
 * and filters. The table engine lives in `lib/data-table.ts`; this component
 * owns the toolbar, header/sort affordances, checkbox selection, bulk bar,
 * pagination, export and empty state.
 *
 * Never sorts, filters or paginates rows in memory. `manualPagination` and
 * `manualSorting` make the server the only processing owner.
 *
 * The table is the ONLY horizontal scroller on the page: the card clips its own
 * radius (`overflow-hidden`) and the wrapper below owns `overflow-x-auto`, so a
 * wide data set never widens the browser viewport. Its parent column is
 * `min-w-0` for the same reason — a flex item refuses to shrink below its
 * content by default.
 *
 * Composition over duplication: `filters` (the CrudFilters bar), `views`
 * (saved views) and `actions` are slots, so a CRUD screen supplies data and
 * wiring — never a second copy of the toolbar. Export and deletion only render
 * when a handler exists; a destructive control that does nothing is worse than
 * no control.
 *
 * Visual system: a 48px header, 48px rows, 16px cell gutters (32px between
 * columns, the minimum a dense table needs to stay scannable), 12px semibold
 * headers in sentence case and `tabular-nums` for numbers and dates. Numbers
 * align right, text left, and a header always sits above the cells it labels.
 * A soft-deleted row gets a red tint, a left accent and (from the page) a
 * status tag — colour is never the only signal.
 */
import { useEffect, useEffectEvent, useId, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
    Download,
    FileDown,
    FileSpreadsheet,
    FileText,
    Filter,
    Trash2,
    RefreshCw,
    Loader2,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    ChevronDown,
    ChevronsUpDown,
    Search,
    X,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import { downloadFile, HttpRequestError } from '@/lib/http';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import DataTableColumns from '@/Components/DataTable/DataTableColumns';
import {
    useServerTable,
    type ColumnAlign,
    type RowData,
    type SimpleColumn,
} from '@/lib/data-table';
import {
    defaultTablePreferences,
    hiddenFromVisibility,
    readTablePreferences,
    sanitizeHiddenColumns,
    visibilityFromHidden,
    writeTablePreferences,
    type TableDensity,
} from '@/lib/table-preferences';
import { cn } from '@/lib/utils';

export type { SimpleColumn } from '@/lib/data-table';

export type ExportFormat = 'csv' | 'xlsx' | 'pdf';

/** A page visit is a network round-trip; a keystroke is not. */
const SEARCH_DEBOUNCE_MS = 300;

/** Same minimum the server-side filters assume. Shorter queries are ignored. */
const SEARCH_MIN_LENGTH = 2;

const alignClass = (align: ColumnAlign | undefined): string =>
    align === 'center' ? 'text-center' : align === 'right' ? 'text-right' : 'text-left';

/**
 * Flex alignment for a header control.
 *
 * `text-align` is not enough here: Chrome's UA stylesheet sets
 * `text-align: start` on `<button>`, so a sortable header ignored a centred or
 * right-aligned column. The sort control owns its alignment instead.
 */
const justifyClass = (align: ColumnAlign | undefined): string =>
    align === 'center' ? 'justify-center' : align === 'right' ? 'justify-end' : 'justify-start';

const exportFormatLabel = (format: string): string => {
    switch (format.toLowerCase()) {
        case 'csv':
            return 'CSV';
        case 'xlsx':
            return 'Excel (XLSX)';
        case 'pdf':
            return 'PDF';
        default:
            return format.toUpperCase();
    }
};

const exportFormatIcon = (format: string): ReactNode => {
    switch (format.toLowerCase()) {
        case 'csv':
            return <FileText className="size-4 text-aq-accent" aria-hidden="true" />;
        case 'xlsx':
            return <FileSpreadsheet className="size-4 text-aq-success" aria-hidden="true" />;
        case 'pdf':
            return <FileDown className="size-4 text-aq-danger" aria-hidden="true" />;
        default:
            return <Download className="size-4 text-surface-500" aria-hidden="true" />;
    }
};

/**
 * One type contract for every header cell: 12px, semibold, sentence case.
 *
 * It used to be 11px uppercase with wide tracking, and `uppercase` was left to
 * inherit from `<th>` into the label. Chrome forces `text-transform: none` on
 * form controls, so every sortable header rendered in title case while the
 * static ones stayed uppercase — the "disordered headers" in the report. The
 * label now carries its own type and nothing relies on inherited transforms,
 * which also removes the all-caps micro-label look that reads as dated at
 * 11px. Contrast comes from weight and colour, not from caps.
 */
const headerType = 'text-xs font-semibold tracking-normal text-surface-600 dark:text-surface-300';

interface DataTableKitProps<TData extends RowData> {
    columns: SimpleColumn<TData>[];
    data: TData[];
    total: number;
    page: number;
    setPage: (page: number) => void;
    perPage: number;
    setPerPage: (perPage: number) => void;
    search: string;
    setSearch: (search: string) => void;
    sort: { field: string; direction: 'asc' | 'desc' } | null;
    setSort: (sort: { field: string; direction: 'asc' | 'desc' } | null) => void;
    /** Stable row key. Defaults to `uuid`, then `id`, then the page index. */
    getRowId?: (row: TData) => string;
    loading?: boolean;
    title?: string;
    description?: string;
    searchPlaceholder?: string;
    filterCount?: number;
    hasActiveFilters?: boolean;
    perPageOptions?: number[];
    exportFormats?: ExportFormat[];
    /** Download URL for a format. Without it (and without `onExport`) the selector is hidden. */
    exportHref?: (format: string) => string | undefined;
    /** Human label used by the selection checkbox and the bulk bar title. */
    selectionLabel?: (row: TData) => string;
    /**
     * Visual state of a row. `deleted` tints a soft-deleted/quarantined row and
     * adds a left accent; the page should also render a status tag so the state
     * is not communicated by colour alone.
     */
    rowState?: (row: TData) => 'default' | 'deleted';
    /** Trailing per-row actions column, e.g. View / Edit / Delete. */
    rowActions?: (row: TData) => ReactNode;
    /** Error state for a Query-backed table. The page owns retry semantics. */
    error?: ReactNode;
    /** A failed action (bulk delete/restore) that must keep the rows on screen. */
    actionError?: string | null;
    onRetry?: () => void;
    emptyTitle?: string;
    emptyDescription?: string;
    /** The CrudFilters bar. */
    filters?: ReactNode;
    /** Saved views selector, rendered before the filters. */
    views?: ReactNode;
    actions?: ReactNode;
    empty?: ReactNode;
    emptyAction?: ReactNode;
    onExport?: (format: string) => void;
    /**
     * Bulk handlers receive the selected loaded rows, not opaque indices.
     * Selection is page-scoped under manual pagination. Omit a handler to hide
     * its control (and the whole checkbox column when both are omitted).
     */
    onBulkDelete?: (rows: TData[]) => void;
    onBulkRestore?: (rows: TData[]) => void;
    /** Context-specific recovery copy for the bulk-delete confirmation. */
    deleteDescription?: string;
    /**
     * A contextual action slot rendered inside the bulk bar. It receives the
     * selected loaded rows and a clear-selection callback. Omit both this and
     * the two handlers above to hide the selection column entirely.
     */
    bulkActions?: (rows: TData[], clearSelection: () => void) => ReactNode;
    /** Mirrors table selection to the page, e.g. for a header action count. */
    onSelectionChange?: (ids: string[], rows: TData[]) => void;
    onResetFilters?: () => void;
    /**
     * localStorage namespace for hidden columns and density. `useCrudTable`
     * supplies the endpoint, so pages get persistence without extra wiring.
     * Omit it for a table whose presentation should not persist.
     */
    preferencesKey?: string;
}

/**
 * Literal classes, never a template: Tailwind scans source text, so
 * `hidden ${bp}:table-cell` would generate nothing.
 */
const HIDE_BELOW: Record<NonNullable<SimpleColumn<unknown>['hideBelow']>, string> = {
    sm: 'hidden sm:table-cell',
    md: 'hidden md:table-cell',
    lg: 'hidden lg:table-cell',
    xl: 'hidden xl:table-cell',
};

export default function DataTableKit<TData extends RowData>({
    columns,
    data,
    total,
    page,
    setPage,
    perPage,
    setPerPage,
    search,
    setSearch,
    sort,
    setSort,
    getRowId,
    loading = false,
    title,
    description,
    searchPlaceholder = 'Search…',
    filterCount = 0,
    hasActiveFilters = false,
    perPageOptions = [10, 15, 25, 50],
    exportFormats = ['csv', 'xlsx', 'pdf'],
    exportHref,
    selectionLabel,
    rowState,
    rowActions,
    error,
    actionError,
    onRetry,
    emptyTitle,
    emptyDescription,
    filters,
    views,
    actions,
    empty,
    emptyAction,
    onExport,
    onBulkDelete,
    onBulkRestore,
    deleteDescription,
    bulkActions,
    onSelectionChange,
    onResetFilters,
    preferencesKey,
}: DataTableKitProps<TData>) {
    /*
     * Read presentation preferences once, before the table instance exists:
     * TanStack needs the initial visibility map at construction time. The
     * page remounts between resources, so the key does not change under a
     * mounted table.
     */
    const preferenceColumns = columns.map((column) => ({
        id: column.id ?? column.accessorKey,
        hidable: column.hidable !== false,
    }));
    const [storedPreferences] = useState(() =>
        preferencesKey === undefined
            ? defaultTablePreferences()
            : readTablePreferences(preferencesKey),
    );
    const [initialHidden] = useState(() =>
        sanitizeHiddenColumns(storedPreferences.hidden, preferenceColumns),
    );
    const [initialColumnVisibility] = useState(() => visibilityFromHidden(initialHidden));
    const [density, setDensity] = useState<TableDensity>(storedPreferences.density);

    /*
     * The engine is outside this component (architecture invariant 2). It
     * receives stable columns from the page and returns the React-facing
     * table, including pagination, sorting, row-selection and visibility.
     */
    const table = useServerTable<TData>({
        columns,
        data,
        total,
        page,
        perPage,
        sort,
        getRowId,
        initialColumnVisibility,
        onPageChange: setPage,
        onPerPageChange: setPerPage,
        onSortChange: setSort,
    });

    const [confirmDeleteOpen, setConfirmDeleteOpen] = useState(false);
    const [exportError, setExportError] = useState<string | null>(null);
    const [exporting, setExporting] = useState<string | null>(null);
    const exportAbort = useRef<AbortController | null>(null);
    const rowsPerPageId = useId();
    const searchId = useId();

    useEffect(() => {
        return () => {
            exportAbort.current?.abort();
            exportAbort.current = null;
        };
    }, []);

    /*
     * Persist on a derived signature rather than the state object: `table.state`
     * is a store read and its identity is not a contract. A string dependency
     * writes storage only when the actually visible set changes.
     */
    const hiddenSignature = JSON.stringify(hiddenFromVisibility(table.state.columnVisibility));
    const persistPreferences = useEffectEvent(() => {
        if (preferencesKey === undefined) {
            return;
        }

        writeTablePreferences(preferencesKey, {
            hidden: hiddenFromVisibility(table.state.columnVisibility),
            density,
        });
    });

    useEffect(() => {
        persistPreferences();
    }, [preferencesKey, hiddenSignature, density]);

    /*
     * The input is local, the query is a page visit. Without this boundary
     * every keystroke called `router.visit`, which cancelled the previous
     * visit, toggled `useVisitPending` repeatedly and made the table flicker.
     *
     * `sentSearch` is the last value this component pushed to the server. It
     * distinguishes "the server echoed our search back" from "the user
     * navigated / reset filters / applied a view" so a late prop never
     * overwrites what is being typed.
     */
    const [draftSearch, setDraftSearch] = useState(search);
    const sentSearch = useRef(search);

    useEffect(() => {
        if (search === sentSearch.current) {
            return;
        }

        sentSearch.current = search;
        setDraftSearch(search);
    }, [search]);

    /*
     * `setSearch` changes identity whenever the page re-renders (it comes from
     * the CRUD hook). `useEffectEvent` lets the debounce timer call the latest
     * one without resetting the timer every time.
     */
    const pushSearch = useEffectEvent((value: string) => {
        table.resetRowSelection();
        setSearch(value);
    });

    useEffect(() => {
        const next = draftSearch.trim();

        if (next === sentSearch.current.trim()) {
            return;
        }

        if (next.length > 0 && next.length < SEARCH_MIN_LENGTH) {
            return;
        }

        const timer = window.setTimeout(() => {
            sentSearch.current = draftSearch;
            pushSearch(draftSearch);
        }, SEARCH_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [draftSearch]);

    /*
     * Selection is page-scoped. A back/forward navigation or a refetch can
     * replace the rows without passing through one of our handlers, so clear
     * the selected IDs when the server-owned inputs change.
     */
    const resetSelection = useEffectEvent(() => {
        if (Object.keys(table.state.rowSelection).length > 0) {
            table.resetRowSelection();
        }
    });

    useEffect(() => {
        resetSelection();
    }, [data, search, page, perPage, sort?.field, sort?.direction]);

    const notifySelection = useEffectEvent(() => {
        onSelectionChange?.(
            Object.keys(table.state.rowSelection),
            table.getSelectedRowModel().rows.map((row) => row.original),
        );
    });

    useEffect(() => {
        notifySelection();
    }, [table.state.rowSelection]);

    const pageCount = Math.max(1, table.getPageCount());
    const selectedRows = table.getSelectedRowModel().rows.map((row) => row.original);
    const selectedCount = Object.keys(table.state.rowSelection).length;
    const selectedTitle =
        selectionLabel === undefined
            ? undefined
            : selectedRows.map((row) => selectionLabel(row)).join(', ');

    const canBulkDelete = onBulkDelete !== undefined;
    const canBulkRestore = onBulkRestore !== undefined;
    const hasBulkActions = canBulkDelete || canBulkRestore || bulkActions !== undefined;
    const selectable = hasBulkActions || onSelectionChange !== undefined;

    const canExport =
        exportFormats.length > 0 && (exportHref !== undefined || onExport !== undefined);

    const filtered = hasActiveFilters || filterCount > 0 || search.trim() !== '';

    const resolvedEmptyTitle = emptyTitle ?? (filtered ? 'No matching records' : 'No records yet');
    const resolvedEmptyDescription =
        emptyDescription ??
        (filtered
            ? 'Try a different search or clear the filters.'
            : 'Records will appear here once they are created.');

    // A stale deep link can point past the last page; derive the range from what
    // the server actually returned so the footer never reads "41–30 of 30".
    const shownPage = Math.min(page, pageCount);
    const pageOutOfRange = data.length === 0 && total > 0 && page > pageCount;
    const firstRow = data.length === 0 ? 0 : (shownPage - 1) * perPage + 1;
    const lastRow = firstRow === 0 ? 0 : Math.min(firstRow + data.length - 1, total);

    /** A failed export and a failed bulk action share one alert region. */
    const tableError = exportError ?? actionError ?? null;

    /** Column definition for a stable column id (defaults to `accessorKey`). */
    const columnFor = (id: string): SimpleColumn<TData> | undefined =>
        columns.find((column) => (column.id ?? column.accessorKey) === id);

    /*
     * The server owns the row set; visibility only decides which of the
     * received cells render. Everything layout-related (empty-state colspan,
     * skeleton, header) follows this list, never `columns.length`.
     */
    const visibleColumns = table
        .getVisibleLeafColumns()
        .map((column) => columnFor(column.id))
        .filter((column): column is SimpleColumn<TData> => column !== undefined);

    /** Leading selection column + visible data columns + optional actions column. */
    const columnCount =
        visibleColumns.length + (selectable ? 1 : 0) + (rowActions === undefined ? 0 : 1);

    const cellPadding =
        density === 'compact' ? 'px-3 py-1.5 sm:px-4 sm:py-2' : 'px-3 py-3 sm:px-4 sm:py-3.5';
    const headerHeight = density === 'compact' ? 'h-9' : 'h-11';
    const emptyPadding = density === 'compact' ? 'py-12' : 'py-16';

    /*
     * Mirror the height of what is on screen. The previous fixed six rows
     * collapsed a 100-row page into a six-row card on every visit, jumping the
     * scroll position when the real rows landed.
     */
    const skeletonRowCount =
        data.length > 0 ? Math.min(data.length, perPage) : Math.min(perPage, 10);

    const handleClearFilters = (): void => {
        table.resetRowSelection();
        onResetFilters?.();
    };

    const handleExport = (format: string): void => {
        const href = exportHref?.(format);

        if (href === undefined || href === '') {
            onExport?.(format);

            return;
        }

        setExportError(null);
        setExporting(format);

        const controller = new AbortController();
        exportAbort.current?.abort();
        exportAbort.current = controller;

        void downloadFile(href, controller.signal)
            .then(({ blob, filename }) => {
                // A real anchor keeps the SPA mounted and lets the browser treat
                // the blob as a download. The URL is revoked after the click.
                const url = URL.createObjectURL(blob);
                const anchor = document.createElement('a');
                anchor.href = url;
                anchor.download = filename ?? `export.${format}`;
                anchor.rel = 'noopener';
                document.body.append(anchor);
                anchor.click();
                anchor.remove();
                window.setTimeout(() => URL.revokeObjectURL(url), 1_000);
            })
            .catch((cause: unknown) => {
                if (cause instanceof DOMException && cause.name === 'AbortError') {
                    return;
                }

                setExportError(
                    cause instanceof HttpRequestError
                        ? cause.message
                        : 'The export could not be generated. Try again.',
                );
            })
            .finally(() => {
                if (exportAbort.current === controller) {
                    exportAbort.current = null;
                    setExporting(null);
                }
            });
    };

    const handleBulkDelete = (): void => {
        setConfirmDeleteOpen(false);
        onBulkDelete?.(selectedRows);
    };

    const handleBulkRestore = (): void => {
        onBulkRestore?.(selectedRows);
    };

    return (
        <section className="min-w-0 max-w-full overflow-hidden rounded-xl border border-aq-shell-line bg-surface-0 shadow-sm dark:bg-surface-900">
            <header className="border-b border-aq-shell-line px-4 py-4 sm:px-5">
                {title || description ? (
                    <div className="mb-3.5">
                        {title && (
                            <h2 className="text-base font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                                {title}
                            </h2>
                        )}
                        {description && (
                            <p className="mt-0.5 text-sm text-surface-600 dark:text-surface-300">
                                {description}
                            </p>
                        )}
                    </div>
                ) : null}

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="flex flex-1 flex-wrap items-center gap-2">
                        <div className="relative w-full sm:max-w-xs">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-surface-400"
                                aria-hidden="true"
                            />
                            {/* A visually hidden label is the field's real name;
                                the placeholder is an example, not a label. */}
                            <label htmlFor={searchId} className="sr-only">
                                {searchPlaceholder}
                            </label>
                            {/* Never disable the field while the visit runs: a
                                debounced search would blur the input mid-word on
                                every pause and force the user to click again. */}
                            <Input
                                id={searchId}
                                placeholder={searchPlaceholder}
                                value={draftSearch}
                                onChange={(e) => setDraftSearch(e.target.value)}
                                className="h-9 pr-9 pl-9 dark:bg-surface-900"
                            />
                            {draftSearch !== '' ? (
                                <button
                                    type="button"
                                    onClick={() => setDraftSearch('')}
                                    aria-label="Clear search"
                                    className="absolute top-1/2 right-2 grid size-7 -translate-y-1/2 place-items-center rounded-md text-surface-500 transition-colors duration-150 hover:bg-surface-100 hover:text-surface-800 motion-reduce:transition-none dark:hover:bg-surface-800 dark:hover:text-surface-100"
                                >
                                    <X className="size-3.5" aria-hidden="true" />
                                </button>
                            ) : null}
                        </div>

                        {draftSearch.trim().length > 0 &&
                        draftSearch.trim().length < SEARCH_MIN_LENGTH ? (
                            <span
                                aria-live="polite"
                                className="text-xs font-medium text-surface-500 dark:text-surface-300"
                            >
                                Type at least {SEARCH_MIN_LENGTH} characters
                            </span>
                        ) : null}

                        {views}
                    </div>

                    <div className="flex flex-wrap items-center gap-2 sm:ml-auto">
                        {actions}
                        <DataTableColumns
                            table={table}
                            columns={columns}
                            density={density}
                            onDensityChange={setDensity}
                        />
                        {canExport ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger
                                    disabled={loading || exporting !== null}
                                    render={
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="toolbar"
                                            disabled={loading || exporting !== null}
                                        />
                                    }
                                >
                                    {exporting === null ? (
                                        <Download
                                            className="size-3.5 text-surface-500 dark:text-surface-300"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <Loader2
                                            className="size-3.5 animate-spin text-surface-500 motion-reduce:animate-none dark:text-surface-300"
                                            aria-hidden="true"
                                        />
                                    )}
                                    {exporting === null ? 'Export' : 'Exporting…'}
                                    <ChevronDown
                                        className="size-3.5 text-surface-400"
                                        aria-hidden="true"
                                    />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="min-w-48">
                                    {exportFormats.map((fmt) => (
                                        <DropdownMenuItem
                                            key={fmt}
                                            onClick={() => handleExport(fmt)}
                                            disabled={exporting !== null}
                                            className="gap-2 py-2"
                                        >
                                            {exportFormatIcon(fmt)}
                                            <span>Export {exportFormatLabel(fmt)}</span>
                                        </DropdownMenuItem>
                                    ))}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : null}
                    </div>
                </div>

                {filters !== undefined ||
                ((filterCount > 0 || hasActiveFilters) && onResetFilters !== undefined) ? (
                    <div className="mt-3.5 border-t border-aq-shell-line/60 pt-3.5">
                        {filters}

                        {/* Legacy fallback for a page that renders no filter
                            slot. CrudFilters owns its own Reset and chips now. */}
                        {filters === undefined &&
                        (filterCount > 0 || hasActiveFilters) &&
                        onResetFilters !== undefined ? (
                            <Button
                                variant="outline"
                                className="h-9"
                                onClick={handleClearFilters}
                                disabled={loading}
                            >
                                <Filter className="mr-1.5 size-3.5" aria-hidden="true" />
                                Clear filters
                                {filterCount > 0 ? ` (${filterCount})` : ''}
                            </Button>
                        ) : null}
                    </div>
                ) : null}
            </header>

            {tableError !== null ? (
                <div
                    role="alert"
                    className="border-b border-aq-shell-line bg-destructive/5 px-4 py-2.5 sm:px-5 text-sm font-medium text-destructive"
                >
                    {tableError}
                </div>
            ) : null}

            {selectable && selectedCount > 0 && hasBulkActions ? (
                <div className="border-b border-aq-shell-line bg-aq-accent/5 px-4 py-2.5 sm:px-5 dark:bg-aq-accent/10">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3">
                            <span
                                title={selectedTitle}
                                className="text-sm font-semibold text-surface-900 tabular-nums dark:text-surface-0"
                            >
                                {selectedCount} selected
                            </span>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => table.resetRowSelection()}
                                disabled={loading}
                                className="h-7 px-2 text-xs text-surface-600 dark:text-surface-300"
                            >
                                Clear selection
                            </Button>
                        </div>
                        <div className="flex items-center gap-2">
                            {bulkActions?.(selectedRows, () => table.resetRowSelection())}
                            {canBulkRestore ? (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={handleBulkRestore}
                                    disabled={loading}
                                >
                                    <RefreshCw className="mr-1.5 size-3.5" aria-hidden="true" />
                                    Restore
                                </Button>
                            ) : null}
                            {canBulkDelete ? (
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    onClick={() => setConfirmDeleteOpen(true)}
                                    disabled={loading}
                                >
                                    <Trash2 className="mr-1.5 size-3.5" aria-hidden="true" />
                                    Delete
                                </Button>
                            ) : null}
                        </div>
                    </div>
                </div>
            ) : null}

            <div className="max-w-full overflow-x-auto overscroll-x-contain contain-paint">
                <table className="w-full min-w-full border-collapse text-left text-sm">
                    <thead className="bg-surface-50/90 dark:bg-surface-800/70">
                        {table.getHeaderGroups().map((headerGroup) => (
                            <tr key={headerGroup.id} className="border-b border-aq-shell-line">
                                {selectable ? (
                                    <th
                                        scope="col"
                                        className={cn(
                                            headerHeight,
                                            'w-12 px-3 text-center align-middle sm:px-4',
                                        )}
                                    >
                                        <Checkbox
                                            checked={table.getIsAllPageRowsSelected()}
                                            indeterminate={
                                                table.getIsSomePageRowsSelected() &&
                                                !table.getIsAllPageRowsSelected()
                                            }
                                            onCheckedChange={(checked) =>
                                                table.toggleAllPageRowsSelected(checked)
                                            }
                                            disabled={loading || data.length === 0}
                                            aria-label="Select all rows on this page"
                                            className="cursor-pointer"
                                        />
                                    </th>
                                ) : null}

                                {headerGroup.headers.map((header) => {
                                    const column = columnFor(header.column.id);
                                    const canSort = header.column.getCanSort();
                                    const sorted = header.column.getIsSorted();
                                    const ariaSort =
                                        sorted === 'asc'
                                            ? 'ascending'
                                            : sorted === 'desc'
                                              ? 'descending'
                                              : 'none';

                                    return (
                                        <th
                                            key={header.id}
                                            scope="col"
                                            aria-sort={canSort ? ariaSort : undefined}
                                            className={cn(
                                                headerHeight,
                                                'px-3 align-middle whitespace-nowrap sm:px-4',
                                                headerType,
                                                alignClass(column?.align),
                                                column?.width,
                                                column?.hideBelow !== undefined &&
                                                    HIDE_BELOW[column.hideBelow],
                                                column?.headerClassName,
                                            )}
                                        >
                                            {header.isPlaceholder ? null : canSort ? (
                                                <button
                                                    type="button"
                                                    onClick={header.column.getToggleSortingHandler()}
                                                    disabled={loading}
                                                    className={cn(
                                                        /*
                                                         * `w-full` + flex: the label starts
                                                         * exactly where the cell content starts,
                                                         * with no negative margin nudging the
                                                         * header 4px off its own column, and
                                                         * `justify-*` replaces the alignment the
                                                         * UA button style discards.
                                                         */
                                                        'group/head -my-1 flex w-full items-center gap-1.5 rounded-sm py-1 transition-colors duration-150 motion-reduce:transition-none',
                                                        headerType,
                                                        'hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus disabled:cursor-not-allowed disabled:opacity-60 dark:hover:text-surface-100',
                                                        justifyClass(column?.align),
                                                        sorted !== false &&
                                                            'text-surface-900 dark:text-surface-0',
                                                    )}
                                                >
                                                    <table.FlexRender header={header} />
                                                    {sorted === 'asc' ? (
                                                        <ChevronUp
                                                            className="size-3.5 shrink-0 text-aq-accent"
                                                            aria-hidden="true"
                                                        />
                                                    ) : sorted === 'desc' ? (
                                                        <ChevronDown
                                                            className="size-3.5 shrink-0 text-aq-accent"
                                                            aria-hidden="true"
                                                        />
                                                    ) : (
                                                        <ChevronsUpDown
                                                            className="size-3.5 shrink-0 opacity-30 transition-opacity duration-150 group-hover/head:opacity-70 group-focus-visible/head:opacity-70 motion-reduce:transition-none"
                                                            aria-hidden="true"
                                                        />
                                                    )}
                                                </button>
                                            ) : (
                                                <table.FlexRender header={header} />
                                            )}
                                        </th>
                                    );
                                })}

                                {rowActions ? (
                                    <th
                                        scope="col"
                                        className={cn(
                                            headerHeight,
                                            'w-16 px-3 text-right align-middle sm:px-4',
                                        )}
                                    >
                                        <span className="sr-only">Actions</span>
                                    </th>
                                ) : null}
                            </tr>
                        ))}
                    </thead>
                    <tbody>
                        {loading ? (
                            Array.from({ length: skeletonRowCount }, (_, rowIndex) => (
                                <tr
                                    key={`skeleton-${rowIndex}`}
                                    className="border-b border-aq-shell-line/70 last:border-b-0"
                                >
                                    {selectable ? (
                                        <td className={cn('w-12', cellPadding)}>
                                            <Skeleton className="size-4 rounded-sm" />
                                        </td>
                                    ) : null}
                                    {visibleColumns.map((column, index) => (
                                        <td
                                            key={column.id ?? column.accessorKey}
                                            className={cn(
                                                cellPadding,
                                                column.className,
                                                column.hideBelow !== undefined &&
                                                    HIDE_BELOW[column.hideBelow],
                                            )}
                                        >
                                            {/* Widths mirror the real columns so the
                                                page does not jump when rows land. */}
                                            <Skeleton
                                                className={cn(
                                                    'h-4',
                                                    index === 0 ? 'w-40' : 'w-24',
                                                    column.align === 'right' && 'ml-auto',
                                                )}
                                            />
                                        </td>
                                    ))}
                                    {rowActions ? (
                                        <td className={cn('w-16', cellPadding)}>
                                            <Skeleton className="ml-auto size-4 rounded-sm" />
                                        </td>
                                    ) : null}
                                </tr>
                            ))
                        ) : error ? (
                            <tr>
                                <td
                                    colSpan={columnCount}
                                    className={cn('px-4 text-center', emptyPadding)}
                                >
                                    <p
                                        role="alert"
                                        className="text-sm font-medium text-destructive"
                                    >
                                        {error}
                                    </p>
                                    {onRetry ? (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            className="mt-4"
                                            onClick={onRetry}
                                        >
                                            Try again
                                        </Button>
                                    ) : null}
                                </td>
                            </tr>
                        ) : pageOutOfRange ? (
                            <tr>
                                <td
                                    colSpan={columnCount}
                                    className={cn('px-4 text-center', emptyPadding)}
                                >
                                    <p className="text-sm font-medium text-surface-900 dark:text-surface-0">
                                        This page is empty
                                    </p>
                                    <p className="mt-1 text-sm text-surface-600 dark:text-surface-300">
                                        The list shrank while you were here.{' '}
                                        <span className="font-medium tabular-nums">{total}</span>{' '}
                                        {total === 1 ? 'record remains' : 'records remain'}.
                                    </p>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        className="mt-4"
                                        onClick={() => setPage(pageCount)}
                                    >
                                        Go to page {pageCount}
                                    </Button>
                                </td>
                            </tr>
                        ) : data.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columnCount}
                                    className={cn('px-4 text-center', emptyPadding)}
                                >
                                    {empty ?? (
                                        <>
                                            <p className="text-sm font-medium text-surface-900 dark:text-surface-0">
                                                {resolvedEmptyTitle}
                                            </p>
                                            <p className="mt-1 text-sm text-surface-600 dark:text-surface-300">
                                                {resolvedEmptyDescription}
                                            </p>
                                        </>
                                    )}
                                    {emptyAction ? (
                                        <div className="mt-4 flex justify-center">
                                            {emptyAction}
                                        </div>
                                    ) : null}
                                </td>
                            </tr>
                        ) : (
                            table.getRowModel().rows.map((row) => {
                                const deleted = rowState?.(row.original) === 'deleted';
                                const selected = row.getIsSelected();
                                const accent = deleted ? 'border-l-2 border-l-aq-danger/60' : '';

                                return (
                                    <tr
                                        key={row.id}
                                        data-state={selected ? 'selected' : undefined}
                                        data-deleted={deleted ? 'true' : undefined}
                                        className={cn(
                                            'border-b border-aq-shell-line/70 last:border-b-0 transition-colors duration-150 motion-reduce:transition-none',
                                            deleted
                                                ? 'bg-aq-danger/5 hover:bg-aq-danger/10 dark:bg-aq-danger/10 dark:hover:bg-aq-danger/15'
                                                : 'hover:bg-surface-50/90 dark:hover:bg-surface-800/40',
                                            selected &&
                                                'bg-aq-accent/5 hover:bg-aq-accent/10 dark:bg-aq-accent/10 dark:hover:bg-aq-accent/15',
                                        )}
                                    >
                                        {selectable ? (
                                            <td
                                                className={cn(
                                                    'w-12 text-center align-middle',
                                                    cellPadding,
                                                    accent,
                                                )}
                                            >
                                                <Checkbox
                                                    checked={selected}
                                                    onCheckedChange={(checked) =>
                                                        row.toggleSelected(checked)
                                                    }
                                                    disabled={loading}
                                                    aria-label={
                                                        selectionLabel?.(row.original) ??
                                                        'Select row'
                                                    }
                                                    className="cursor-pointer"
                                                />
                                            </td>
                                        ) : null}
                                        {row.getVisibleCells().map((cell, index) => {
                                            const column = columnFor(cell.column.id);

                                            return (
                                                <td
                                                    key={cell.id}
                                                    className={cn(
                                                        cellPadding,
                                                        'align-middle text-sm leading-5 text-surface-700 dark:text-surface-200',
                                                        alignClass(column?.align),
                                                        column?.width,
                                                        column?.className,
                                                        column?.hideBelow !== undefined &&
                                                            HIDE_BELOW[column.hideBelow],
                                                        index === 0 && !selectable && accent,
                                                    )}
                                                >
                                                    <table.FlexRender cell={cell} />
                                                </td>
                                            );
                                        })}
                                        {rowActions ? (
                                            <td
                                                className={cn(
                                                    'w-16 text-right align-middle',
                                                    cellPadding,
                                                )}
                                            >
                                                {rowActions(row.original)}
                                            </td>
                                        ) : null}
                                    </tr>
                                );
                            })
                        )}
                    </tbody>
                </table>
            </div>

            {total > 0 ? (
                <div className="flex flex-col gap-3 border-t border-aq-shell-line px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <p
                        aria-live="polite"
                        className="text-xs text-surface-600 dark:text-surface-300"
                    >
                        {firstRow === 0 ? (
                            <>
                                This page is empty ·{' '}
                                <span className="font-medium text-surface-900 tabular-nums dark:text-surface-0">
                                    {total}
                                </span>{' '}
                                {total === 1 ? 'record' : 'records'}
                            </>
                        ) : (
                            <>
                                Showing{' '}
                                <span className="font-medium text-surface-900 tabular-nums dark:text-surface-0">
                                    {firstRow}–{lastRow}
                                </span>{' '}
                                of{' '}
                                <span className="font-medium text-surface-900 tabular-nums dark:text-surface-0">
                                    {total}
                                </span>
                            </>
                        )}
                    </p>

                    <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <div className="flex items-center gap-2">
                            <span
                                id={rowsPerPageId}
                                className="text-xs text-surface-600 dark:text-surface-300"
                            >
                                Rows per page
                            </span>
                            <Select
                                value={String(perPage)}
                                onValueChange={(value) => {
                                    table.resetRowSelection();
                                    table.setPageSize(Number(value));
                                }}
                                disabled={loading}
                            >
                                <SelectTrigger className="h-8 w-18" aria-labelledby={rowsPerPageId}>
                                    <SelectValue placeholder={String(perPage)}>
                                        {perPage}
                                    </SelectValue>
                                </SelectTrigger>
                                <SelectContent>
                                    {perPageOptions.map((option) => (
                                        <SelectItem key={option} value={String(option)}>
                                            {option}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex items-center gap-1.5">
                            <Button
                                size="icon-sm"
                                variant="outline"
                                className="size-8"
                                aria-label="Previous page"
                                disabled={!table.getCanPreviousPage() || loading}
                                onClick={() => {
                                    table.resetRowSelection();
                                    table.previousPage();
                                }}
                            >
                                <ChevronLeft className="size-4" aria-hidden="true" />
                            </Button>
                            <span className="px-1 text-xs text-surface-600 tabular-nums dark:text-surface-300">
                                Page {shownPage} of {pageCount}
                            </span>
                            <Button
                                size="icon-sm"
                                variant="outline"
                                className="size-8"
                                aria-label="Next page"
                                disabled={!table.getCanNextPage() || loading}
                                onClick={() => {
                                    table.resetRowSelection();
                                    table.nextPage();
                                }}
                            >
                                <ChevronRight className="size-4" aria-hidden="true" />
                            </Button>
                        </div>
                    </div>
                </div>
            ) : null}

            <Dialog open={confirmDeleteOpen} onOpenChange={setConfirmDeleteOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            Delete {selectedCount} {selectedCount === 1 ? 'record' : 'records'}?
                        </DialogTitle>
                        <DialogDescription>
                            {deleteDescription ??
                                'Deleted rows can be restored later from the same list. This action is applied to every selected row.'}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setConfirmDeleteOpen(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={handleBulkDelete}
                            disabled={loading}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </section>
    );
}
