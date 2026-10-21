<script setup lang="ts">
/**
 * Contextual selection bar.
 *
 * It appears only while rows are selected and sits above the table rather
 * than floating over the last row: a bar that covers the data it operates on
 * is the accessibility and mis-click failure the 2026 pattern replaced.
 *
 * Delete and restore both go through ConfirmDialog (mounted once in AppLayout)
 * — never `window.confirm()`. A restore is reversible, but it is still a state
 * change: asking first is what stops a misclick in a dense selection bar. Both
 * actions are present because a soft-deleted view is a first-class state, not an
 * afterthought.
 */
import { computed } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import TableButton from './TableButton.vue';

interface Props {
    count: number;
    canRestore?: boolean;
    busy?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{ clear: []; delete: []; restore: [] }>();

defineSlots<{ default(): unknown }>();

const confirm = useConfirm();

const noun = computed(() => (props.count === 1 ? 'record' : 'records'));

const askDelete = (): void => {
    const message = props.canRestore
        ? `Delete ${props.count} selected ${noun.value}? You can restore them from the deleted view.`
        : `Delete ${props.count} selected ${noun.value}? This action cannot be undone.`;

    confirm.require({
        header: 'Delete selected records',
        message,
        icon: 'pi pi-exclamation-triangle',
        acceptProps: { label: 'Delete' },
        rejectProps: { label: 'Cancel' },
        accept: () => emit('delete'),
    });
};

const askRestore = (): void => {
    confirm.require({
        header: 'Restore selected records',
        message: `Restore ${props.count} selected ${noun.value}? They will become active again.`,
        icon: 'pi pi-replay',
        acceptProps: { label: 'Restore' },
        rejectProps: { label: 'Cancel' },
        accept: () => emit('restore'),
    });
};
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-2 rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 dark:border-primary-800 dark:bg-primary-950/40"
    >
        <p
            class="flex items-center gap-2 text-sm font-medium text-surface-700 dark:text-surface-200"
            role="status"
            aria-live="polite"
        >
            <span
                class="inline-flex min-w-6 items-center justify-center rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-primary-contrast tabular-nums"
            >
                {{ props.count }}
            </span>
            {{ noun }} selected
        </p>

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <TableButton
                v-if="props.canRestore"
                variant="neutral"
                size="sm"
                :disabled="props.busy"
                @click="askRestore"
            >
                <i
                    class="pi pi-replay text-sm text-primary-700 dark:text-primary-300"
                    aria-hidden="true"
                />
                <span>Restore</span>
            </TableButton>

            <TableButton variant="danger" size="sm" :disabled="props.busy" @click="askDelete">
                <i class="pi pi-trash text-sm" aria-hidden="true" />
                <span>Delete</span>
            </TableButton>

            <slot />

            <TableButton
                variant="ghost"
                size="sm"
                icon-only
                :disabled="props.busy"
                aria-label="Clear selection"
                @click="emit('clear')"
            >
                <i class="pi pi-times text-sm" aria-hidden="true" />
            </TableButton>
        </div>
    </div>
</template>
