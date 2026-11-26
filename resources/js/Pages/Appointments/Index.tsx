/**
 * Appointments/Index — the visit list on the shared CRUD table kit.
 *
 * The URL owns page, search, sort, filters and page size; the server applies
 * the same filter DTO to the rows and to the export. Soft-deleted rows are
 * restorable from the row menu, and bulk delete keeps its Base UI confirmation.
 */
import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { CalendarCheck, CalendarPlus, Eye, Pencil, RotateCcw, Trash2 } from 'lucide-react';
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
import { formatDateTime } from '@/lib/date';
import { useBulkActions, useCrudTable } from '@/lib/crud-table';
import { useVisitPending } from '@/lib/visits';
import SavedViewsMenu from '@/Modules/SavedViews/components/SavedViewsMenu';
import { canReschedule, humanize, statusVariant } from './components/appointmentDisplay';

type AppointmentRow = Modules.LeadAppointment.Application.Dtos.AppointmentData;
type Option = { value: string; label: string };

type AppointmentsIndexProps = {
    rows: AppointmentRow[];
    total: number;
    options: {
        statuses: Option[];
        kinds: Option[];
        regions: Option[];
    };
    canCreate: boolean;
    canView: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    canRestore: boolean;
    canExport: boolean;
    canBulkDelete: boolean;
    canBulkRestore: boolean;
};

const whenLabel = (row: AppointmentRow): string =>
    formatDateTime(row.starts_at_local, 'en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: row.timezone,
    });

const appointmentColumns: SimpleColumn<AppointmentRow>[] = [
    {
        accessorKey: 'customer_name',
        header: 'Customer',
        sortable: false,
        className: 'min-w-[180px] max-w-[260px]',
        cell: ({ row }): React.ReactNode => (
            <span className="flex min-w-0 flex-col">
                <span className="flex min-w-0 items-center gap-2">
                    <span className="truncate font-medium text-surface-900 dark:text-surface-0">
                        {row.customer_name}
                    </span>
                    {row.deleted_at !== null && <Tag variant="destructive">Deleted</Tag>}
                </span>
                <span className="truncate text-xs text-surface-500 dark:text-surface-300">
                    {row.customer_phone ?? row.customer_email ?? 'No contact details'}
                </span>
            </span>
        ),
    },
    {
        accessorKey: 'starts_at_local',
        header: 'When',
        sortKey: 'starts_at',
        className: 'whitespace-nowrap tabular-nums',
        cell: ({ row }): React.ReactNode => (
            <span className="flex flex-col">
                <span className="text-surface-700 dark:text-surface-200">{whenLabel(row)}</span>
                <span className="text-xs text-surface-500 dark:text-surface-300">
                    {row.timezone}
                </span>
            </span>
        ),
    },
    {
        accessorKey: 'kind',
        header: 'Kind',
        sortKey: 'kind',
        className: 'whitespace-nowrap',
        hideBelow: 'sm',
        cell: ({ value }): React.ReactNode => (typeof value === 'string' ? humanize(value) : '—'),
    },
    {
        accessorKey: 'status',
        header: 'Status',
        sortKey: 'status',
        className: 'whitespace-nowrap',
        cell: ({ value }): React.ReactNode =>
            typeof value === 'string' ? (
                <Tag variant={statusVariant(value)}>{humanize(value)}</Tag>
            ) : (
                '—'
            ),
    },
    {
        accessorKey: 'address_line',
        header: 'Property',
        sortable: false,
        className: 'max-w-[220px]',
        hideBelow: 'lg',
        cell: ({ value }): React.ReactNode => (
            <span className="block truncate text-surface-600 dark:text-surface-300">
                {typeof value === 'string' && value !== '' ? value : '—'}
            </span>
        ),
    },
    {
        accessorKey: 'region',
        header: 'Region',
        sortable: false,
        className: 'whitespace-nowrap',
        hideBelow: 'md',
        cell: ({ value }): React.ReactNode => (typeof value === 'string' ? humanize(value) : '—'),
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
        sortKey: 'created_at',
        align: 'right',
        width: 'w-38',
        className: 'whitespace-nowrap tabular-nums text-surface-600 dark:text-surface-300',
        hideBelow: 'xl',
        cell: ({ value }): React.ReactNode =>
            typeof value === 'string' ? formatDateTime(value) : '—',
    },
];

