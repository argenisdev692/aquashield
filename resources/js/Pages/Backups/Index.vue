<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Column from 'primevue/column';
import { useConfirm } from 'primevue/useconfirm';
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
import {
    countActiveFilters,
    useTableState,
    type TableFilters,
    type TableSortOrder,
} from '@/lib/table';
import {
    BackupRequestError,
    downloadBackupUrl,
    useBackupMutations,
    useBackupsQuery,
    type BackupAbilities,
    type BackupArchive,
    type BackupListPayload,
} from '@/Modules/Backups/composables/useBackups';
import Select from '@/volt/Select.vue';
import Tag from '@/volt/Tag.vue';

defineOptions({
    layout: [AppLayout, { title: 'Database backups' }],
});

const props = defineProps<{
    rows: BackupArchive[];
    total: number;
    currentPage: number;
    perPage: number;
    filters: Modules.Backups.Data.BackupFilterData;
    disks: string[];
    can: BackupAbilities;
}>();

type BackupTableFilters = TableFilters & {
    status: string;
    disk: string;
    date_from: string;
    date_to: string;
};

interface SelectOption {
    label: string;
    value: string;
}

const toast = useToast();
const confirm = useConfirm();

const initialSortOrder: TableSortOrder = props.filters.sort_order === 1 ? 1 : -1;
const initialPayload: BackupListPayload = {
    rows: props.rows,
    total: props.total,
    page: props.currentPage,
    per_page: props.perPage,
};

const { page, perPage, search, sort, filters, params, resetFilters, exportUrl } =
    useTableState<BackupTableFilters>({
        page: props.currentPage,
        perPage: props.perPage,
        search: props.filters.search ?? '',
        sort: { field: props.filters.sort_field, order: initialSortOrder },
        filters: {
            status: props.filters.status,
            disk: props.filters.disk ?? '',
            date_from: props.filters.date_from ?? '',
            date_to: props.filters.date_to ?? '',
        },
    });

const { data, isLoading, error, refetch } = useBackupsQuery(params, initialPayload);
const { deleteBackup, restoreBackup, bulkDelete, bulkRestore, runBackup, busy } =
    useBackupMutations();

const selection = ref<BackupArchive[]>([]);

const rows = computed<BackupArchive[]>(() => data.value?.rows ?? []);
const total = computed<number>(() => data.value?.total ?? 0);
const tableLoading = computed<boolean>(() => isLoading.value || busy.value);

/**
 * `status=active` is the server default, not a user filter. Counting it would
 * show a "1 filter" badge on a pristine table.
 */
const activeFilterCount = computed<number>(() =>
    countActiveFilters({
        status: filters.status === 'active' ? '' : filters.status,
        disk: filters.disk,
        date_from: filters.date_from,
        date_to: filters.date_to,
    }),
);
const hasFilters = computed<boolean>(
    () => search.value.trim() !== '' || activeFilterCount.value > 0,
);

const queryErrorMessage = computed<string>(() =>
    error.value instanceof BackupRequestError
        ? error.value.message
        : 'Could not load backups. Check your connection and try again.',
);

const exportFormats = computed<ExportFormat[]>(() =>
    props.can.export ? ['csv', 'xlsx', 'pdf'] : [],
);

const statusOptions: SelectOption[] = [
    { label: 'Active', value: 'active' },
    { label: 'Quarantined', value: 'trashed' },
    { label: 'All', value: 'all' },
];

const diskOptions = computed<SelectOption[]>(() =>
    props.disks.map((disk) => ({ label: disk, value: disk })),
);

watch(filters, () => {
    page.value = 1;
    selection.value = [];
});

const resetAll = (): void => {
    search.value = '';
    resetFilters();
    filters.status = 'active';
    page.value = 1;
    selection.value = [];
};

const exportHref = (format: ExportFormat): string | undefined =>
    props.can.export ? exportUrl(`/backups/export/${format}`) : undefined;

const selectionLabel = (row: BackupArchive): string => `${row.filename} (${row.disk})`;

const notifyError = (caught: unknown, fallback: string): void => {
    toast.add({
        severity: 'error',
        summary: 'Action failed',
        detail: caught instanceof BackupRequestError ? caught.message : fallback,
        life: 5000,
    });
};

