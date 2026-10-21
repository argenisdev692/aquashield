<script setup lang="ts">
/**
 * Empty state that distinguishes "nothing yet" from "nothing matched".
 *
 * The copy is the page's decision; the component only keeps the visual weight
 * correct inside a table cell and gives the action slot a home. `role="status"`
 * announces the result after a filtered request finishes.
 */
interface Props {
    title?: string;
    description?: string;
    icon?: string;
}

const props = defineProps<Props>();

defineSlots<{ default(): unknown }>();
</script>

<template>
    <div
        role="status"
        class="flex flex-col items-center justify-center gap-2 px-6 py-14 text-center"
    >
        <span
            class="grid size-12 place-items-center rounded-full bg-primary-50 text-xl text-primary-700 dark:bg-primary-950 dark:text-primary-300"
            aria-hidden="true"
        >
            <i :class="props.icon ?? 'pi pi-inbox'" />
        </span>

        <h3 class="text-sm font-semibold text-surface-900 dark:text-surface-0">
            {{ props.title ?? 'No records yet' }}
        </h3>

        <p class="max-w-sm text-sm text-surface-500 dark:text-surface-300">
            {{ props.description ?? 'Records will appear here once they are created.' }}
        </p>

        <div v-if="$slots.default" class="mt-2">
            <slot />
        </div>
    </div>
</template>