export default function AppointmentsIndex() {
    const page = usePage<AppointmentsIndexProps>();
    const {
        rows,
        total,
        options,
        canCreate,
        canView,
        canUpdate,
        canDelete,
        canRestore,
        canExport,
        canBulkDelete,
        canBulkRestore,
    } = page.props;

    const visitsPending = useVisitPending();
    const table = useCrudTable({
        endpoint: '/appointments',
        // Match the server's no-query default (`starts_at`, ascending). The
        // header, rows and export must agree on the first load.
        defaults: { sort: { field: 'starts_at', order: 1 } },
    });
    const bulk = useBulkActions({
        deleteUrl: canBulkDelete ? '/appointments/bulk-delete' : undefined,
        restoreUrl: canBulkRestore ? '/appointments/bulk-restore' : undefined,
    });

    const [pendingDelete, setPendingDelete] = useState<AppointmentRow | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [deleteError, setDeleteError] = useState<string | null>(null);

    const filters: CrudFilterDef[] = [
        {
            key: 'status',
            label: 'Status',
            type: 'select',
            placeholder: 'Any status',
            options: options.statuses,
        },
        {
            key: 'kind',
            label: 'Kind',
            type: 'select',
            placeholder: 'Any kind',
            options: options.kinds,
        },
        {
            key: 'region',
            label: 'Region',
            type: 'select',
            placeholder: 'Any region',
            options: options.regions,
        },
        {
            key: 'record_state',
            label: 'Records',
            type: 'select',
            placeholder: 'Active',
            options: [
                { value: 'trashed', label: 'Trashed' },
                { value: 'all', label: 'All (incl. trashed)' },
            ],
        },
        { key: 'date', label: 'Visit date', type: 'date-range' },
    ];

    const exportFormats: ExportFormat[] = canExport ? ['csv', 'xlsx', 'pdf'] : [];

    const confirmDelete = (): void => {
        if (pendingDelete === null) {
            return;
        }

        setDeleting(true);
        setDeleteError(null);
        router.delete(`/appointments/${pendingDelete.uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                setPendingDelete(null);
            },
            onError: (errors) => {
                setDeleteError(
                    errors.date ??
                        errors.appointment ??
                        'The appointment could not be deleted. Try again.',
                );
            },
            // Keep the dialog open on failure: the message below the copy is
            // the only explanation the operator gets.
            onFinish: () => {
                setDeleting(false);
            },
        });
    };

    return (
        <AppLayout title="Appointments">
            <Head title="Appointments" />

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300">
                        <CalendarCheck className="size-4" aria-hidden="true" />
                        <span>{total} appointments match the current view</span>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {canCreate && (
                            <Button
                                render={<Link href="/appointments/create" />}
                                nativeButton={false}
                                className="h-9 gap-2"
                            >
                                <CalendarPlus className="size-4" aria-hidden="true" />
                                Schedule visit
                            </Button>
                        )}
                    </div>
                </div>

                <DataTableKit
                    {...table.kitProps}
                    columns={appointmentColumns}
                    data={rows}
                    total={total}
                    rowActions={(row) => (
                        <DataTableRowActions
                            label={`Actions for ${row.customer_name}`}
                            actions={[
                                ...(canView
                                    ? [
                                          {
                                              label: 'View',
                                              icon: <Eye className="size-4" aria-hidden="true" />,
                                              onClick: () =>
                                                  router.visit(`/appointments/${row.uuid}`),
                                          },
                                      ]
                                    : []),
                                ...(canUpdate &&
                                row.deleted_at === null &&
                                canReschedule(row.status)
                                    ? [
                                          {
                                              label: 'Reschedule',
                                              icon: (
                                                  <Pencil className="size-4" aria-hidden="true" />
                                              ),
                                              onClick: () =>
                                                  router.visit(`/appointments/${row.uuid}/edit`),
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
                                                          `/appointments/${row.uuid}/restore`,
                                                          {},
                                                          { preserveScroll: true },
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
                    loading={visitsPending || bulk.processing || deleting}
                    title="All appointments"
                    description="Inspections, estimates and job visits, from request to completion."
                    searchPlaceholder="Search customer, phone, email or notes…"
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
                    views={
                        <SavedViewsMenu
                            resource="appointments"
                            state={table.state}
                            onApply={table.apply}
                        />
                    }
                    onResetFilters={table.reset}
                    exportFormats={exportFormats}
                    exportHref={canExport ? table.exportUrl : undefined}
                    selectionLabel={(row) => `${row.customer_name} (${row.kind})`}
                    onBulkDelete={canBulkDelete ? bulk.deleteRows : undefined}
                    onBulkRestore={canBulkRestore ? bulk.restoreRows : undefined}
                    actionError={bulk.error}
                    emptyTitle="No appointments yet"
                    emptyDescription="A visit scheduled from a lead or the dashboard calendar appears here."
                />
            </div>

            <CrudDialog
                open={pendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingDelete(null);
                        setDeleteError(null);
                    }
                }}
                title="Delete this appointment?"
                description={
                    pendingDelete === null
                        ? undefined
                        : `${pendingDelete.customer_name} moves to the trash. The record can be restored from the deleted view.`
                }
                confirmLabel="Delete appointment"
                confirmVariant="destructive"
                onConfirm={confirmDelete}
                loading={deleting}
            >
                <p className="text-sm text-surface-600 dark:text-surface-300">
                    The calendar slot is freed and the lead's status is not changed.
                </p>
                {deleteError !== null && (
                    <p role="alert" className="mt-2 text-sm font-medium text-aq-danger-strong">
                        {deleteError}
                    </p>
                )}
            </CrudDialog>
        </AppLayout>
    );
}
