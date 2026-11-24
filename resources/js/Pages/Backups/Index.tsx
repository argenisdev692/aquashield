/**
 * Backups/Index — backup listing on the shared CRUD table kit.
 *
 * Filters, pagination and export all come from `useCrudTable`; bulk
 * quarantine/restore post to the module's JSON endpoints and reload the same
 * URL. Queuing a run goes through `Modules/Backups/hooks/useRunBackup` — the
 * page never touches the network itself (invariant 1) — and answers in place,
 * so the current view is never lost to a redirect.
 */
import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Database, Eye, Package } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Tag } from '@/Components/ui/tag';
import {
    CrudDialog,
    CrudFilters,
    DataTableKit,
    DataTableRowActions,
    TableButton,
    type CrudFilterDef,
    type ExportFormat,
    type SimpleColumn,
} from '@/Components/DataTable';
import AppLayout from '@/Layouts/AppLayout';
import { useBulkActions, useCrudTable } from '@/lib/crud-table';
import { formatDateTime } from '@/lib/date';
import { useVisitPending } from '@/lib/visits';
import { useRunBackup } from '@/Modules/Backups/hooks/useRunBackup';
import SavedViewsMenu from '@/Modules/SavedViews/components/SavedViewsMenu';

type BackupArchive = Modules.Backups.Data.BackupArchiveData;
type BackupFilter = Modules.Backups.Data.BackupFilterData;

type BackupPageProps = {
    rows: BackupArchive[];
    total: number;
    currentPage: number;
    perPage: number;
    lastPage: number;
    filters: BackupFilter;
    disks: string[];
    can: {
        create: boolean;
        delete: boolean;
        bulk_delete: boolean;
        restore: boolean;
        bulk_restore: boolean;
        download: boolean;
        export: boolean;
        view: boolean;
    };
};

/* Stable model input for TanStack Table v9 — see Users/Index.tsx. */
const backupColumns: SimpleColumn<BackupArchive>[] = [
    {
        accessorKey: 'filename',
        header: 'Archive',
        cell: ({ row }) => (
            <div className="flex min-w-0 items-center gap-2">
                <Package className="size-4 text-surface-400" aria-hidden="true" />
                <div className="min-w-0">
                    <p className="truncate font-mono text-sm font-medium text-surface-900 dark:text-surface-0">
                        {row.filename}
                    </p>
                    <div className="mt-1 flex flex-wrap items-center gap-1">
                        {row.is_newest && <Tag variant="default">Newest</Tag>}
                        {row.is_trashed && <Tag variant="warning">Quarantined</Tag>}
                    </div>
                </div>
            </div>
        ),
    },
    {
        accessorKey: 'disk',
        header: 'Disk',
        className: 'whitespace-nowrap',
        hideBelow: 'md',
    },
    {
        accessorKey: 'taken_at',
        header: 'Taken',
        align: 'right',
        width: 'w-44',
        className: 'whitespace-nowrap tabular-nums',
        hideBelow: 'sm',
        cell: ({ row }) => formatDateTime(row.taken_at),
    },
    // Displayed as a human label; sorted by the numeric field the server allows.
    {
        accessorKey: 'size_human',
        sortKey: 'size_bytes',
        header: 'Size',
        align: 'right',
        width: 'w-24',
        className: 'whitespace-nowrap tabular-nums',
        hideBelow: 'xl',
    },
    {
        accessorKey: 'age_days',
        header: 'Age',
        sortable: false,
        align: 'right',
        width: 'w-20',
        className: 'whitespace-nowrap tabular-nums',
        hideBelow: 'xl',
        cell: ({ row }) => <span>{row.age_days === 0 ? 'Today' : `${row.age_days} d`}</span>,
    },
];

