/**
 * AccessControl/Roles/Index — server-first role catalogue.
 *
 * The URL owns search, filters, sort and page (useCrudTable). The table never
 * filters or paginates in memory. Delete is the only irreversible action, so it
 * goes through a Base UI dialog and the server re-checks system/assigned guards.
 */
import { useState } from 'react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
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
import { useCrudTable } from '@/lib/crud-table';
import { formatDateTime } from '@/lib/date';
import { useVisitPending } from '@/lib/visits';
import { usePermissionCacheReset } from '@/Modules/AccessControl/hooks/usePermissionCacheReset';

type Role = Modules.AccessControl.Data.RoleData;
type RoleFilter = Modules.AccessControl.Data.RoleFilterData;
type FilterOptions = Modules.AccessControl.Data.FilterOptionsData;

type RolesPageProps = {
    rows: Role[];
    total: number;
    currentPage: number;
    perPage: number;
    filters: RoleFilter;
    filterOptions: FilterOptions;
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    canExport: boolean;
    canResetCache: boolean;
};

const roleColumns: SimpleColumn<Role>[] = [
    {
        accessorKey: 'name',
        header: 'Role',
        cell: ({ row }) => (
            <div className="flex min-w-0 flex-col gap-1">
                <span className="truncate font-mono text-sm font-medium text-surface-900 dark:text-surface-0">
                    {row.name}
                </span>
                <Tag variant={row.is_system ? 'outline' : 'default'} className="w-fit">
                    {row.is_system ? 'system' : 'custom'}
                </Tag>
            </div>
        ),
    },
    {
        accessorKey: 'permissions_count',
        header: 'Permissions',
        align: 'right',
        width: 'w-32',
        className: 'tabular-nums',
    },
    {
        accessorKey: 'users_count',
        header: 'Users',
        align: 'right',
        width: 'w-24',
        className: 'tabular-nums',
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
        align: 'right',
        width: 'w-44',
        hideBelow: 'sm',
        className: 'whitespace-nowrap tabular-nums',
        cell: ({ row }) => formatDateTime(row.created_at),
    },
];

export default function RolesIndex() {
    const page = usePage<RolesPageProps>();
    const {
        rows,
        total,
        filterOptions,
        canCreate,
        canUpdate,
        canDelete,
        canExport,
        canResetCache,
    } = page.props;

    const visitsPending = useVisitPending();
    const table = useCrudTable({
        endpoint: '/roles',
        defaults: { sort: { field: 'name', order: 1 } },
    });

    const [deleteTarget, setDeleteTarget] = useState<Role | null>(null);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const { reset: resetCache, resetting } = usePermissionCacheReset();
    const deleteForm = useForm({});

    const filters: CrudFilterDef[] = [
        {
            key: 'type',
            label: 'Type',
            type: 'select',
            placeholder: 'Any type',
            options: [
                { value: 'system', label: 'System' },
                { value: 'custom', label: 'Custom' },
            ],
        },
        {
            key: 'permission',
            label: 'Permission',
            type: 'select',
            placeholder: 'Any permission',
            searchable: true,
            options: filterOptions.permissions.map((permission) => ({
                value: permission,
                label: permission,
            })),
        },
        { key: 'created', label: 'Created', type: 'date-range' },
    ];

    const exportFormats: ExportFormat[] = canExport ? ['csv', 'xlsx', 'pdf'] : [];

    const confirmDelete = (): void => {
        if (deleteTarget === null) {
            return;
        }

        deleteForm.delete(`/roles/${deleteTarget.uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                setDeleteError(null);
            },
            onError: (errors) => {
                setDeleteError(Object.values(errors)[0] ?? 'The role could not be deleted.');
            },
        });
    };

    return (
        <AppLayout title="Roles">
            <Head title="Roles" />

            <DataTableKit
                {...table.kitProps}
                columns={roleColumns}
                data={rows}
                total={total}
                loading={visitsPending}
                title="Roles"
                description="The role catalogue and the permission set each role carries. System roles keep their names; their permissions remain editable."
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
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        {canResetCache ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="toolbar"
                                onClick={resetCache}
                                disabled={resetting}
                            >
                                <RefreshCw
                                    className={resetting ? 'size-3.5 animate-spin' : 'size-3.5'}
                                    aria-hidden="true"
                                />
                                Flush cache
                            </Button>
                        ) : null}
                        {canCreate ? (
                            <Button
                                type="button"
                                size="toolbar"
                                onClick={() => router.visit('/roles/create')}
                            >
                                <Plus className="size-3.5" aria-hidden="true" />
                                New role
                            </Button>
                        ) : null}
                    </div>
                }
                rowActions={(row) => (
                    <DataTableRowActions
                        label={`Actions for ${row.name}`}
                        actions={[
                            ...(canUpdate
                                ? [
                                      {
                                          label: 'Edit',
                                          icon: <Pencil className="size-4" aria-hidden="true" />,
                                          onClick: () => router.visit(`/roles/${row.uuid}/edit`),
                                      },
                                  ]
                                : []),
                            ...(canDelete && !row.is_system
                                ? [
                                      {
                                          label: 'Delete',
                                          destructive: true,
                                          icon: <Trash2 className="size-4" aria-hidden="true" />,
                                          onClick: () => {
                                              setDeleteError(null);
                                              setDeleteTarget(row);
                                          },
                                      },
                                  ]
                                : []),
                        ]}
                    />
                )}
                emptyAction={
                    canCreate ? (
                        <Button size="sm" onClick={() => router.visit('/roles/create')}>
                            <Plus className="mr-1.5 size-3.5" aria-hidden="true" />
                            New role
                        </Button>
                    ) : null
                }
            />

            <CrudDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                        setDeleteError(null);
                    }
                }}
                title="Delete role"
                description="The role and its permission links are removed. This cannot be undone."
                confirmLabel="Delete role"
                confirmVariant="destructive"
                onConfirm={confirmDelete}
                loading={deleteForm.processing}
                confirmDisabled={deleteTarget === null}
            >
                <p className="text-sm text-surface-700 dark:text-surface-200">
                    Delete <strong className="font-mono">{deleteTarget?.name}</strong>?
                </p>
                <p className="mt-2 text-xs text-surface-600 dark:text-surface-300">
                    A role assigned to users cannot be deleted until those users are reassigned.
                </p>
                {deleteError ? (
                    <p role="alert" className="mt-3 text-sm font-medium text-aq-danger-strong">
                        {deleteError}
                    </p>
                ) : null}
            </CrudDialog>
        </AppLayout>
    );
}
