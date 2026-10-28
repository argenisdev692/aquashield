<script setup lang="ts" generic="T">
/**
 * DataTableKit — the composed server-side table.
 *
 * 2026 shape, not a 2019 grid: search first, filters behind one trigger with
 * an active count, a contextual bulk bar, a short pagination window, and an
 * export that reuses the table's own query string. The table stays `lazy`;
 * this component never sorts, filters or slices in memory.
 *
 * The parts remain importable on their own (search, filters, bulk bar, row
 * actions, pagination, export, empty state) for the one screen that needs a
 * different layout. Composition is the default, not a cage.
 *
 * Required: `v-model:page` and `v-model:per-page`. Everything else has a
 * sensible default, and the default slot is where `<Column>` children go.
 */
import { computed, useId, watch } from 'vue';
import type { TableSort } from '@/lib/table';
import Datatable from '@/volt/Datatable.vue';
import DataTableBulkActions from './DataTableBulkActions.vue';
import DataTableEmpty from './DataTableEmpty.vue';
import DataTableExport from './DataTableExport.vue';
import DataTableFilters from './DataTableFilters.vue';
import DataTablePagination from './DataTablePagination.vue';
import DataTableSearch from './DataTableSearch.vue';
import type { ExportFormat } from './types';

interface Props {
    rows: T[];
    total: number;
    loading?: boolean;
    dataKey?: string;
    label?: string;
    title?: string;
    description?: string;
    searchPlaceholder?: string;
    searchMinLength?: number;
    filterCount?: number;
    /**
     * Pages with filters they cannot count in `filterCount` can pass the truth
     * directly. Without one of the two, the empty state cannot tell "no records
     * yet" from "nothing matched".
     */
    hasActiveFilters?: boolean;
    perPageOptions?: readonly number[];
    exportFormats?: readonly ExportFormat[];
    exportHref?: (format: ExportFormat) => string | undefined;
    canRestore?: boolean;
    /** Optional human label per selected row, used by the bulk confirm copy. */
    selectionLabel?: (row: T) => string;
    emptyTitle?: string;
    emptyDescription?: string;
    emptyIcon?: string;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    export: [format: ExportFormat];
    'bulk-delete': [];
    'bulk-restore': [];
    'reset-filters': [];
}>();

defineSlots<{
    default(): unknown;
    toolbar(): unknown;
    filters(): unknown;
    actions(): unknown;
    empty(props: { filtered: boolean }): unknown;
    'empty-action'(): unknown;
    'bulk-actions'(): unknown;
}>();

const page = defineModel<number>('page', { required: true });
const perPage = defineModel<number>('perPage', { required: true });
const search = defineModel<string>('search', { default: '' });
const sort = defineModel<TableSort | null>('sort', { default: null });
const selection = defineModel<T[]>('selection', { default: () => [] });

const titleId = useId();

interface SortPayload {
    sortField?: string | string[] | null;
    sortOrder?: number | number[] | null;
}

const isSortPayload = (value: unknown): value is SortPayload =>
    typeof value === 'object' && value !== null && ('sortField' in value || 'sortOrder' in value);

const firstField = (value: string | string[] | null | undefined): string | null =>
    Array.isArray(value) ? (value[0] ?? null) : (value ?? null);

const firstOrder = (value: number | number[] | null | undefined): number | null =>
    Array.isArray(value) ? (value[0] ?? null) : (value ?? null);

const first = computed(() => Math.max(0, (page.value - 1) * perPage.value));
const pageCount = computed(() => Math.max(1, Math.ceil(props.total / Math.max(1, perPage.value))));
const sortField = computed(() => sort.value?.field);
const sortOrder = computed(() => sort.value?.order);
const filtered = computed(
    () =>
        props.hasActiveFilters === true ||
        (props.filterCount ?? 0) > 0 ||
        search.value.trim() !== '',
);

/**
 * The bulk bar owns the confirm modal, so it needs readable row labels to show
 * what is about to be deleted. The page supplies the projection; the kit never
 * assumes a model shape.
 */
const selectionLabels = computed<string[] | undefined>(() => {
    if (props.selectionLabel === undefined) {
        return undefined;
    }

    return selection.value.map((row) => props.selectionLabel?.(row) ?? '');
});

const resolvedEmptyTitle = computed(() => {
    if (props.emptyTitle !== undefined) {
        return props.emptyTitle;
    }

    return filtered.value ? 'No matching records' : 'No records yet';
});

const resolvedEmptyDescription = computed(() => {
    if (props.emptyDescription !== undefined) {
        return props.emptyDescription;
    }

    return filtered.value
        ? 'Try a different search or clear the filters.'
        : 'Records will appear here once they are created.';
});

const clearSelection = (): void => {
    selection.value = [];
};

const setPage = (value: number): void => {
    if (value === page.value) {
        return;
    }

    page.value = value;
    clearSelection();
};

const setPerPage = (value: number): void => {
    perPage.value = value;
};

