/**
 * Availability/Index — the company calendar and the day exceptions.
 *
 * The table is a page visit: rows, filters and the export all come from the
 * server, and the URL is the state. The month grid is a client-owned panel
 * (`/availability/calendar`) because it outlives one table visit and must
 * refresh when a weekly rule changes. The weekly hours panel is Inertia state
 * with dialog writes, so a calendar day and its source never disagree.
 */
import { useState, useTransition } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarOff,
    CalendarPlus,
    ChevronLeft,
    ChevronRight,
    Loader2,
    Pencil,
    RefreshCw,
    RotateCcw,
    Trash2,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Tag } from '@/Components/ui/tag';
import {
    CrudDialog,
    CrudFilters,
    DataTableKit,
    DataTableRowActions,
    type CrudFilterDef,
    type ExportFormat,
    type SimpleColumn,
} from '@/Components/DataTable';
import AppLayout from '@/Layouts/AppLayout';
import { formatIsoDate } from '@/lib/date';
import { useBulkActions, useCrudTable } from '@/lib/crud-table';
import { useVisitPending } from '@/lib/visits';
import AvailabilityCalendarBoundary from '@/Modules/LeadAppointment/components/AvailabilityCalendarBoundary';
import { useAvailabilityCalendarInvalidator } from '@/Modules/LeadAppointment/hooks/useAvailabilityCalendar';
import { useSyncHolidays } from '@/Modules/LeadAppointment/hooks/useAvailabilityMutations';
import AvailabilityCalendarPanel, {
    AvailabilityMonthSkeleton,
} from './components/AvailabilityCalendarPanel';
import BulkClosureDialog from './components/BulkClosureDialog';
import { monthBounds, monthLabel, shiftMonth } from './components/calendarMonth';
import RulesPanel from './components/RulesPanel';

type ExceptionRow = Modules.LeadAppointment.Application.Dtos.AvailabilityExceptionData;
type Rule = Modules.LeadAppointment.Application.Dtos.AvailabilityRuleData;
type Option = Shared.Application.DTOs.SelectOptionData;

interface AvailabilityIndexProps {
    [key: string]: unknown;
    rows: ExceptionRow[];
    total: number;
    rules: Rule[];
    trashedRules: Rule[];
    options: {
        availability: Option[];
        sources: Option[];
        recordStates: Option[];
    };
    calendar: {
        from: string;
        to: string;
        today: string;
        timezone: string;
        maxDays: number;
    };
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    canRestore: boolean;
    canExport: boolean;
    canBulkDelete: boolean;
    canBulkRestore: boolean;
    canViewCalendar: boolean;
    canCreateRule: boolean;
    canUpdateRule: boolean;
    canDeleteRule: boolean;
    canRestoreRule: boolean;
}

const dayLabel = (date: string): string =>
    formatIsoDate(date, 'en-US', {
        weekday: 'long',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });

const windowLabel = (row: ExceptionRow): string =>
    row.start_time === null || row.end_time === null ? '—' : `${row.start_time}–${row.end_time}`;

const sourceTag = (source: string): 'default' | 'outline' =>
    source === 'holiday' ? 'default' : 'outline';

/** One sentence for the sync result, without "1 days" or "0 rows replaced". */
const describeSync = (
    result: Modules.LeadAppointment.Application.Dtos.HolidayResyncResultData,
): string => {
    if (result.created === 0) {
        return 'The federal calendar is already up to date for the current and next year.';
    }

    const days = result.created === 1 ? '1 holiday day' : `${result.created} holiday days`;
    const replaced =
        result.purged === 0
            ? ''
            : `, replacing ${result.purged === 1 ? '1 previous row' : `${result.purged} previous rows`}`;

    return `${days} written for the current and next year${replaced}.`;
};

/*
 * Module scope, not a render value: `useServerTable` treats `columns` as a
 * model input, and the adapter's contract is that a page hands it a stable
 * array (see lib/data-table.ts).
 */
