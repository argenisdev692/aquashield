<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Column from 'primevue/column';
import { useToast } from 'primevue/usetoast';
import {
    DataTableDateRange,
    DataTableFilterField,
    DataTableKit,
    DataTableRowActions,
    TableButton,
    type ExportFormat,
    type RowAction,
} from '@/Components/DataTable';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTableState, type TableFilters, type TableSortOrder } from '@/lib/table';
import {
    UserRequestError,
    useUserMutations,
    useUsersQuery,
    type ManagedUser,
    type UserListPayload,
} from '@/Modules/Users/composables/useUsers';
import Avatar from '@/volt/Avatar.vue';
import Select from '@/volt/Select.vue';
import Tag from '@/volt/Tag.vue';
import UserStatusBadge from './components/UserStatusBadge.vue';
import { formatDateTime, initials } from './components/userDisplay';

defineOptions({
    layout: [AppLayout, { title: 'Users' }],
});

const props = defineProps<{
    rows: ManagedUser[];
    total: number;
    currentPage: number;
    perPage: number;
    filters: Modules.Users.Data.UserFilterData;
    catalog: Modules.Users.Data.UserAccessCatalogData;
    currentUserUuid: string;
    canView: boolean;
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    canRestore: boolean;
    canExport: boolean;
    canBulkDelete: boolean;
    canBulkRestore: boolean;
}>();

type UserTableFilters = TableFilters & {
    status: string;
    role: string;
    permission: string;
    date_from: string;
    date_to: string;
};

interface SelectOption {
    label: string;
    value: string;
}

const toast = useToast();

/**
 * The server has already applied the deep-linked filters, so the client table
 * starts from the same state instead of fetching an unfiltered first page and
 * replacing what the user sees.
 */
const initialSortOrder: TableSortOrder = props.filters.sort_order === 1 ? 1 : -1;
const initialPayload: UserListPayload = {
    rows: props.rows,
    total: props.total,
    page: props.currentPage,
    per_page: props.perPage,
};

const { page, perPage, search, sort, filters, params, activeFilterCount, resetFilters, exportUrl } =
    useTableState<UserTableFilters>({
        page: props.currentPage,
        perPage: props.perPage,
        search: props.filters.search ?? '',
        sort: { field: props.filters.sort_field, order: initialSortOrder },
        filters: {
            status: props.filters.status ?? '',
            role: props.filters.role ?? '',
            permission: props.filters.permission ?? '',
            date_from: props.filters.date_from ?? '',
            date_to: props.filters.date_to ?? '',
        },
    });

const { data, isLoading, error, refetch } = useUsersQuery(params, initialPayload);
const { deleteUser, restoreUser, bulkDelete, bulkRestore, busy } = useUserMutations();

const selection = ref<ManagedUser[]>([]);

const rows = computed<ManagedUser[]>(() => data.value?.rows ?? []);
const total = computed<number>(() => data.value?.total ?? 0);
const tableLoading = computed<boolean>(() => isLoading.value || busy.value);
const hasFilters = computed<boolean>(
    () => search.value.trim() !== '' || activeFilterCount.value > 0,
);
const queryErrorMessage = computed<string>(() =>
    error.value instanceof UserRequestError
        ? error.value.message
        : 'Could not load users. Check your connection and try again.',
);

const exportFormats = computed<ExportFormat[]>(() =>
    props.canExport ? ['csv', 'xlsx', 'pdf'] : [],
);

const statusOptions: SelectOption[] = [
    { label: 'Active', value: 'active' },
    { label: 'Deleted', value: 'trashed' },
    { label: 'All', value: 'all' },
];

const roleOptions = computed<SelectOption[]>(() =>
    props.catalog.roles.map((role) => ({ label: role.name, value: role.name })),
);

const permissionOptions = computed<SelectOption[]>(() =>
    props.catalog.permissions.map((permission) => ({ label: permission, value: permission })),
);

/**
 * Filters are live slot state, so the kit cannot reset the page for us.
 */
watch(filters, () => {
    page.value = 1;
    selection.value = [];
});

const resetAll = (): void => {
    search.value = '';
    resetFilters();
    page.value = 1;
    selection.value = [];
};

const exportHref = (format: ExportFormat): string | undefined =>
    props.canExport ? exportUrl(`/users/export/${format}`) : undefined;

/** The bulk confirm names the selected accounts, not only their count. */
const selectionLabel = (row: ManagedUser): string => `${row.name} <${row.email}>`;

const notifyError = (caught: unknown, fallback: string): void => {
    toast.add({
        severity: 'error',
        summary: 'Action failed',
        detail: caught instanceof UserRequestError ? caught.message : fallback,
        life: 5000,
    });
};