const triggerDownload = (uuid: string): void => {
    const link = document.createElement('a');
    link.href = downloadBackupUrl(uuid);
    link.rel = 'noopener';
    document.body.append(link);
    link.click();
    link.remove();
};

const startRun = async (): Promise<void> => {
    try {
        await runBackup();
        toast.add({
            severity: 'success',
            summary: 'Backup queued',
            detail: 'The archive appears once the worker finishes the dump.',
            life: 5000,
        });
        await refetch();
    } catch (caught: unknown) {
        notifyError(caught, 'The backup could not be queued.');
    }
};

const confirmRun = (): void => {
    confirm.require({
        header: 'Run database backup',
        message:
            'Queue a database-only dump now? It writes a new archive to the backup disk; the scheduled run is unaffected.',
        acceptLabel: 'Run backup',
        rejectLabel: 'Cancel',
        accept: () => {
            void startRun();
        },
    });
};

const removeBackup = async (row: BackupArchive): Promise<void> => {
    try {
        await deleteBackup(row.uuid);
        selection.value = selection.value.filter((selected) => selected.uuid !== row.uuid);
        toast.add({
            severity: 'success',
            summary: 'Backup quarantined',
            detail: `${row.filename} can be restored for 30 days.`,
            life: 4000,
        });
    } catch (caught: unknown) {
        notifyError(caught, 'The backup could not be removed.');
    }
};

const restoreBackupRow = async (row: BackupArchive): Promise<void> => {
    try {
        await restoreBackup(row.uuid);
        toast.add({
            severity: 'success',
            summary: 'Backup restored',
            detail: `${row.filename} is active again.`,
            life: 4000,
        });
    } catch (caught: unknown) {
        notifyError(caught, 'The backup could not be restored.');
    }
};

const onBulkDelete = async (): Promise<void> => {
    const active = selection.value.filter((row) => !row.is_trashed);

    if (active.length === 0) {
        toast.add({
            severity: 'info',
            summary: 'Nothing to remove',
            detail: 'Removal applies to active archives only.',
            life: 4000,
        });

        return;
    }

    try {
        await bulkDelete(active.map((row) => row.uuid));
        selection.value = [];
        toast.add({
            severity: 'success',
            summary: 'Backups quarantined',
            detail: `${active.length} ${active.length === 1 ? 'archive is' : 'archives are'} recoverable for 30 days.`,
            life: 4000,
        });
    } catch (caught: unknown) {
        notifyError(caught, 'The selected backups could not be removed.');
    }
};

const onBulkRestore = async (): Promise<void> => {
    const deleted = selection.value.filter((row) => row.is_trashed);

    if (deleted.length === 0) {
        toast.add({
            severity: 'info',
            summary: 'Nothing to restore',
            detail: 'Restore applies to quarantined archives only.',
            life: 4000,
        });

        return;
    }

    try {
        await bulkRestore(deleted.map((row) => row.uuid));
        selection.value = [];
        toast.add({
            severity: 'success',
            summary: 'Backups restored',
            detail: `${deleted.length} ${deleted.length === 1 ? 'archive is' : 'archives are'} active again.`,
            life: 4000,
        });
    } catch (caught: unknown) {
        notifyError(caught, 'The selected backups could not be restored.');
    }
};

const rowActions = computed<RowAction<BackupArchive>[]>(() => [
    {
        key: 'view',
        label: 'View backup',
        icon: 'pi pi-eye',
        hidden: () => !props.can.view,
        action: (row) => {
            router.visit(`/backups/${row.uuid}`);
        },
    },
    {
        key: 'download',
        label: 'Download archive',
        icon: 'pi pi-download',
        hidden: (row) => !props.can.download || row.is_trashed,
        action: (row) => {
            triggerDownload(row.uuid);
        },
    },
    {
        key: 'restore',
        label: 'Restore backup',
        icon: 'pi pi-replay',
        tone: 'accent',
        hidden: (row) => !props.can.restore || !row.is_trashed,
        confirm: {
            header: 'Restore backup',
            message: (row) =>
                `Restore ${row.filename} (${row.disk})? It returns to the active destination.`,
            acceptLabel: 'Restore',
        },
        action: restoreBackupRow,
    },
    {
        key: 'delete',
        label: 'Quarantine backup',
        icon: 'pi pi-trash',
        tone: 'danger',
        // The newest archive is protected by the server guard; hiding the
        // action keeps the UI from offering a request that must 422.
        hidden: (row) => !props.can.delete || row.is_trashed || row.is_newest,
        confirm: {
            header: 'Quarantine backup',
            message: (row) =>
                `Move ${row.filename} (${row.disk}) to quarantine? It stays restorable for 30 days, then the retention command deletes it. This does not touch the newest archive.`,
            acceptLabel: 'Quarantine',
        },
        action: removeBackup,
    },
]);
</script>