const exceptionColumns: SimpleColumn<ExceptionRow>[] = [
    {
        accessorKey: 'date',
        header: 'Day',
        sortKey: 'date',
        className: 'whitespace-nowrap',
        cell: ({ row }): React.ReactNode => (
            <span className="flex items-center gap-2 font-medium whitespace-nowrap text-surface-900 dark:text-surface-0">
                {dayLabel(row.date)}
                {row.deleted_at !== null && <Tag variant="destructive">Deleted</Tag>}
            </span>
        ),
    },
    {
        accessorKey: 'is_available',
        header: 'State',
        sortable: false,
        className: 'whitespace-nowrap',
        cell: ({ row }): React.ReactNode => (
            <Tag variant={row.is_available ? 'success' : 'destructive'}>
                {row.is_available ? 'Open' : 'Closed'}
            </Tag>
        ),
    },
    {
        accessorKey: 'start_time',
        header: 'Window',
        sortable: false,
        className: 'whitespace-nowrap tabular-nums text-surface-600 dark:text-surface-300',
        hideBelow: 'sm',
        cell: ({ row }): React.ReactNode => windowLabel(row),
    },
    {
        accessorKey: 'reason',
        header: 'Reason',
        sortable: false,
        className: 'max-w-[280px]',
        cell: ({ value }): React.ReactNode => (
            <span className="block truncate text-surface-600 dark:text-surface-300">
                {typeof value === 'string' && value !== '' ? value : '—'}
            </span>
        ),
    },
    {
        accessorKey: 'source',
        header: 'Source',
        sortable: false,
        className: 'whitespace-nowrap',
        hideBelow: 'md',
        cell: ({ value }): React.ReactNode =>
            typeof value === 'string' ? (
                <Tag variant={sourceTag(value)}>{value === 'holiday' ? 'Holiday' : 'Manual'}</Tag>
            ) : (
                '—'
            ),
    },
];