const removeUser = async (row: ManagedUser): Promise<void> => {
    try {
        await deleteUser(row.uuid);
        selection.value = selection.value.filter((selected) => selected.uuid !== row.uuid);
        toast.add({
            severity: 'success',
            summary: 'User deleted',
            detail: `${row.name} can be restored from the deleted view.`,
            life: 4000,
        });
    } catch (caught: unknown) {
        notifyError(caught, 'The user could not be deleted.');
    }
};

const restoreUserRow = async (row: ManagedUser): Promise<void> => {
    try {
        await restoreUser(row.uuid);
        toast.add({
            severity: 'success',
            summary: 'User restored',
            detail: `${row.name} can sign in again.`,
            life: 4000,
        });
    } catch (caught: unknown) {
        notifyError(caught, 'The user could not be restored.');
    }
};

const onBulkDelete = async (): Promise<void> => {
    if (!props.canBulkDelete) {
        toast.add({
            severity: 'error',
            summary: 'Not allowed',
            detail: 'You do not have permission to delete users in bulk.',
            life: 5000,
        });

        return;
    }

    if (selection.value.some((row) => row.deleted_at !== null)) {
        toast.add({
            severity: 'error',
            summary: 'Selection contains deleted users',
            detail: 'Clear the selection and choose active accounts only.',
            life: 5000,
        });

        return;
    }

    const count = selection.value.length;

    try {
        await bulkDelete(selection.value.map((row) => row.uuid));
        selection.value = [];
        toast.add({
            severity: 'success',
            summary: 'Users deleted',
            detail: `${count} ${count === 1 ? 'account is' : 'accounts are'} recoverable from the deleted view.`,
            life: 4000,
        });
    } catch (caught: unknown) {
        notifyError(caught, 'The selected users could not be deleted.');
    }
};

const onBulkRestore = async (): Promise<void> => {
    const deleted = selection.value.filter((row) => row.deleted_at !== null);

    if (deleted.length === 0) {
        toast.add({
            severity: 'info',
            summary: 'Nothing to restore',
            detail: 'Restore applies to deleted accounts only.',
            life: 4000,
        });

        return;
    }

    try {
        await bulkRestore(deleted.map((row) => row.uuid));
        selection.value = [];
        toast.add({
            severity: 'success',
            summary: 'Users restored',
            detail: `${deleted.length} ${deleted.length === 1 ? 'account is' : 'accounts are'} active again.`,
            life: 4000,
        });
    } catch (caught: unknown) {
        notifyError(caught, 'The selected users could not be restored.');
    }
};

const rowActions = computed<RowAction<ManagedUser>[]>(() => [
    {
        key: 'view',
        label: 'View user',
        icon: 'pi pi-eye',
        hidden: (row) => !props.canView,
        action: (row) => {
            router.visit(`/users/${row.uuid}`);
        },
    },
    {
        key: 'edit',
        label: 'Edit user',
        icon: 'pi pi-pencil',
        hidden: (row) => !props.canUpdate || row.deleted_at !== null,
        action: (row) => {
            router.visit(`/users/${row.uuid}/edit`);
        },
    },
    {
        key: 'restore',
        label: 'Restore user',
        icon: 'pi pi-replay',
        tone: 'accent',
        hidden: (row) => !props.canRestore || row.deleted_at === null,
        confirm: {
            header: 'Restore user',
            message: (row) =>
                `Restore ${row.name} <${row.email}>? They will be able to sign in again.`,
            acceptLabel: 'Restore',
        },
        action: restoreUserRow,
    },
    {
        key: 'delete',
        label: 'Delete user',
        icon: 'pi pi-trash',
        tone: 'danger',
        hidden: (row) =>
            !props.canDelete || row.deleted_at !== null || row.uuid === props.currentUserUuid,
        confirm: {
            header: 'Delete user',
            message: (row) =>
                `Delete ${row.name} <${row.email}>${row.username === null ? '' : ` (@${row.username})`}? Their sessions end immediately, and you can restore them from the deleted view.`,
            acceptLabel: 'Delete',
        },
        action: removeUser,
    },
]);
</script>

