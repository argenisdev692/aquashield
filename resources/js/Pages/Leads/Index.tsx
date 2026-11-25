/**
 * Leads/Index — lead listing on the shared CRUD table kit.
 *
 * The URL owns the table state through `useCrudTable`; the server applies the
 * same filters to the rows and to the export. The board is a sibling page, not
 * a client-side regroup of this paginated list.
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Eye, KanbanSquare, Pencil, Plus, Users } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Tag } from '@/Components/ui/tag';
import {
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

type LeadRow = Modules.LeadAppointment.Application.Dtos.LeadListItemData;

type Option = { value: string; label: string };

type LeadIndexProps = {
    rows: LeadRow[];
    total: number;
    options: {
        statuses: Option[];
        sources: Option[];
        regions: Option[];
        owners: Modules.LeadAppointment.Application.Dtos.AssignableOwnerData[];
    };
    canCreate: boolean;
    canView: boolean;
    canUpdate: boolean;
    canExport: boolean;
    canBulkDelete: boolean;
    canBulkRestore: boolean;
};

const humanize = (value: string): string => {
    const label = value.replace(/_/g, ' ');

    return label.charAt(0).toUpperCase() + label.slice(1);
};

const statusVariant = (status: string): 'success' | 'warning' | 'destructive' | 'outline' => {
    if (status === 'won') {
        return 'success';
    }

    if (status === 'lost') {
        return 'destructive';
    }

    if (status === 'inspection_scheduled' || status === 'qualified') {
        return 'warning';
    }

    return 'outline';
};

const leadColumns: SimpleColumn<LeadRow>[] = [
    {
        accessorKey: 'full_name',
        header: 'Lead',
        sortKey: 'first_name',
        className: 'min-w-[160px] max-w-[240px] sm:min-w-[180px]',
        cell: ({ row }): React.ReactNode => (
            <span className="flex min-w-0 flex-col">
                <span className="flex min-w-0 items-center gap-2">
                    <span className="truncate font-medium text-surface-900 dark:text-surface-0">
                        {row.full_name}
                    </span>
                    {row.is_emergency && <Tag variant="destructive">Emergency</Tag>}
                    {row.deleted_at !== null && <Tag variant="destructive">Deleted</Tag>}
                </span>
                <span className="truncate text-xs text-surface-500 sm:hidden dark:text-surface-300">
                    {row.phone ?? row.email ?? '—'}
                </span>
            </span>
        ),
    },
    {
        accessorKey: 'phone',
        header: 'Phone',
        sortable: false,
        className: 'whitespace-nowrap tabular-nums',
        hideBelow: 'sm',
        cell: ({ value }): React.ReactNode => (
            <span className="text-surface-600 dark:text-surface-300">
                {typeof value === 'string' && value !== '' ? value : '—'}
            </span>
        ),
    },
    {
        accessorKey: 'status',
        header: 'Stage',
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
        accessorKey: 'source',
        header: 'Source',
        sortKey: 'source',
        className: 'whitespace-nowrap',
        hideBelow: 'sm',
        cell: ({ value }): React.ReactNode =>
            typeof value === 'string' ? (
                <span className="text-surface-600 dark:text-surface-300">{humanize(value)}</span>
            ) : (
                '—'
            ),
    },
    {
        accessorKey: 'city',
        header: 'Region',
        sortable: false,
        className: 'max-w-[150px]',
        hideBelow: 'md',
        cell: ({ row }): React.ReactNode => (
            <span className="block truncate text-surface-600 dark:text-surface-300">
                {[row.city, row.state].filter(Boolean).join(', ') || '—'}
            </span>
        ),
    },
    {
        accessorKey: 'owner_name',
        header: 'Owner',
        sortable: false,
        className: 'max-w-[150px]',
        hideBelow: 'lg',
        cell: ({ value }): React.ReactNode => (
            <span className="block truncate text-surface-600 dark:text-surface-300">
                {typeof value === 'string' && value !== '' ? value : 'Unassigned'}
            </span>
        ),
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
        sortKey: 'created_at',
        align: 'right',
        width: 'w-38',
        className: 'whitespace-nowrap tabular-nums text-surface-600 dark:text-surface-300',
        hideBelow: 'xl',
        cell: ({ row }): React.ReactNode => formatDateTime(row.created_at),
    },
];

export default function LeadsIndex() {
    const page = usePage<LeadIndexProps>();
    const {
        rows,
        total,
        options,
        canCreate,
        canView,
        canUpdate,
        canExport,
        canBulkDelete,
        canBulkRestore,
    } = page.props;

    const visitsPending = useVisitPending();
    const table = useCrudTable({
        endpoint: '/leads',
        defaults: { sort: { field: 'created_at', order: -1 } },
    });
    const bulk = useBulkActions({
        deleteUrl: canBulkDelete ? '/leads/bulk-delete' : undefined,
        restoreUrl: canBulkRestore ? '/leads/bulk-restore' : undefined,
    });

    const filters: CrudFilterDef[] = [
        {
            key: 'status',
            label: 'Stage',
            type: 'select',
            placeholder: 'Any stage',
            options: options.statuses,
        },
        {
            key: 'source',
            label: 'Source',
            type: 'select',
            placeholder: 'Any source',
            options: options.sources,
        },
        {
            key: 'region',
            label: 'Region',
            type: 'select',
            placeholder: 'Any region',
            options: options.regions,
        },
        {
            key: 'emergency',
            label: 'Priority',
            type: 'select',
            placeholder: 'Any priority',
            options: [
                { value: 'yes', label: 'Emergency' },
                { value: 'no', label: 'Standard' },
            ],
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
        { key: 'created', label: 'Created', type: 'date-range' },
    ];

    const exportFormats: ExportFormat[] = canExport ? ['csv', 'xlsx', 'pdf'] : [];

    const selectionLabel = (row: LeadRow): string =>
        `${row.full_name} (${row.phone ?? 'no phone'})`;

    return (
        <AppLayout title="Leads">
            <Head title="Leads" />

            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300">
                        <Users className="size-4" aria-hidden="true" />
                        <span>{total} leads match the current view</span>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            render={<Link href="/leads/board" />}
                            nativeButton={false}
                            variant="outline"
                            className="h-9 gap-2"
                        >
                            <KanbanSquare className="size-4" aria-hidden="true" />
                            Pipeline board
                        </Button>
                        {canCreate && (
                            <Button
                                render={<Link href="/leads/create" />}
                                nativeButton={false}
                                className="h-9 gap-2"
                            >
                                <Plus className="size-4" aria-hidden="true" />
                                Add lead
                            </Button>
                        )}
                    </div>
                </div>

                <DataTableKit
                    {...table.kitProps}
                    columns={leadColumns}
                    data={rows}
                    total={total}
                    rowActions={(row) => (
                        <DataTableRowActions
                            label={`Actions for ${row.full_name}`}
                            actions={[
                                ...(canView
                                    ? [
                                          {
                                              label: 'View',
                                              icon: <Eye className="size-4" aria-hidden="true" />,
                                              onClick: () => router.visit(`/leads/${row.uuid}`),
                                          },
                                      ]
                                    : []),
                                ...(canUpdate
                                    ? [
                                          {
                                              label: 'Edit',
                                              icon: (
                                                  <Pencil className="size-4" aria-hidden="true" />
                                              ),
                                              onClick: () =>
                                                  router.visit(`/leads/${row.uuid}/edit`),
                                          },
                                      ]
                                    : []),
                            ]}
                        />
                    )}
                    loading={visitsPending || bulk.processing}
                    title="All leads"
                    description="Meta Ads, website and staff-entered leads, from first contact to inspection."
                    searchPlaceholder="Search name, company, email, phone, address or city…"
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
                            resource="leads"
                            state={table.state}
                            onApply={table.apply}
                        />
                    }
                    onResetFilters={table.reset}
                    exportFormats={exportFormats}
                    exportHref={canExport ? table.exportUrl : undefined}
                    selectionLabel={selectionLabel}
                    onBulkDelete={canBulkDelete ? bulk.deleteRows : undefined}
                    onBulkRestore={canBulkRestore ? bulk.restoreRows : undefined}
                    actionError={bulk.error}
                    emptyTitle="No leads yet"
                    emptyDescription="A Meta Ads lead, a website form or a staff entry will appear here."
                />
            </div>
        </AppLayout>
    );
}
