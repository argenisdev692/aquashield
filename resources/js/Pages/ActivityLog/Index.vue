<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import Column from 'primevue/column';
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
    ActivityRequestError,
    useActivityQuery,
    type ActivityEntry,
    type ActivityListPayload,
} from '@/Modules/ActivityLog/composables/useActivityLog';
import Inputtext from '@/volt/Inputtext.vue';
import Tag from '@/volt/Tag.vue';
import { eventSeverity } from './components/activityDisplay';

defineOptions({
    layout: [AppLayout, { title: 'Activity log' }],
});

const props = defineProps<{
    rows: ActivityEntry[];
    total: number;
    currentPage: number;
    perPage: number;
    filters: Modules.ActivityLog.Data.ActivityFilterData;
    canView: boolean;
    canExport: boolean;
}>();

type ActivityTableFilters = TableFilters & {
    log_name: string;
    event: string;
    date_from: string;
    date_to: string;
};

const initialSortOrder: TableSortOrder = props.filters.sort_order === 1 ? 1 : -1;
const initialPayload: ActivityListPayload = {
    rows: props.rows,
    total: props.total,
    page: props.currentPage,
    per_page: props.perPage,
};

const { page, perPage, search, sort, filters, params, resetFilters, exportUrl } =
    useTableState<ActivityTableFilters>({
        page: props.currentPage,
        perPage: props.perPage,
        search: props.filters.search ?? '',
        sort: { field: props.filters.sort_field, order: initialSortOrder },
        filters: {
            log_name: props.filters.log_name ?? '',
            event: props.filters.event ?? '',
            date_from: props.filters.date_from ?? '',
            date_to: props.filters.date_to ?? '',
        },
    });

const { data, isLoading, error, refetch } = useActivityQuery(params, initialPayload);

const rows = computed<ActivityEntry[]>(() => data.value?.rows ?? []);
const total = computed<number>(() => data.value?.total ?? 0);
const activeFilterCount = computed<number>(() => countActiveFilters(filters));
const hasFilters = computed<boolean>(
    () => search.value.trim() !== '' || activeFilterCount.value > 0,
);

const queryErrorMessage = computed<string>(() =>
    error.value instanceof ActivityRequestError
        ? error.value.message
        : 'Could not load the audit trail. Check your connection and try again.',
);

const exportFormats = computed<ExportFormat[]>(() =>
    props.canExport ? ['csv', 'xlsx', 'pdf'] : [],
);

const exportHref = (format: ExportFormat): string | undefined =>
    props.canExport ? exportUrl(`/activity/export/${format}`) : undefined;

watch(filters, () => {
    page.value = 1;
});

const resetAll = (): void => {
    search.value = '';
    resetFilters();
    page.value = 1;
};

const rowActions = computed<RowAction<ActivityEntry>[]>(() => [
    {
        key: 'view',
        label: 'View entry',
        icon: 'pi pi-eye',
        hidden: () => !props.canView,
        action: (row) => {
            router.visit(`/activity/${row.uuid}`);
        },
    },
]);
</script>

<template>
    <Head title="Activity log" />

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
        :rows="rows"
        :total="total"
        :loading="isLoading"
        data-key="uuid"
        title="Activity log"
        description="Read-only audit trail. Entries are append-only and retained per the audit policy."
        :filter-count="activeFilterCount"
        :has-active-filters="hasFilters"
        :export-formats="exportFormats"
        :export-href="exportHref"
        @reset-filters="resetAll"
    >
        <template #filters>
            <DataTableFilterField v-slot="{ id, describedBy }" label="Log">
                <Inputtext
                    :id="id"
                    v-model="filters.log_name"
                    :aria-describedby="describedBy"
                    placeholder="backups, users…"
                    fluid
                />
            </DataTableFilterField>

            <DataTableFilterField v-slot="{ id, describedBy }" label="Event">
                <Inputtext
                    :id="id"
                    v-model="filters.event"
                    :aria-describedby="describedBy"
                    placeholder="backup.quarantined"
                    fluid
                />
            </DataTableFilterField>

            <DataTableDateRange
                v-model:from="filters.date_from"
                v-model:to="filters.date_to"
                label="Recorded"
            />
        </template>

        <Column field="event" header="Event" sortable>
            <template #body="{ data }">
                <Tag :severity="eventSeverity(data.event)">{{ data.event }}</Tag>
            </template>
        </Column>

        <Column field="description" header="Description">
            <template #body="{ data }">
                <span class="text-sm text-surface-800 dark:text-surface-100">
                    {{ data.description }}
                </span>
            </template>
        </Column>

        <Column field="log_name" header="Log" sortable>
            <template #body="{ data }">
                <span class="font-mono text-xs text-surface-600 dark:text-surface-300">
                    {{ data.log_name }}
                </span>
            </template>
        </Column>

        <Column header="Causer">
            <template #body="{ data }">
                <span class="text-sm text-surface-700 dark:text-surface-200">
                    {{ data.causer_name ?? 'System' }}
                </span>
            </template>
        </Column>

        <Column field="created_at" header="Recorded" sortable>
            <template #body="{ data }">
                <time
                    :datetime="data.created_at"
                    class="text-sm text-surface-700 tabular-nums dark:text-surface-200"
                >
                    {{ data.created_at }}
                </time>
            </template>
        </Column>

        <Column header="" style="width: 1%">
            <template #body="{ data }">
                <DataTableRowActions
                    :row="data"
                    :row-label="data.event"
                    :actions="rowActions"
                    :disabled="isLoading"
                />
            </template>
        </Column>

        <template #empty-action>
            <TableButton v-if="hasFilters" variant="neutral" @click="resetAll">
                Clear filters
            </TableButton>
        </template>
    </DataTableKit>
</template>
