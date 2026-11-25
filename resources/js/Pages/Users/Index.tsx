/**
 * Users/Index — the staff directory on the shared CRUD table kit.
 *
 * The URL owns search, sort, filters, page and page size. Filters are two
 * axes on purpose: `status` selects the record state (active/trashed) while
 * `account_status` selects the staff lifecycle (active/invited/suspended).
 *
 * Bulk actions post to the module's JSON endpoints and reload only the table
 * props, so the current view survives the mutation. Every action returns an
 * updated/skipped summary; no control pretends a protected row was changed.
 */
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    Eye,
    Mail,
    Pencil,
    Plus,
    RotateCcw,
    ShieldCheck,
    ShieldOff,
    Trash2,
    Users,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Tag } from '@/Components/ui/tag';
import {
    CrudAdvancedFilters,
    CrudDialog,
    DataTableKit,
    DataTableRowActions,
    type CrudFilterDef,
    type ExportFormat,
    type SimpleColumn,
} from '@/Components/DataTable';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/date';
import { useCrudTable } from '@/lib/crud-table';
import { useVisitPending } from '@/lib/visits';
import SavedViewsMenu from '@/Modules/SavedViews/components/SavedViewsMenu';
import type { BulkFeedback } from '@/Modules/Users/hooks/useUserBulkActions';
import ActionFeedback from './components/ActionFeedback';
import UserBulkActions from './components/UserBulkActions';
import UserStatusBadge from './components/UserStatusBadge';

type ManagedUser = Modules.Users.Data.ManagedUserData;

type UsersIndexProps = {
    rows: ManagedUser[];
    total: number;
    currentPage: number;
    perPage: number;
    catalog: Modules.Users.Data.UserAccessCatalogData;
    currentUserUuid: string;
    activeSuperAdminCount: number;
    canView: boolean;
    canCreate: boolean;
    canUpdate: boolean;
    canManageStatus: boolean;
    canAssignRoles: boolean;
    canDelete: boolean;
    canRestore: boolean;
    canExport: boolean;
    canBulkDelete: boolean;
    canBulkRestore: boolean;
};

type PendingAction = {
    type: 'suspend' | 'activate' | 'delete';
    user: ManagedUser;
};

/*
 * TanStack Table v9 treats `columns` as a model input. A module-level array
 * keeps that identity stable across renders; recreating it inside the page
 * rebuilds the column model on every keystroke.
 */
const userColumns: SimpleColumn<ManagedUser>[] = [
    {
        accessorKey: 'name',
        id: 'name',
        header: 'Name',
        sortKey: 'name',
        hidable: false,
        className: 'min-w-[140px] max-w-[200px] sm:min-w-[150px]',
        cell: ({ row }): React.ReactNode => (
            <span className="flex min-w-0 flex-col">
                <span className="flex min-w-0 items-center gap-2">
                    <span className="truncate font-medium text-surface-900 dark:text-surface-0">
                        {row.name}
                    </span>
                    {row.deleted_at !== null && <Tag variant="destructive">Deleted</Tag>}
                </span>
                <span className="truncate text-xs text-surface-500 sm:hidden dark:text-surface-300">
                    {row.email}
                </span>
            </span>
        ),
    },
    {
        accessorKey: 'email',
        header: 'Email',
        className: 'min-w-[170px] max-w-[220px]',
        hideBelow: 'sm',
        cell: ({ row }): React.ReactNode => (
            <span
                className="block truncate text-surface-600 dark:text-surface-300"
                title={row.email}
            >
                {row.email}
            </span>
        ),
    },
    {
        accessorKey: 'username',
        header: 'Username',
        sortKey: 'username',
        className: 'max-w-[120px] whitespace-nowrap text-surface-600 dark:text-surface-300',
        hideBelow: 'xl',
        cell: ({ value }): React.ReactNode => (
            <span className="block truncate">{typeof value === 'string' ? value : '—'}</span>
        ),
    },
    {
        accessorKey: 'roles',
        header: 'Roles',
        sortable: false,
        className: 'min-w-[110px]',
        hideBelow: 'sm',
        cell: ({ value }): React.ReactNode => (
            <span className="flex flex-wrap gap-1">
                {Array.isArray(value) && value.length > 0
                    ? value.map((role) => (
                          <Tag key={role} variant="outline">
                              {role}
                          </Tag>
                      ))
                    : '—'}
            </span>
        ),
    },
    {
        accessorKey: 'status',
        header: 'Status',
        sortKey: 'status',
        className: 'whitespace-nowrap',
        cell: ({ row }): React.ReactNode => (
            <UserStatusBadge status={row.status} label={row.status_label} />
        ),
    },
    {
        accessorKey: 'created_at',
        header: 'Created',
        align: 'right',
        width: 'w-38',
        className: 'whitespace-nowrap tabular-nums text-surface-600 dark:text-surface-300',
        hideBelow: 'xl',
        cell: ({ row }): React.ReactNode => formatDateTime(row.created_at),
    },
];

