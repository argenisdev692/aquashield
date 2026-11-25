/**
 * Availability/Index — the company calendar and the day exceptions.
 *
 * The table is a page visit: rows, filters and the export all come from the
 * server, and the URL is the state. The month grid is a client-owned panel
 * (`/availability/calendar`) because it outlives one table visit and must
 * refresh when a weekly rule changes. The weekly hours panel is Inertia state
 * with dialog writes, so a calendar day and its source never disagree.
 */
import { Suspense, useState, useTransition } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarOff,
    CalendarPlus,
    ChevronLeft,
    ChevronRight,
    Pencil,
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
import { useAvailabilityCalendarInvalidator } from '@/Modules/LeadAppointment/hooks/useAvailabilityCalendar';
import AvailabilityCalendarPanel, {
    AvailabilityMonthSkeleton,
} from './components/AvailabilityCalendarPanel';
import BulkClosureDialog from './components/BulkClosureDialog';
import { monthBounds, monthLabel, shiftMonth } from './components/calendarMonth';
import RulesPanel from './components/RulesPanel';

type ExceptionRow = Modules.LeadAppointment.Application.Dtos.AvailabilityExceptionData;
type Rule = Modules.LeadAppointment.Application.Dtos.AvailabilityRuleData;
type Option = { value: string; label: string };

interface AvailabilityIndexProps {
    [key: string]: unknown;
    rows: ExceptionRow[];
    total: number;
    rules: Rule[];
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

export default function AvailabilityIndex() {
    const page = usePage<AvailabilityIndexProps>();
    const {
        rows,
        total,
        rules,
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
    } = page.props;

    const visitsPending = useVisitPending();
    const invalidateCalendar = useAvailabilityCalendarInvalidator();
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

    const bounds = monthBounds(month);

    const refresh = (): void => {
        invalidateCalendar();
        router.reload({ only: ['rows', 'total', 'currentPage', 'perPage', 'rules'] });
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

    const columns: SimpleColumn<ExceptionRow>[] = [
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
                    <Tag variant={sourceTag(value)}>
                        {value === 'holiday' ? 'Holiday' : 'Manual'}
                    </Tag>
                ) : (
                    '—'
                ),
        },
    ];

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

    const confirmDelete = (): void => {
        if (pendingDelete === null) {
            return;
        }

        setDeleting(true);
        setDeleteError(null);

        router.delete(`/availability/${pendingDelete.uuid}`, {
            preserveScroll: true,
            onSuccess: invalidateCalendar,
            onError: (errors) => {
                setDeleteError(errors.date ?? 'The day could not be removed. Try again.');
            },
            onFinish: () => {
                setDeleting(false);
                setPendingDelete(null);
            },
        });
    };

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
                                <Button
                                    type="button"
                                    variant="outline"
                                    className="h-9 gap-2"
                                    onClick={() => setClosureOpen(true)}
                                >
                                    <CalendarOff className="size-4" aria-hidden="true" />
                                    Close a range
                                </Button>
                                <Button
                                    render={<Link href="/availability/create" />}
                                    nativeButton={false}
                                    className="h-9 gap-2"
                                >
                                    <CalendarPlus className="size-4" aria-hidden="true" />
                                    New exception
                                </Button>
                            </>
                        )}
                    </div>
                </div>

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
                            <p className="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
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
                                className="h-8"
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
                            <Suspense fallback={<AvailabilityMonthSkeleton />}>
                                <AvailabilityCalendarPanel
                                    from={bounds.from}
                                    to={bounds.to}
                                    today={calendar.today}
                                    canEdit={canCreate}
                                    onSelectDate={handleSelectDate}
                                />
                            </Suspense>
                        ) : (
                            <p className="px-2 py-8 text-center text-sm text-surface-500 dark:text-surface-400">
                                The month grid needs the availability-rule permission. The table
                                below still lists the days you can manage.
                            </p>
                        )}
                    </div>

                    {canCreate && canViewCalendar && (
                        <p className="px-4 pb-3 text-xs text-surface-500 dark:text-surface-400">
                            Select a day to block it or to open an extra window.
                        </p>
                    )}
                </section>

                {canViewCalendar && (
                    <RulesPanel
                        rules={rules}
                        canCreate={canCreateRule}
                        canUpdate={canUpdateRule}
                        canDelete={canDeleteRule}
                        onChanged={refresh}
                    />
                )}

                <DataTableKit
                    {...table.kitProps}
                    columns={columns}
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
                                                onClick: () => setPendingDelete(row),
                                            },
                                        ]
                                      : []),
                            ]}
                        />
                    )}
                    emptyTitle="No availability exceptions"
                    emptyDescription="Holidays appear after the sync; a closure reported by the crew appears here as soon as it is saved."
                />
            </div>

            <BulkClosureDialog
                open={closureOpen}
                onOpenChange={setClosureOpen}
                maxDays={calendar.maxDays}
                onCreated={refresh}
            />

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