export default function BackupsIndex() {
    const page = usePage<BackupPageProps>();
    const { rows, total, disks, can } = page.props;
    const visitsPending = useVisitPending();
    const table = useCrudTable({
        endpoint: '/backups',
        defaults: { sort: { field: 'taken_at', order: -1 } },
    });
    const bulk = useBulkActions({
        deleteUrl: can.bulk_delete ? '/backups/bulk-delete' : undefined,
        restoreUrl: can.bulk_restore ? '/backups/bulk-restore' : undefined,
    });

    const [confirmRunOpen, setConfirmRunOpen] = useState(false);
    const {
        run,
        pending: runPending,
        status: runStatus,
        error: runError,
        reset: resetRun,
    } = useRunBackup();
    const runQueued = runStatus === 'queued';

    /*
     * Every entry point resets the hook first. A late response from a dialog the
     * user already dismissed therefore cannot greet the next attempt with a
     * stale "queued" or "failed" message.
     */
    const openRunDialog = (): void => {
        resetRun();
        setConfirmRunOpen(true);
    };

    const filters: CrudFilterDef[] = [
        {
            key: 'status',
            label: 'Status',
            type: 'select',
            placeholder: 'Active',
            options: [
                { value: 'trashed', label: 'Quarantined' },
                { value: 'all', label: 'All (incl. quarantined)' },
            ],
        },
        {
            key: 'disk',
            label: 'Destination',
            type: 'select',
            placeholder: 'Any disk',
            options: disks.map((disk) => ({ value: disk, label: disk })),
        },
        { key: 'taken', label: 'Taken', type: 'date-range' },
    ];

    const exportFormats: ExportFormat[] = can.export ? ['csv', 'xlsx', 'pdf'] : [];

    const selectionLabel = (row: BackupArchive): string => `${row.filename} (${row.disk})`;

    const runBackupAction = can.create ? (
        <Button onClick={openRunDialog} disabled={visitsPending || runPending} className="h-9">
            <Database className="size-4" aria-hidden="true" />
            Run backup
        </Button>
    ) : null;

    const emptyActionContent = table.hasActiveFilters ? (
        <TableButton variant="outline" onClick={table.reset}>
            Clear filters
        </TableButton>
    ) : can.create ? (
        <Button onClick={openRunDialog} variant="outline">
            Run the first backup
        </Button>
    ) : null;

    return (
        <AppLayout title="Database backups">
            <Head title="Database backups" />

            <DataTableKit
                {...table.kitProps}
                columns={backupColumns}
                data={rows}
                total={total}
                loading={visitsPending || bulk.processing || runPending}
                title="Database backups"
                description="Database-only dumps. Quarantine is reversible; the newest archive is protected."
                rowState={(row) => (row.is_trashed ? 'deleted' : 'default')}
                rowActions={
                    can.view
                        ? (row) => (
                              <DataTableRowActions
                                  label={`Actions for ${row.filename}`}
                                  actions={[
                                      {
                                          label: 'View',
                                          icon: <Eye className="size-4" aria-hidden="true" />,
                                          onClick: () => router.visit(`/backups/${row.uuid}`),
                                      },
                                  ]}
                              />
                          )
                        : undefined
                }
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
                    <SavedViewsMenu resource="backups" state={table.state} onApply={table.apply} />
                }
                onResetFilters={table.reset}
                exportFormats={exportFormats}
                exportHref={can.export ? table.exportUrl : undefined}
                selectionLabel={selectionLabel}
                onBulkDelete={can.bulk_delete ? bulk.deleteRows : undefined}
                onBulkRestore={can.bulk_restore ? bulk.restoreRows : undefined}
                actionError={bulk.error}
                actions={runBackupAction}
                emptyAction={emptyActionContent}
            />

            <CrudDialog
                open={confirmRunOpen}
                onOpenChange={setConfirmRunOpen}
                title={runQueued ? 'Backup queued' : 'Run a database backup?'}
                description={
                    runQueued
                        ? 'The dump is running in the background; the scheduled run is unaffected.'
                        : 'This queues a database-only dump and writes a new archive to the backup disk.'
                }
                confirmLabel="Run backup"
                confirmHidden={runQueued}
                cancelLabel={runQueued ? 'Close' : 'Cancel'}
                onConfirm={() => {
                    void run();
                }}
                loading={runPending}
            >
                {runQueued ? (
                    <div role="status" className="flex flex-col gap-2">
                        <p className="text-sm text-surface-600 dark:text-surface-300">
                            The archive appears at the top of this list once the worker finishes the
                            dump. You can keep working while it runs.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-2">
                        <p className="text-sm text-surface-600 dark:text-surface-300">
                            The newest archive is protected until the next successful run.
                            Quarantine stays reversible, and no existing archive is touched.
                        </p>
                        {runError ? (
                            <p role="alert" className="text-sm font-medium text-destructive">
                                {runError}
                            </p>
                        ) : null}
                    </div>
                )}
            </CrudDialog>
        </AppLayout>
    );
}