export default function UsersIndex() {
    const page = usePage<UsersIndexProps>();
    const {
        rows,
        total,
        catalog,
        currentUserUuid,
        activeSuperAdminCount,
        canCreate,
        canView,
        canUpdate,
        canManageStatus,
        canAssignRoles,
        canDelete,
        canRestore,
        canExport,
        canBulkDelete,
        canBulkRestore,
    } = page.props;

    const visitsPending = useVisitPending();
    const [pending, setPending] = useState<PendingAction | null>(null);
    const [feedback, setFeedback] = useState<BulkFeedback | null>(null);

    /*
     * The hook seeds the page size from the server's echoed `perPage`, so the
     * first paint, the footer, the page count and the selector cannot disagree.
     * Only the sort default is page-specific.
     */
    const table = useCrudTable({
        endpoint: '/users',
        defaults: { sort: { field: 'created_at', order: -1 } },
    });

    const isLastActiveAdmin = (row: ManagedUser): boolean =>
        row.is_super_admin && row.status === 'active' && activeSuperAdminCount === 1;

    const isProtected = (row: ManagedUser): boolean =>
        row.uuid === currentUserUuid || isLastActiveAdmin(row);

    /*
     * Declared here, not at module scope: the role list comes from the server
     * catalog. The order matches the toolbar reading left to right.
     */
    const filters: CrudFilterDef[] = [
        {
            key: 'account_status',
            label: 'Account status',
            type: 'select',
            placeholder: 'Any status',
            options: [
                { value: 'active', label: 'Active' },
                { value: 'invited', label: 'Invited' },
                { value: 'suspended', label: 'Suspended' },
            ],
        },
        {
            key: 'role',
            label: 'Roles',
            type: 'multi-select',
            placeholder: 'Any role',
            options: catalog.roles.map((role) => ({ value: role.name, label: role.name })),
        },
        {
            key: 'permission',
            label: 'Permission',
            type: 'select',
            placeholder: 'Any permission',
            searchable: true,
            options: catalog.permissions.map((permission) => ({
                value: permission,
                label: permission,
            })),
        },
        {
            key: 'status',
            label: 'Records',
            type: 'select',
            placeholder: 'Active only',
            options: [
                { value: 'trashed', label: 'Deleted only' },
                { value: 'all', label: 'All (incl. deleted)' },
            ],
        },
        { key: 'created', label: 'Created', type: 'date-range' },
    ];

    const exportFormats: ExportFormat[] = canExport ? ['csv', 'xlsx', 'pdf'] : [];

    const selectionLabel = (row: ManagedUser): string => `${row.name} (${row.email})`;

    const addUserAction = canCreate ? (
        <Button render={<Link href="/users/create" />} nativeButton={false} className="h-9">
            <Plus className="size-4" aria-hidden="true" />
            Add user
        </Button>
    ) : null;

    const confirmPending = (): void => {
        if (pending === null) {
            return;
        }

        if (pending.type === 'delete') {
            router.delete(`/users/${pending.user.uuid}`, { preserveScroll: true });
        } else {
            router.post(
                '/users/bulk-status',
                {
                    uuids: [pending.user.uuid],
                    status: pending.type === 'suspend' ? 'suspended' : 'active',
                },
                { preserveScroll: true },
            );
        }

        setPending(null);
    };

    return (
        <AppLayout title="Users">
            <Head title="Users" />

            <div className="flex flex-col gap-4">
                <div className="flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300">
                    <Users className="size-4" aria-hidden="true" />
                    <span>{total} users match the current view</span>
                </div>

                <ActionFeedback feedback={feedback} onDismiss={() => setFeedback(null)} />

                <DataTableKit
                    {...table.kitProps}
                    columns={userColumns}
                    data={rows}
                    total={total}
                    perPageOptions={[10, 15, 25, 50, 100]}
                    rowActions={(row) => (
                        <DataTableRowActions
                            label={`Actions for ${row.name}`}
                            actions={[
                                ...(canView
                                    ? [
                                          {
                                              label: 'View',
                                              icon: <Eye className="size-4" aria-hidden="true" />,
                                              onClick: () => router.visit(`/users/${row.uuid}`),
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
                                                  router.visit(`/users/${row.uuid}/edit`),
                                          },
                                      ]
                                    : []),
                                ...(canManageStatus &&
                                row.status === 'invited' &&
                                row.deleted_at === null
                                    ? [
                                          {
                                              label: 'Resend invitation',
                                              icon: <Mail className="size-4" aria-hidden="true" />,
                                              onClick: () =>
                                                  router.post(
                                                      `/users/${row.uuid}/invitation`,
                                                      {},
                                                      { preserveScroll: true },
                                                  ),
                                          },
                                      ]
                                    : []),
                                ...(canManageStatus &&
                                row.status === 'active' &&
                                row.deleted_at === null &&
                                !isProtected(row)
                                    ? [
                                          {
                                              label: 'Suspend',
                                              icon: (
                                                  <ShieldOff
                                                      className="size-4"
                                                      aria-hidden="true"
                                                  />
                                              ),
                                              onClick: () =>
                                                  setPending({ type: 'suspend', user: row }),
                                          },
                                      ]
                                    : []),
                                ...(canManageStatus &&
                                row.status === 'suspended' &&
                                row.deleted_at === null
                                    ? [
                                          {
                                              label: 'Activate',
                                              icon: (
                                                  <ShieldCheck
                                                      className="size-4"
                                                      aria-hidden="true"
                                                  />
                                              ),
                                              onClick: () =>
                                                  setPending({ type: 'activate', user: row }),
                                          },
                                      ]
                                    : []),
                                ...(canRestore && row.deleted_at !== null
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
                                                      `/users/${row.uuid}/restore`,
                                                      {},
                                                      { preserveScroll: true },
                                                  ),
                                          },
                                      ]
                                    : []),
                                ...(canDelete && row.deleted_at === null && !isProtected(row)
                                    ? [
                                          {
                                              label: 'Delete',
                                              icon: (
                                                  <Trash2 className="size-4" aria-hidden="true" />
                                              ),
                                              destructive: true,
                                              onClick: () =>
                                                  setPending({ type: 'delete', user: row }),
                                          },
                                      ]
                                    : []),
                            ]}
                        />
                    )}
                    loading={visitsPending}
                    title="All users"
                    description="Staff accounts, their roles and their lifecycle state."
                    rowState={(row) => (row.deleted_at !== null ? 'deleted' : 'default')}
                    filterCount={table.activeFilterCount}
                    hasActiveFilters={table.hasActiveFilters}
                    filters={
                        <CrudAdvancedFilters
                            defs={filters}
                            state={table.state}
                            onChange={table.setFilters}
                            disabled={visitsPending}
                        />
                    }
                    views={
                        <SavedViewsMenu
                            resource="users"
                            state={table.state}
                            onApply={table.apply}
                        />
                    }
                    onResetFilters={table.reset}
                    exportFormats={exportFormats}
                    exportHref={canExport ? table.exportUrl : undefined}
                    selectionLabel={selectionLabel}
                    bulkActions={(selected) => (
                        <UserBulkActions
                            rows={selected}
                            onFeedback={setFeedback}
                            roles={catalog.roles.map((role) => role.name)}
                            currentUserUuid={currentUserUuid}
                            activeSuperAdminCount={activeSuperAdminCount}
                            canAssignRoles={canAssignRoles}
                            canDelete={canBulkDelete}
                            canRestore={canBulkRestore}
                        />
                    )}
                    emptyTitle="No users match your filters"
                    emptyDescription="Try a different search, remove a filter, or clear everything to see the full directory."
                    emptyAction={
                        table.hasActiveFilters ? (
                            <Button variant="outline" size="lg" onClick={table.reset}>
                                Clear all filters
                            </Button>
                        ) : null
                    }
                    actions={addUserAction}
                />
            </div>

            <CrudDialog
                open={pending !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPending(null);
                    }
                }}
                title={
                    pending?.type === 'delete'
                        ? `Delete ${pending.user.name}?`
                        : pending?.type === 'suspend'
                          ? `Suspend ${pending.user.name}?`
                          : `Activate ${pending?.user.name ?? 'account'}?`
                }
                description={
                    pending?.type === 'delete'
                        ? 'The account is soft-deleted and can be restored later. Access is removed immediately.'
                        : pending?.type === 'suspend'
                          ? 'The account is signed out and its API tokens are revoked until reactivated.'
                          : 'The account regains access and can sign in again.'
                }
                confirmLabel={
                    pending?.type === 'delete'
                        ? 'Delete'
                        : pending?.type === 'suspend'
                          ? 'Suspend'
                          : 'Activate'
                }
                confirmVariant={pending?.type === 'activate' ? 'default' : 'destructive'}
                onConfirm={confirmPending}
            >
                <p className="text-sm text-surface-600 dark:text-surface-300">
                    {pending?.user.email}
                </p>
            </CrudDialog>
        </AppLayout>
    );
}