<template>
    <Head title="Users" />

    <div
        v-if="error"
        role="alert"
        class="mb-4 flex flex-col gap-3 rounded-xl border border-aq-danger/40 bg-aq-danger/5 px-4 py-3 sm:flex-row sm:items-center"
    >
        <i class="pi pi-exclamation-triangle text-aq-danger-strong" aria-hidden="true" />
        <p class="min-w-0 flex-1 text-sm text-aq-danger-strong">
            {{ queryErrorMessage }}
        </p>
        <TableButton variant="neutral" @click="refetch()">Try again</TableButton>
    </div>

    <DataTableKit
        v-model:page="page"
        v-model:per-page="perPage"
        v-model:search="search"
        v-model:sort="sort"
        v-model:selection="selection"
        :rows="rows"
        :total="total"
        :loading="tableLoading"
        data-key="uuid"
        title="All users"
        description="People with access to this workspace. Deleted accounts stay recoverable."
        :filter-count="activeFilterCount"
        :has-active-filters="hasFilters"
        :export-formats="exportFormats"
        :export-href="exportHref"
        :selection-label="selectionLabel"
        :can-restore="canBulkRestore && (filters.status === 'trashed' || filters.status === 'all')"
        @bulk-delete="onBulkDelete"
        @bulk-restore="onBulkRestore"
        @reset-filters="resetAll"
    >
        <template #filters>
            <DataTableFilterField v-slot="{ id }" label="Status">
                <Select
                    :input-id="id"
                    v-model="filters.status"
                    :options="statusOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Active users"
                    show-clear
                />
            </DataTableFilterField>

            <DataTableFilterField v-slot="{ id }" label="Role">
                <Select
                    :input-id="id"
                    v-model="filters.role"
                    :options="roleOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Any role"
                    filter
                    show-clear
                />
            </DataTableFilterField>

            <DataTableFilterField v-slot="{ id }" label="Permission">
                <Select
                    :input-id="id"
                    v-model="filters.permission"
                    :options="permissionOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Any permission"
                    filter
                    show-clear
                />
            </DataTableFilterField>

            <DataTableDateRange
                v-model:from="filters.date_from"
                v-model:to="filters.date_to"
                label="Created"
            />
        </template>

        <template #actions>
            <Link
                v-if="canCreate"
                href="/users/create"
                class="inline-flex h-9 items-center gap-2 rounded-md border border-primary bg-primary px-3 text-sm font-medium text-primary-contrast transition-colors duration-150 hover:border-primary-emphasis hover:bg-primary-emphasis focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none"
            >
                <i class="pi pi-plus text-xs" aria-hidden="true" />
                New user
            </Link>
        </template>

        <Column
            v-if="canBulkDelete || canBulkRestore"
            selection-mode="multiple"
            header-style="width: 3rem"
        />

        <Column field="first_name" header="User" sortable>
            <template #body="{ data }">
                <div class="flex min-w-0 items-center gap-2.5">
                    <Avatar
                        :label="initials(data.name)"
                        shape="circle"
                        class="shrink-0 bg-aq-accent/15 text-xs font-semibold text-aq-accent dark:bg-aq-accent/20"
                    />
                    <div class="min-w-0">
                        <p
                            class="truncate text-sm font-medium text-surface-900 dark:text-surface-0"
                        >
                            {{ data.name }}
                        </p>
                        <p class="truncate text-xs text-surface-500 dark:text-surface-400">
                            @{{ data.username ?? data.email }}
                        </p>
                    </div>
                </div>
            </template>
        </Column>

        <Column field="email" header="Email" sortable>
            <template #body="{ data }">
                <span class="text-sm text-surface-700 dark:text-surface-200">{{ data.email }}</span>
            </template>
        </Column>

        <Column header="Roles">
            <template #body="{ data }">
                <div class="flex flex-wrap items-center gap-1">
                    <Tag v-for="role in data.roles" :key="role" severity="secondary">
                        {{ role }}
                    </Tag>
                    <Tag
                        v-if="data.direct_permissions.length > 0"
                        severity="info"
                        :title="`${data.direct_permissions.length} direct permissions`"
                        :aria-label="`${data.direct_permissions.length} direct permissions`"
                    >
                        +{{ data.direct_permissions.length }}
                    </Tag>
                    <span
                        v-if="data.roles.length === 0 && data.direct_permissions.length === 0"
                        class="text-sm text-surface-500"
                    >
                        No access
                    </span>
                </div>
            </template>
        </Column>

        <Column field="deleted_at" header="Status" sortable>
            <template #body="{ data }">
                <UserStatusBadge
                    :deleted-at="data.deleted_at"
                    :email-verified="data.email_verified"
                />
            </template>
        </Column>

        <Column field="created_at" header="Created" sortable>
            <template #body="{ data }">
                <time
                    :datetime="data.created_at ?? undefined"
                    class="text-sm text-surface-700 tabular-nums dark:text-surface-200"
                >
                    {{ formatDateTime(data.created_at) }}
                </time>
            </template>
        </Column>

        <Column header="" style="width: 1%">
            <template #body="{ data }">
                <DataTableRowActions
                    :row="data"
                    :row-label="data.name"
                    :actions="rowActions"
                    :disabled="tableLoading"
                />
            </template>
        </Column>

        <template #empty-action>
            <TableButton v-if="hasFilters" variant="neutral" @click="resetAll">
                Clear filters
            </TableButton>
            <Link
                v-else-if="canCreate"
                href="/users/create"
                class="inline-flex h-9 items-center gap-2 rounded-md border border-primary bg-primary px-3 text-sm font-medium text-primary-contrast transition-colors duration-150 hover:border-primary-emphasis hover:bg-primary-emphasis focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none"
            >
                <i class="pi pi-plus text-xs" aria-hidden="true" />
                Create the first user
            </Link>
        </template>
    </DataTableKit>
</template>
