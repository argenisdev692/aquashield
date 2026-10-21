<script setup lang="ts" generic="T">
/**
 * Icon row actions, with the two states a soft-delete table needs.
 *
 * The array is computed by the page from the row, so a deleted row can expose
 * restore and force-delete while an active row exposes edit and delete. Icons
 * never carry the meaning alone: every control has an `aria-label` and a
 * tooltip. Actions with a `confirm` block route through ConfirmDialog, which is
 * used for delete and restore alike: both change row state, and a misclick in a
 * dense action column should still be recoverable.
 */
import { computed } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import type { RowAction, RowActionTone } from './types';
import TableButton from './TableButton.vue';

interface Props {
    row: T;
    actions: readonly RowAction<T>[];
    rowLabel?: string;
    disabled?: boolean;
}

const props = defineProps<Props>();
const confirm = useConfirm();

const TONE_CLASS: Record<RowActionTone, string> = {
    default: 'text-surface-500 dark:text-surface-400',
    accent: 'text-primary-700 dark:text-primary-300',
    danger: 'text-aq-danger-strong',
};

const tooltipClass =
    'pointer-events-none absolute rounded-md bg-surface-900 px-2.5 py-1.5 text-xs font-medium whitespace-nowrap text-surface-0 shadow-lg dark:bg-surface-800 dark:ring-1 dark:ring-surface-700';

const visibleActions = computed(() =>
    props.actions.filter((action) => action.hidden?.(props.row) !== true),
);

const groupLabel = computed(() =>
    props.rowLabel ? `Actions for ${props.rowLabel}` : 'Row actions',
);

const isDisabled = (action: RowAction<T>): boolean =>
    props.disabled === true || action.disabled?.(props.row) === true;

const run = (action: RowAction<T>): void => {
    if (isDisabled(action)) {
        return;
    }

    if (!action.confirm) {
        void action.action(props.row);
        return;
    }

    const message =
        typeof action.confirm.message === 'function'
            ? action.confirm.message(props.row)
            : action.confirm.message;

    confirm.require({
        header: action.confirm.header,
        message,
        icon: 'pi pi-exclamation-triangle',
        acceptProps: { label: action.confirm.acceptLabel ?? action.label },
        rejectProps: { label: action.confirm.rejectLabel ?? 'Cancel' },
        accept: () => {
            void action.action(props.row);
        },
    });
};
</script>

<template>
    <div
        v-if="visibleActions.length > 0"
        class="flex items-center justify-end gap-0.5"
        role="group"
        :aria-label="groupLabel"
    >
        <TableButton
            v-for="action in visibleActions"
            :key="action.key"
            variant="ghost"
            size="sm"
            icon-only
            :disabled="isDisabled(action)"
            :aria-label="action.label"
            v-tooltip.top="{ value: action.label, showDelay: 300, class: tooltipClass }"
            @click="run(action)"
        >
            <i
                :class="[action.icon, 'text-sm', TONE_CLASS[action.tone ?? 'default']]"
                aria-hidden="true"
            />
        </TableButton>
    </div>
</template>
