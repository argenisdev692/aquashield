<script setup lang="ts" generic="T">
import { computed, ref, useId } from 'vue';
import SecondaryButton from '@/volt/SecondaryButton.vue';

/**
 * Repeatable group of rows: line items, contacts, rate cards, service windows.
 *
 * A CRM opportunity is rarely one flat record — it has products, and a company
 * has several contacts. Rendering that with `v-for` in every page duplicates
 * the add/remove/reorder contract and, more often than not, loses the keyboard
 * path. This field owns the contract and exposes the row through a scoped slot,
 * so the page still writes the fields it needs and nothing else.
 *
 * Reordering is explicit up/down buttons, not drag-only (WCAG 2.2 SC 2.5.7).
 * Moves and removals are announced through a polite live region.
 */

interface Props {
    label: string;
    /** Builds an empty row. Called once per `Add row` click, only by this field. */
    createRow: () => T;
    /** Stable identity for a row. Without it the index is the key. */
    rowKey?: (row: T) => string | number;
    minRows?: number;
    maxRows?: number;
    addLabel?: string;
    emptyMessage?: string;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
}

const {
    label,
    createRow,
    rowKey,
    minRows = 0,
    maxRows = 20,
    addLabel = 'Add row',
    emptyMessage = 'No rows yet.',
    error,
    hint,
    required = false,
    disabled = false,
} = defineProps<Props>();

const model = defineModel<T[]>({ required: true });

defineSlots<{
    default(props: { row: T; index: number }): unknown;
}>();

const uid = useId();
const labelId = `${uid}-label`;
const hintId = `${uid}-hint`;
const errorId = `${uid}-error`;
const announcement = ref('');

const describedBy = computed<string | undefined>(() => {
    const ids = [hint && !error ? hintId : undefined, error ? errorId : undefined].filter(
        (value): value is string => value !== undefined,
    );

    return ids.length > 0 ? ids.join(' ') : undefined;
});

const announce = (message: string): void => {
    announcement.value = '';

    requestAnimationFrame(() => {
        announcement.value = message;
    });
};

const add = (): void => {
    if (disabled || model.value.length >= maxRows) {
        return;
    }

    model.value = [...model.value, createRow()];
    announce(`Added row ${model.value.length}.`);
};

const remove = (index: number): void => {
    if (disabled || model.value.length <= minRows) {
        return;
    }

    model.value = model.value.filter((_, position) => position !== index);
    announce(`Removed row ${index + 1}.`);
};

const move = (index: number, delta: number): void => {
    const target = index + delta;

    if (
        disabled ||
        target < 0 ||
        target >= model.value.length ||
        index < 0 ||
        index >= model.value.length
    ) {
        return;
    }

    const next = [...model.value];
    const [row] = next.splice(index, 1);

    if (row === undefined) {
        return;
    }

    next.splice(target, 0, row);
    model.value = next;
    announce(`Moved row ${index + 1} to position ${target + 1}.`);
};
</script>

<template>
    <div
        class="flex flex-col gap-3"
        role="group"
        :aria-labelledby="labelId"
        :aria-describedby="describedBy"
        :aria-invalid="error ? true : undefined"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p :id="labelId" class="text-sm font-medium text-surface-800 dark:text-surface-100">
                    {{ label }}
                    <span v-if="required" class="text-aq-danger-strong" aria-hidden="true">*</span>
                    <span v-if="required" class="sr-only">(required)</span>
                </p>

                <p
                    v-if="hint && !error"
                    :id="hintId"
                    class="text-xs text-surface-600 dark:text-surface-300"
                >
                    {{ hint }}
                </p>
            </div>

            <SecondaryButton
                v-if="!disabled"
                type="button"
                :disabled="model.length >= maxRows"
                @click="add"
            >
                {{ addLabel }}
            </SecondaryButton>
        </div>

        <p
            v-if="error"
            :id="errorId"
            role="alert"
            class="text-xs font-medium text-aq-danger-strong"
        >
            {{ error }}
        </p>

        <p class="sr-only" aria-live="polite">{{ announcement }}</p>

        <ol v-if="model.length > 0" class="flex flex-col gap-3">
            <li
                v-for="(row, index) in model"
                :key="rowKey ? rowKey(row) : index"
                class="rounded-xl border border-content-border bg-surface-50/60 p-3 dark:bg-surface-800/40"
            >
                <div class="flex items-start gap-2">
                    <div class="min-w-0 flex-1">
                        <slot :row="row" :index="index" />
                    </div>

                    <div class="flex shrink-0 flex-col items-center gap-0.5">
                        <button
                            type="button"
                            class="grid size-7 place-items-center rounded-md text-surface-600 transition-colors hover:bg-surface-200 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary-500 disabled:pointer-events-none disabled:opacity-40 dark:text-surface-300 dark:hover:bg-surface-700 dark:hover:text-surface-0 motion-reduce:transition-none"
                            :disabled="disabled || index === 0"
                            :aria-label="`Move row ${index + 1} up`"
                            @click.prevent="move(index, -1)"
                        >
                            <i class="pi pi-arrow-up text-xs" aria-hidden="true" />
                        </button>

                        <button
                            type="button"
                            class="grid size-7 place-items-center rounded-md text-surface-600 transition-colors hover:bg-surface-200 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary-500 disabled:pointer-events-none disabled:opacity-40 dark:text-surface-300 dark:hover:bg-surface-700 dark:hover:text-surface-0 motion-reduce:transition-none"
                            :disabled="disabled || index === model.length - 1"
                            :aria-label="`Move row ${index + 1} down`"
                            @click.prevent="move(index, 1)"
                        >
                            <i class="pi pi-arrow-down text-xs" aria-hidden="true" />
                        </button>

                        <button
                            type="button"
                            class="grid size-7 place-items-center rounded-md text-surface-600 transition-colors hover:bg-aq-danger/10 hover:text-aq-danger-strong focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-aq-danger disabled:pointer-events-none disabled:opacity-40 dark:text-surface-300 motion-reduce:transition-none"
                            :disabled="disabled || model.length <= minRows"
                            :aria-label="`Remove row ${index + 1}`"
                            @click.prevent="remove(index)"
                        >
                            <i class="pi pi-trash text-xs" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </li>
        </ol>

        <p
            v-else
            class="rounded-lg border border-dashed border-content-border px-3 py-2 text-sm text-surface-600 dark:text-surface-300"
        >
            {{ emptyMessage }}
        </p>
    </div>
</template>
