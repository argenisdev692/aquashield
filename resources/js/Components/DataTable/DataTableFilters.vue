<script setup lang="ts">
/**
 * Advanced filters live behind a popover, not in a permanent second toolbar.
 *
 * The pattern that won in 2026 admin surfaces: one compact trigger, an active
 * count, and a panel that keeps the table's vertical space for rows. The
 * panel is deliberately a slot — status, owner, dates and "show deleted" are
 * domain decisions, while the chrome, the reset contract and the active count
 * are the reusable part.
 *
 * Filters apply live. "Done" only closes the panel, so there is no second
 * apply/commit model to keep in sync with the query key.
 */
import { computed, ref, useId, useTemplateRef } from 'vue';
import Popover from '@/volt/Popover.vue';
import TableButton from './TableButton.vue';

interface Props {
    activeCount?: number;
    label?: string;
    disabled?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{ reset: [] }>();

defineSlots<{ default(): unknown }>();

const titleId = useId();
const contentId = useId();
const open = ref(false);

const panel = useTemplateRef<{ toggle: (event: Event) => void; hide: () => void }>('panel');

const accessibleLabel = computed(() => {
    const label = props.label ?? 'Filters';

    return (props.activeCount ?? 0) > 0 ? `${label}, ${props.activeCount} active` : label;
});

const toggle = (event: Event): void => {
    panel.value?.toggle(event);
};

const hide = (): void => {
    panel.value?.hide();
};

const reset = (): void => {
    emit('reset');
};
</script>

<template>
    <div class="inline-flex">
        <TableButton
            variant="neutral"
            :aria-label="accessibleLabel"
            aria-haspopup="dialog"
            :aria-expanded="open"
            :aria-controls="contentId"
            :disabled="props.disabled"
            @click="toggle"
        >
            <i class="pi pi-filter text-sm" aria-hidden="true" />
            <span>{{ props.label ?? 'Filters' }}</span>
            <span
                v-if="(props.activeCount ?? 0) > 0"
                class="inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-semibold text-primary-contrast tabular-nums"
            >
                {{ props.activeCount }}
            </span>
            <i
                class="pi pi-chevron-down text-xs text-surface-500 dark:text-surface-400"
                aria-hidden="true"
            />
        </TableButton>

        <Popover ref="panel" @show="open = true" @hide="open = false">
            <div
                :id="contentId"
                role="dialog"
                aria-modal="false"
                :aria-labelledby="titleId"
                class="flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-4"
                @keydown.esc="hide"
            >
                <div class="flex items-center justify-between gap-3">
                    <h2
                        :id="titleId"
                        class="text-sm font-semibold text-surface-900 dark:text-surface-0"
                    >
                        {{ props.label ?? 'Filters' }}
                    </h2>

                    <TableButton
                        variant="ghost"
                        size="sm"
                        aria-label="Reset filters"
                        :disabled="(props.activeCount ?? 0) === 0"
                        @click="reset"
                    >
                        Reset
                    </TableButton>
                </div>

                <div class="flex flex-col gap-3">
                    <slot />
                </div>

                <div class="flex justify-end border-t border-aq-shell-line pt-3">
                    <TableButton variant="primary" size="sm" @click="hide">Done</TableButton>
                </div>
            </div>
        </Popover>
    </div>
</template>
