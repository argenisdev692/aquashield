<script setup lang="ts">
/**
 * Server-side pagination footer.
 *
 * The browser never slices rows: changing page or page size updates the
 * reactive state the query key depends on. The window is intentionally short
 * (first, current ±1, last) because a 2026 table on a 13" screen should not
 * spend its footer on twelve page numbers.
 */
import { computed, useId } from 'vue';
import Select from '@/volt/Select.vue';
import TableButton from './TableButton.vue';

interface Props {
    page: number;
    perPage: number;
    total: number;
    perPageOptions?: readonly number[];
    disabled?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:page': [page: number];
    'update:perPage': [perPage: number];
}>();

const perPageId = useId();

interface PerPageOption {
    label: string;
    value: number;
}

interface PageItem {
    key: string;
    page: number | null;
}

const safePerPage = computed(() => Math.max(1, props.perPage));
const pageCount = computed(() => Math.max(1, Math.ceil(props.total / safePerPage.value)));
const currentPage = computed(() => Math.min(Math.max(props.page, 1), pageCount.value));
const firstRow = computed(() =>
    props.total === 0 ? 0 : (currentPage.value - 1) * safePerPage.value + 1,
);
const lastRow = computed(() => Math.min(currentPage.value * safePerPage.value, props.total));

const options = computed<PerPageOption[]>(() => {
    const values = [
        ...new Set([...(props.perPageOptions ?? [10, 15, 25, 50]), props.perPage]),
    ].sort((first, second) => first - second);

    return values.map((value) => ({ label: `${value} / page`, value }));
});

const items = computed<PageItem[]>(() => {
    const count = pageCount.value;
    const current = currentPage.value;

    if (count <= 7) {
        return Array.from({ length: count }, (_, index) => ({
            key: `page-${index + 1}`,
            page: index + 1,
        }));
    }

    const result: PageItem[] = [{ key: 'page-1', page: 1 }];
    const start = Math.max(2, current - 1);
    const end = Math.min(count - 1, current + 1);

    if (start > 2) {
        result.push({ key: 'gap-start', page: null });
    }

    for (let value = start; value <= end; value += 1) {
        result.push({ key: `page-${value}`, page: value });
    }

    if (end < count - 1) {
        result.push({ key: 'gap-end', page: null });
    }

    result.push({ key: `page-${count}`, page: count });

    return result;
});

const goTo = (next: number | null): void => {
    if (next === null) {
        return;
    }

    const target = Math.min(Math.max(next, 1), pageCount.value);

    if (target !== props.page) {
        emit('update:page', target);
    }
};

const onPerPage = (value: unknown): void => {
    if (typeof value !== 'number' || value === props.perPage) {
        return;
    }

    emit('update:perPage', value);
};
</script>

<template>
    <nav
        aria-label="Pagination"
        class="flex flex-col gap-3 border-t border-aq-shell-line px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <p class="text-sm text-surface-600 dark:text-surface-300" aria-live="polite">
            Showing
            <span class="font-medium text-surface-800 tabular-nums dark:text-surface-100">
                {{ firstRow }}–{{ lastRow }}
            </span>
            of
            <span class="font-medium text-surface-800 tabular-nums dark:text-surface-100">
                {{ props.total }}
            </span>
        </p>

        <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2">
                <label :for="perPageId" class="text-sm text-surface-600 dark:text-surface-300">
                    Rows per page
                </label>

                <Select
                    :input-id="perPageId"
                    :model-value="props.perPage"
                    :options="options"
                    option-label="label"
                    option-value="value"
                    size="small"
                    :disabled="props.disabled"
                    class="w-32"
                    @update:model-value="onPerPage"
                />
            </div>

            <div class="flex items-center gap-1">
                <TableButton
                    variant="ghost"
                    size="sm"
                    icon-only
                    aria-label="Previous page"
                    :disabled="props.disabled || currentPage <= 1"
                    @click="goTo(currentPage - 1)"
                >
                    <i class="pi pi-chevron-left text-sm" aria-hidden="true" />
                </TableButton>

                <div class="hidden items-center gap-1 sm:flex">
                    <template v-for="item in items" :key="item.key">
                        <span
                            v-if="item.page === null"
                            class="grid size-8 place-items-center text-sm text-surface-400 dark:text-surface-500"
                            aria-hidden="true"
                        >
                            …
                        </span>

                        <TableButton
                            v-else
                            :variant="item.page === currentPage ? 'primary' : 'ghost'"
                            size="sm"
                            class="min-w-8 tabular-nums"
                            :aria-current="item.page === currentPage ? 'page' : undefined"
                            :aria-label="`Page ${item.page}`"
                            :disabled="props.disabled"
                            @click="goTo(item.page)"
                        >
                            {{ item.page }}
                        </TableButton>
                    </template>
                </div>

                <p class="text-sm text-surface-600 tabular-nums sm:hidden dark:text-surface-300">
                    Page {{ currentPage }} of {{ pageCount }}
                </p>

                <TableButton
                    variant="ghost"
                    size="sm"
                    icon-only
                    aria-label="Next page"
                    :disabled="props.disabled || currentPage >= pageCount"
                    @click="goTo(currentPage + 1)"
                >
                    <i class="pi pi-chevron-right text-sm" aria-hidden="true" />
                </TableButton>
            </div>
        </div>
    </nav>
</template>
