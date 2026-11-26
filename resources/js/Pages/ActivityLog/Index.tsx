/**
 * ActivityLog/Index — read-only audit trail on the shared CRUD table kit.
 *
 * The URL owns search, filters, sort and page through `useCrudTable`. The log
 * has no bulk actions, so the kit renders no checkbox column at all.
 */
import { Head, router, usePage } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { Tag } from '@/Components/ui/tag';
import {
    CrudFilters,
    DataTableKit,
    DataTableRowActions,
    TableButton,
    type CrudFilterDef,
    type ExportFormat,
    type SimpleColumn,
} from '@/Components/DataTable';
import AppLayout from '@/Layouts/AppLayout';
import { useCrudTable } from '@/lib/crud-table';
import { formatDateTime } from '@/lib/date';
import { useVisitPending } from '@/lib/visits';
import SavedViewsMenu from '@/Modules/SavedViews/components/SavedViewsMenu';

type ActivityEntry = Modules.ActivityLog.Data.ActivityEntryData;

type ActivityPageProps = {
    rows: ActivityEntry[];
    total: number;
    currentPage: number;
    perPage: number;
    canView: boolean;
    canExport: boolean;
};

const eventSeverity = (event: string): 'default' | 'destructive' | 'success' | 'warning' => {
    if (/error|failed|exception/i.test(event)) return 'destructive';
    if (/created|restored|backup/i.test(event)) return 'success';
    if (/deleted|removed|quarantine/i.test(event)) return 'warning';
    return 'default';
};

/* Stable model input for TanStack Table v9 — see Users/Index.tsx. */
const activityColumns: SimpleColumn<ActivityEntry>[] = [
    {
        accessorKey: 'event',
        header: 'Event',
        className: 'whitespace-nowrap',
        cell: ({ row }) => <Tag variant={eventSeverity(row.event)}>{row.event}</Tag>,
    },
    // Only created_at, event and log_name are server-sortable.
    {
        accessorKey: 'description',
        header: 'Description',
        sortable: false,
        className: 'max-w-[320px]',
        hideBelow: 'xl',
        cell: ({ row }) => (
            <span className="block truncate" title={row.description}>
                {row.description}
            </span>
        ),
    },
    {
        accessorKey: 'log_name',
        header: 'Log',
        className: 'whitespace-nowrap',
        hideBelow: 'md',
    },
    {
        accessorKey: 'causer_name',
        header: 'Causer',
        sortable: false,
        className: 'whitespace-nowrap',
        hideBelow: 'md',
    },
    {
        accessorKey: 'created_at',
        header: 'Recorded',
        align: 'right',
        width: 'w-44',
        className: 'whitespace-nowrap tabular-nums',
        hideBelow: 'sm',
        cell: ({ row }) => formatDateTime(row.created_at),
    },
];

export default function ActivityLogIndex() {
    const page = usePage<ActivityPageProps>();
    const { rows, total, canView, canExport } = page.props;
    const visitsPending = useVisitPending();
    const table = useCrudTable({
        endpoint: '/activity',
        defaults: { sort: { field: 'created_at', order: -1 } },
    });

    const filters: CrudFilterDef[] = [
        {
            key: 'log_name',
            label: 'Log (exact)',
            type: 'text',
            placeholder: 'backups',
        },
        {
            key: 'event',
            label: 'Event',
            type: 'text',
            placeholder: 'backup.quarantined',
        },
        { key: 'recorded', label: 'Recorded', type: 'date-range' },
    ];

    const exportFormats: ExportFormat[] = canExport ? ['csv', 'xlsx', 'pdf'] : [];

    const emptyActionContent = table.hasActiveFilters ? (
        <TableButton variant="outline" onClick={table.reset}>
            Clear filters
        </TableButton>
    ) : null;

    return (
        <AppLayout title="Activity log">
            <Head title="Activity log" />

            <DataTableKit
                {...table.kitProps}
                columns={activityColumns}
                data={rows}
                total={total}
                perPageOptions={[10, 15, 25, 50, 100]}
                rowActions={
                    canView
                        ? (row) => (
                              <DataTableRowActions
                                  label={`View entry ${row.event}`}
                                  actions={[
                                      {
                                          label: 'View',
                                          icon: <Eye className="size-4" aria-hidden="true" />,
                                          onClick: () => router.visit(`/activity/${row.uuid}`),
                                      },
                                  ]}
                              />
                          )
                        : undefined
                }
                loading={visitsPending}
                title="Recorded events"
                description="Read-only audit trail. Entries are append-only and retained per the audit policy."
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
                    <SavedViewsMenu resource="activity" state={table.state} onApply={table.apply} />
                }
                onResetFilters={table.reset}
                exportFormats={exportFormats}
                exportHref={canExport ? table.exportUrl : undefined}
                emptyAction={emptyActionContent}
            />
        </AppLayout>
    );
}
