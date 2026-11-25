/**
 * AccessControl/Permissions/Index — the permission catalogue.
 *
 * One editable field means create/edit are dialogs, not pages. The seeded
 * catalogue is `is_system` and read-only; custom rows can be renamed, and
 * deleted only while unassigned.
 */
import { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';
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
import { accessControlUrls } from '@/Modules/AccessControl/endpoints';
import { usePermissionCacheReset } from '@/Modules/AccessControl/hooks/usePermissionCacheReset';
import PermissionDialog from './components/PermissionDialog';

type Permission = Modules.AccessControl.Data.PermissionData;
type PermissionFilter = Modules.AccessControl.Data.PermissionFilterData;
type FilterOptions = Modules.AccessControl.Data.FilterOptionsData;

type PermissionsPageProps = {
    rows: Permission[];
    total: number;
    currentPage: number;
    perPage: number;
    filters: PermissionFilter;
    filterOptions: FilterOptions;
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    canExport: boolean;
    canResetCache: boolean;
};

type DialogState = { mode: 'create' } | { mode: 'edit'; permission: Permission } | null;

const permissionColumns: SimpleColumn<Permission>[] = [
    {
        accessorKey: 'name',
        header: 'Permission',
        hidable: false,
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
        accessorKey: 'group',
        header: 'Group',
        sortable: false,
        width: 'w-44',
        cell: ({ row }) => (
            <Tag variant="outline" className="font-mono">
                {row.group}
            </Tag>
        ),
    },
    {
        accessorKey: 'roles_count',
        header: 'Roles',
        align: 'right',
        width: 'w-24',
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

export default function PermissionsIndex() {
    const page = usePage<PermissionsPageProps>();
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

    const table = useCrudTable({
        endpoint: accessControlUrls.permissions,
        defaults: { sort: { field: 'name', order: 1 } },
    });
    const visitsPending = table.pending;

    const [dialog, setDialog] = useState<DialogState>(null);
    const [deleteTarget, setDeleteTarget] = useState<Permission | null>(null);
    const [deleteError, setDeleteError] = useState<string | null>(null);
    const { reset: resetCache, resetting } = usePermissionCacheReset();
    const deleteForm = useForm({ return_to: page.url });

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
            key: 'group',
            label: 'Group',
            type: 'select',
            placeholder: 'Any group',
            searchable: true,
            options: filterOptions.groups.map((group) => ({ value: group, label: group })),
        },
        {
            key: 'role',
            label: 'Role',
            type: 'select',
            placeholder: 'Any role',
            options: filterOptions.roles.map((role) => ({ value: role, label: role })),
        },
        { key: 'created', label: 'Created', type: 'date-range' },
    ];

    const exportFormats: ExportFormat[] = canExport ? ['csv', 'xlsx', 'pdf'] : [];

    const confirmDelete = (): void => {
        if (deleteTarget === null) {
            return;
        }

        deleteForm.transform((data) => ({ ...data, return_to: page.url }));

        deleteForm.delete(accessControlUrls.permission(deleteTarget.uuid), {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                setDeleteError(null);
            },
            onError: (errors) => {
                setDeleteError(Object.values(errors)[0] ?? 'The permission could not be deleted.');
            },
        });
    };

    const dialogKey = dialog?.mode === 'edit' ? `edit-${dialog.permission.uuid}` : 'create';

    return (
        <AppLayout title="Permissions">
            <Head title="Permissions" />

            <DataTableKit
                {...table.kitProps}
                columns={permissionColumns}
                data={rows}
                total={total}
                loading={visitsPending}
                title="Permissions"
                description="The capability catalogue. System rows are referenced by route middleware and cannot be renamed or deleted; custom rows can."
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
                                    className={
                                        resetting
                                            ? 'size-3.5 animate-spin motion-reduce:animate-none'
                                            : 'size-3.5'
                                    }
                                    aria-hidden="true"
                                />
                                Flush cache
                            </Button>
                        ) : null}
                        {canCreate ? (
                            <Button
                                type="button"
                                size="toolbar"
                                onClick={() => setDialog({ mode: 'create' })}
                            >
                                <Plus className="size-3.5" aria-hidden="true" />
                                New permission
                            </Button>
                        ) : null}
                    </div>
                }
                rowActions={(row) => (
                    <DataTableRowActions
                        label={`Actions for ${row.name}`}
                        actions={[
                            ...(canUpdate && !row.is_system
                                ? [
                                      {
                                          label: 'Rename',
                                          icon: <Pencil className="size-4" aria-hidden="true" />,
                                          onClick: () =>
                                              setDialog({ mode: 'edit', permission: row }),
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
                        <Button size="sm" onClick={() => setDialog({ mode: 'create' })}>
                            <Plus className="mr-1.5 size-3.5" aria-hidden="true" />
                            New permission
                        </Button>
                    ) : null
                }
            />

            {dialog !== null ? (
                <PermissionDialog
                    key={dialogKey}
                    open
                    returnTo={page.url}
                    onOpenChange={(open) => {
                        if (!open) {
                            setDialog(null);
                        }
                    }}
                    permission={dialog.mode === 'edit' ? dialog.permission : undefined}
                />
            ) : null}

            <CrudDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                        setDeleteError(null);
                    }
                }}
                title="Delete permission"
                description="The permission and its links are removed. This cannot be undone."
                confirmLabel="Delete permission"
                confirmVariant="destructive"
                onConfirm={confirmDelete}
                loading={deleteForm.processing}
                confirmDisabled={deleteTarget === null}
            >
                <p className="text-sm text-surface-700 dark:text-surface-200">
                    Delete <strong className="font-mono">{deleteTarget?.name}</strong>?
                </p>
                <p className="mt-2 text-xs text-surface-600 dark:text-surface-300">
                    A permission assigned to a role or a user cannot be deleted until those grants
                    are removed.
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