<template>
    <Head title="Database backups" />

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
        title="Database backups"
        description="Database-only dumps. Quarantine is reversible; the newest archive is protected."
        :filter-count="activeFilterCount"
        :has-active-filters="hasFilters"
        :export-formats="exportFormats"
        :export-href="exportHref"
        :selection-label="selectionLabel"
        :can-restore="can.bulk_restore"
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
                    placeholder="Active backups"
                    show-clear
                />
            </DataTableFilterField>

            <DataTableFilterField v-slot="{ id }" label="Destination">
                <Select
                    :input-id="id"
                    v-model="filters.disk"
                    :options="diskOptions"
                    option-label="label"
                    option-value="value"
                    placeholder="Any disk"
                    show-clear
                />
            </DataTableFilterField>

            <DataTableDateRange
                v-model:from="filters.date_from"
                v-model:to="filters.date_to"
                label="Taken"
            />
        </template>

        <template #actions>
            <TableButton v-if="can.create" variant="primary" :disabled="busy" @click="confirmRun">
                <i class="pi pi-database text-xs" aria-hidden="true" />
                Run backup
            </TableButton>
        </template>

        <Column
            v-if="can.bulk_delete || can.bulk_restore"
            selection-mode="multiple"
            header-style="width: 3rem"
        />

        <Column field="filename" header="Archive" sortable>
            <template #body="{ data }">
                <div class="flex min-w-0 items-center gap-2">
                    <i
                        class="pi pi-file-zipper shrink-0 text-sm text-surface-400 dark:text-surface-500"
                        aria-hidden="true"
                    ></i>
                    <div class="min-w-0">
                        <p
                            class="truncate font-mono text-sm font-medium text-surface-900 dark:text-surface-0"
                        >
                            {{ data.filename }}
                        </p>
                        <div class="mt-1 flex flex-wrap items-center gap-1">
                            <Tag v-if="data.is_newest" severity="success">Newest</Tag>
                            <Tag v-if="data.is_trashed" severity="warn">Quarantined</Tag>
                        </div>
                    </div>
                </div>
            </template>
        </Column>

        <Column field="disk" header="Disk" sortable>
            <template #body="{ data }">
                <span class="text-sm text-surface-700 dark:text-surface-200">{{ data.disk }}</span>
            </template>
        </Column>

        <Column field="taken_at" header="Taken" sortable>
            <template #body="{ data }">
                <time
                    :datetime="data.taken_at"
                    class="text-sm text-surface-700 tabular-nums dark:text-surface-200"
                >
                    {{ data.taken_at }}
                </time>
            </template>
        </Column>

        <Column field="size_bytes" header="Size" sortable>
            <template #body="{ data }">
                <span class="text-sm text-surface-700 tabular-nums dark:text-surface-200">
                    {{ data.size_human }}
                </span>
            </template>
        </Column>

        <Column header="Age">
            <template #body="{ data }">
                <span class="text-sm text-surface-700 tabular-nums dark:text-surface-200">
                    {{ data.age_days === 0 ? 'Today' : `${data.age_days} d` }}
                </span>
            </template>
        </Column>

        <Column header="" style="width: 1%">
            <template #body="{ data }">
                <DataTableRowActions
                    :row="data"
                    :row-label="data.filename"
                    :actions="rowActions"
                    :disabled="tableLoading"
                />
            </template>
        </Column>

        <template #empty-action>
            <TableButton v-if="hasFilters" variant="neutral" @click="resetAll">
                Clear filters
            </TableButton>
            <TableButton v-else-if="can.create" variant="primary" @click="confirmRun">
                Run the first backup
            </TableButton>
        </template>
    </DataTableKit>
</template>
