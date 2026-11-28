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
import {
    CrudFilters,
    DataTableKit,
    DataTableRowActions,
    type CrudFilterDef,
    type ExportFormat,
} from '@/Components/DataTable';
import AppLayout from '@/Layouts/AppLayout';
import { useBulkActions, useCrudTable } from '@/lib/crud-table';
import { useVisitPending } from '@/lib/visits';
import { leadColumns } from '@/Modules/LeadAppointment/components/leadColumns';
import { useLeadAppointmentInvalidator } from '@/Modules/LeadAppointment/hooks/useLeadAppointmentInvalidator';
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
    const invalidateLeadAppointment = useLeadAppointmentInvalidator();
    const table = useCrudTable({
        endpoint: '/leads',
        defaults: { sort: { field: 'created_at', order: -1 } },
    });
    const bulk = useBulkActions({
        deleteUrl: canBulkDelete ? '/leads/bulk-delete' : undefined,
        restoreUrl: canBulkRestore ? '/leads/bulk-restore' : undefined,
        onSettled: invalidateLeadAppointment,
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
        /*
         * Won uses `converted_at`, not `created_at`: the KPI drill-down and a
         * business user filtering won deals both need the conversion window.
         */
        {
            key: 'converted',
            label: 'Won date',
            type: 'date-range',
            fromKey: 'converted_from',
            toKey: 'converted_to',
        },
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
                                ...(canUpdate && row.deleted_at === null
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