const onSort = (event: unknown): void => {
    if (!isSortPayload(event)) {
        return;
    }

    const field = firstField(event.sortField);
    const order = firstOrder(event.sortOrder);

    sort.value =
        typeof field === 'string' && (order === 1 || order === -1) ? { field, order } : null;
    page.value = 1;
    clearSelection();
};

const clearFilters = (): void => {
    emit('reset-filters');
    page.value = 1;
    clearSelection();
};

const onExport = (format: ExportFormat): void => {
    emit('export', format);
};

const onBulkDelete = (): void => {
    emit('bulk-delete');
};

const onBulkRestore = (): void => {
    emit('bulk-restore');
};

/**
 * A settled search or a new page size always starts at page one. Selection is
 * page-scoped by design: carrying row references across a server page is the
 * bug that makes bulk actions delete the wrong records.
 */
watch([search, perPage], () => {
    page.value = 1;
    clearSelection();
});

/**
 * Deleting the last rows of the last page would otherwise leave the user on an
 * empty page while the footer clamps its display. `total === 0` is skipped so a
 * deep link is not rewound before the first response arrives.
 */
watch(
    [page, pageCount],
    () => {
        if (props.total > 0 && page.value > pageCount.value) {
            page.value = pageCount.value;
            clearSelection();
        }
    },
    { immediate: true },
);

defineExpose({ clearSelection });
</script>

<template>
    <section
        class="overflow-hidden rounded-xl border border-aq-shell-line bg-surface-0 shadow-sm dark:bg-surface-900"
        :aria-labelledby="props.title ? titleId : undefined"
        :aria-label="props.title ? undefined : (props.label ?? 'Data table')"
    >
        <header class="border-b border-aq-shell-line px-4 py-3">
            <div v-if="props.title || props.description" class="mb-3">
                <h2
                    v-if="props.title"
                    :id="titleId"
                    class="text-base font-semibold tracking-tight text-surface-900 dark:text-surface-0"
                >
                    {{ props.title }}
                </h2>
                <p
                    v-if="props.description"
                    class="mt-0.5 text-sm text-surface-600 dark:text-surface-300"
                >
                    {{ props.description }}
                </p>
            </div>

            <slot name="toolbar">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        <DataTableSearch
                            v-model="search"
                            :placeholder="props.searchPlaceholder ?? 'Search…'"
                            :min-length="props.searchMinLength ?? 2"
                            :loading="props.loading ?? false"
                            class="w-full sm:max-w-xs"
                        />

                        <DataTableFilters
                            v-if="$slots.filters"
                            :active-count="props.filterCount ?? 0"
                            :disabled="props.loading ?? false"
                            @reset="clearFilters"
                        >
                            <slot name="filters" />
                        </DataTableFilters>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <slot name="actions" />

                        <DataTableExport
                            v-if="(props.exportFormats ?? ['csv', 'xlsx', 'pdf']).length > 0"
                            :formats="props.exportFormats"
                            :href="props.exportHref"
                            :disabled="props.loading ?? false"
                            @export="onExport"
                        />
                    </div>
                </div>
            </slot>
        </header>

        <Transition
            enter-active-class="transition duration-150 ease-out motion-reduce:transition-none"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in motion-reduce:transition-none"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div v-if="selection.length > 0" class="border-b border-aq-shell-line px-4 py-2.5">
                <DataTableBulkActions
                    :count="selection.length"
                    :can-restore="props.canRestore ?? false"
                    :busy="props.loading ?? false"
                    :labels="selectionLabels"
                    @clear="clearSelection"
                    @delete="onBulkDelete"
                    @restore="onBulkRestore"
                >
                    <slot name="bulk-actions" />
                </DataTableBulkActions>
            </div>
        </Transition>

        <Datatable
            :value="props.rows"
            :loading="props.loading ?? false"
            lazy
            scrollable
            :total-records="props.total"
            :first="first"
            :rows="perPage"
            :data-key="props.dataKey ?? 'id'"
            selection-mode="multiple"
            v-model:selection="selection"
            :sort-field="sortField"
            :sort-order="sortOrder"
            row-hover
            @sort="onSort"
        >
            <slot />

            <template #empty>
                <slot name="empty" :filtered="filtered">
                    <DataTableEmpty
                        :title="resolvedEmptyTitle"
                        :description="resolvedEmptyDescription"
                        :icon="props.emptyIcon ?? 'pi pi-inbox'"
                    >
                        <slot name="empty-action" />
                    </DataTableEmpty>
                </slot>
            </template>
        </Datatable>

        <DataTablePagination
            v-if="props.total > 0"
            :page="page"
            :per-page="perPage"
            :total="props.total"
            :per-page-options="props.perPageOptions ?? [10, 15, 25, 50]"
            :disabled="props.loading ?? false"
            @update:page="setPage"
            @update:per-page="setPerPage"
        />
    </section>
</template>