export default function AvailabilityIndex() {
    const page = usePage<AvailabilityIndexProps>();
    const {
        rows,
        total,
        rules,
        trashedRules,
        options,
        calendar,
        canCreate,
        canUpdate,
        canDelete,
        canRestore,
        canExport,
        canBulkDelete,
        canBulkRestore,
        canViewCalendar,
        canCreateRule,
        canUpdateRule,
        canDeleteRule,
        canRestoreRule,
    } = page.props;

    const visitsPending = useVisitPending();
    const invalidateCalendar = useAvailabilityCalendarInvalidator();
    const syncHolidays = useSyncHolidays();
    const table = useCrudTable({
        endpoint: '/availability',
        defaults: { sort: { field: 'date', order: 1 } },
    });
    const bulk = useBulkActions({
        deleteUrl: canBulkDelete ? '/availability-exceptions/bulk-delete' : undefined,
        restoreUrl: canBulkRestore ? '/availability-exceptions/bulk-restore' : undefined,
        onSettled: invalidateCalendar,
    });

    const [month, setMonth] = useState(calendar.from);
    const [isMonthPending, startMonthTransition] = useTransition();
    const [closureOpen, setClosureOpen] = useState(false);
    const [pendingDelete, setPendingDelete] = useState<ExceptionRow | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const [syncMessage, setSyncMessage] = useState<string | null>(null);
    const [syncError, setSyncError] = useState<string | null>(null);

    const bounds = monthBounds(month);

    /*
     * The grid links every day to the same route, so the hint has to describe
     * what this caller can actually do there. Everything stays server-checked:
     * the controller only offers the edit form to a caller who may update, and
     * only offers the form at all to a caller who may create.
     */
    const calendarHint =
        canCreate && canUpdate
            ? 'Select a day to block it or to open an extra window. A day that already has an exception opens its edit form.'
            : canCreate
              ? 'Select a day to block it or to open an extra window.'
              : 'Select a day to change its exception. A day that follows the weekly hours needs the create permission.';

    const refresh = (): void => {
        invalidateCalendar();
        router.reload({
            only: ['rows', 'total', 'currentPage', 'perPage', 'rules', 'trashedRules'],
        });
    };

    const changeMonth = (delta: number): void => {
        startMonthTransition(() => setMonth((current) => shiftMonth(current, delta)));
    };

    const goToCurrentMonth = (): void => {
        startMonthTransition(() => setMonth(calendar.from));
    };

    const handleSelectDate = (date: string): void => {
        router.visit(`/availability/create?date=${encodeURIComponent(date)}`);
    };

    const runHolidaySync = (): void => {
        setSyncMessage(null);
        setSyncError(null);

        syncHolidays.mutate(undefined, {
            onSuccess: (result) => {
                setSyncMessage(describeSync(result));
                refresh();
            },
            onError: (cause) => {
                setSyncError(
                    cause instanceof Error && cause.message !== ''
                        ? cause.message
                        : 'The holiday calendar could not be synced. Try again.',
                );
            },
        });
    };

    const confirmDelete = (): void => {
        if (pendingDelete === null) {
            return;
        }

        setDeleting(true);
        setDeleteError(null);

        router.delete(`/availability/${pendingDelete.uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                invalidateCalendar();
                setPendingDelete(null);
            },
            // The dialog stays open on failure: the message below it is the
            // only feedback the operator gets, and closing it would throw the
            // reason away (onFinish runs after onError, always).
            onError: (errors) => {
                setDeleteError(errors.date ?? 'The day could not be removed. Try again.');
            },
            onFinish: () => setDeleting(false),
        });
    };

    const filters: CrudFilterDef[] = [
        {
            key: 'availability',
            label: 'State',
            type: 'select',
            placeholder: 'Open or closed',
            options: options.availability,
        },
        {
            key: 'source',
            label: 'Source',
            type: 'select',
            placeholder: 'Any source',
            options: options.sources,
        },
        {
            key: 'record_state',
            label: 'Records',
            type: 'select',
            placeholder: 'Active',
            options: options.recordStates,
        },
        { key: 'date', label: 'Day', type: 'date-range' },
    ];

    const exportFormats: ExportFormat[] = canExport ? ['csv', 'xlsx', 'pdf'] : [];

    return (
        <AppLayout title="Availability">
            <Head title="Availability" />

            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300">
                        <CalendarOff className="size-4" aria-hidden="true" />
                        <span>{total} exceptions match the current view</span>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {canCreate && (
                            <>
                                {/* The route re-checks CREATE_AVAILABILITY_EXCEPTION and
                                    throttles at 10/min — this is a convenience, not the gate. */}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="header"
                                    onClick={runHolidaySync}
                                    disabled={syncHolidays.isPending || visitsPending}
                                >
                                    {syncHolidays.isPending ? (
                                        <Loader2
                                            className="size-4 animate-spin motion-reduce:animate-none"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <RefreshCw className="size-4" aria-hidden="true" />
                                    )}
                                    {syncHolidays.isPending ? 'Syncing…' : 'Sync holidays'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="header"
                                    onClick={() => setClosureOpen(true)}
                                >
                                    <CalendarOff className="size-4" aria-hidden="true" />
                                    Close a range
                                </Button>
                                <Button
                                    render={<Link href="/availability/create" />}
                                    nativeButton={false}
                                    size="header"
                                >
                                    <CalendarPlus className="size-4" aria-hidden="true" />
                                    New exception
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {syncMessage !== null && (
                    <p
                        role="status"
                        className="rounded-lg border border-aq-success/40 bg-aq-success/5 px-3 py-2 text-sm text-surface-700 dark:text-surface-200"
                    >
                        {syncMessage}
                    </p>
                )}

                {syncError !== null && (
                    <p
                        role="alert"
                        className="rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm text-aq-danger-strong"
                    >
                        {syncError}
                    </p>
                )}

                <section
                    aria-labelledby="availability-calendar-heading"
                    aria-busy={isMonthPending}
                    className="rounded-xl border border-surface-200 bg-surface-0 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-surface-200 px-4 py-3 dark:border-surface-800">
                        <div>
                            <h2
                                id="availability-calendar-heading"
                                className="text-sm font-semibold text-surface-900 dark:text-surface-0"
                            >
                                Company calendar
                            </h2>
                            <p className="mt-0.5 text-xs text-surface-500 dark:text-surface-300">
                                Weekly hours, holidays and closures resolved per day. Times are read
                                in {calendar.timezone}.
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon-sm"
                                aria-label="Previous month"
                                onClick={() => changeMonth(-1)}
                            >
                                <ChevronLeft className="size-4" aria-hidden="true" />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={goToCurrentMonth}
                            >
                                This month
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon-sm"
                                aria-label="Next month"
                                onClick={() => changeMonth(1)}
                            >
                                <ChevronRight className="size-4" aria-hidden="true" />
                            </Button>
                            <span
                                aria-live="polite"
                                className="min-w-40 text-right text-sm font-semibold tabular-nums text-surface-800 dark:text-surface-100"
                            >
                                {monthLabel(month)}
                                {isMonthPending ? ' · loading' : ''}
                            </span>
                        </div>
                    </div>

                    <div className="p-3">
                        {canViewCalendar ? (
                            /*
                             * The key is the visible window, not a mount token:
                             * while the boundary is in its failed state it does
                             * not render its children, so without a key a month
                             * change would keep showing the old error instead of
                             * asking the server for the new month.
                             */
                            <AvailabilityCalendarBoundary
                                key={`${bounds.from}:${bounds.to}`}
                                fallback={<AvailabilityMonthSkeleton />}
                            >
                                <AvailabilityCalendarPanel
                                    from={bounds.from}
                                    to={bounds.to}
                                    today={calendar.today}
                                    /*
                                     * Clicking a day opens `/availability/create`,
                                     * which the controller turns into the edit
                                     * form when a row exists. That route now
                                     * accepts CREATE or UPDATE, so an update-only
                                     * editor gets the edit form instead of a 403;
                                     * the write itself stays on CREATE.
                                     */
                                    canEdit={canCreate || canUpdate}
                                    onSelectDate={handleSelectDate}
                                />
                            </AvailabilityCalendarBoundary>
                        ) : (
                            <p className="px-2 py-8 text-center text-sm text-surface-500 dark:text-surface-300">
                                The month grid needs the availability-rule permission. The table
                                below still lists the days you can manage.
                            </p>
                        )}
                    </div>

                    {canViewCalendar && (canCreate || canUpdate) && (
                        <p className="px-4 pb-3 text-xs text-surface-500 dark:text-surface-300">
                            {calendarHint}
                        </p>
                    )}
                </section>

                {canViewCalendar && (
                    <RulesPanel
                        rules={rules}
                        trashedRules={trashedRules}
                        canCreate={canCreateRule}
                        canUpdate={canUpdateRule}
                        canDelete={canDeleteRule}
                        canRestore={canRestoreRule}
                        onChanged={refresh}
                    />
                )}

                <DataTableKit
                    {...table.kitProps}
                    columns={exceptionColumns}
                    data={rows}
                    total={total}
                    loading={visitsPending || bulk.processing || deleting}
                    title="Days that differ from the weekly hours"
                    description="Holidays synced from the federal calendar and closures the crew reported."
                    searchPlaceholder="Search the reason…"
                    rowState={(row) => (row.deleted_at !== null ? 'deleted' : 'default')}
                    filterCount={table.activeFilterCount}
                    hasActiveFilters={table.hasActiveFilters}
                    filters={
                        <CrudFilters
                            defs={filters}
                            state={table.state}
                            onChange={table.setFilters}
                            disabled={visitsPending}
                        />
                    }
                    onResetFilters={table.reset}
                    exportFormats={exportFormats}
                    exportHref={canExport ? table.exportUrl : undefined}
                    selectionLabel={(row) => `${dayLabel(row.date)} (${row.reason ?? 'no reason'})`}
                    onBulkDelete={canBulkDelete ? bulk.deleteRows : undefined}
                    onBulkRestore={canBulkRestore ? bulk.restoreRows : undefined}
                    actionError={bulk.error}
                    rowActions={(row) => (
                        <DataTableRowActions
                            label={`Actions for ${dayLabel(row.date)}`}
                            actions={[
                                ...(canUpdate
                                    ? [
                                          {
                                              label: 'Edit',
                                              icon: (
                                                  <Pencil className="size-4" aria-hidden="true" />
                                              ),
                                              onClick: () =>
                                                  router.visit(`/availability/${row.uuid}/edit`),
                                          },
                                      ]
                                    : []),
                                ...(row.deleted_at !== null
                                    ? canRestore
                                        ? [
                                              {
                                                  label: 'Restore',
                                                  icon: (
                                                      <RotateCcw
                                                          className="size-4"
                                                          aria-hidden="true"
                                                      />
                                                  ),
                                                  onClick: () =>
                                                      router.patch(
                                                          `/availability/${row.uuid}/restore`,
                                                          {},
                                                          {
                                                              preserveScroll: true,
                                                              onSuccess: invalidateCalendar,
                                                          },
                                                      ),
                                              },
                                          ]
                                        : []
                                    : canDelete
                                      ? [
                                            {
                                                label: 'Delete',
                                                icon: (
                                                    <Trash2 className="size-4" aria-hidden="true" />
                                                ),
                                                destructive: true,
                                                onClick: () => {
                                                    setDeleteError(null);
                                                    setPendingDelete(row);
                                                },
                                            },
                                        ]
                                      : []),
                            ]}
                        />
                    )}
                    emptyTitle="No availability exceptions"
                    emptyDescription="Holidays appear after a sync; a closure reported by the crew appears here as soon as it is saved."
                />
            </div>

            {closureOpen && (
                <BulkClosureDialog
                    open={closureOpen}
                    onOpenChange={setClosureOpen}
                    maxDays={calendar.maxDays}
                    onCreated={refresh}
                />
            )}

            <CrudDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingDelete(null);
                        setDeleteError(null);
                    }
                }}
                title="Delete this availability exception?"
                description={
                    pendingDelete === null
                        ? undefined
                        : `${dayLabel(pendingDelete.date)} returns to the weekly hours (or to closed when there are none).`
                }
                confirmLabel="Delete exception"
                confirmVariant="destructive"
                onConfirm={confirmDelete}
                loading={deleting}
            >
                <p className="text-sm text-surface-600 dark:text-surface-300">
                    Appointments already booked on that day are not cancelled. The row can be
                    restored from the trashed view.
                </p>
                {deleteError !== null && (
                    <p
                        role="alert"
                        className="mt-2 rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm text-aq-danger-strong"
                    >
                        {deleteError}
                    </p>
                )}
            </CrudDialog>
        </AppLayout>
    );
}
