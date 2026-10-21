<script setup lang="ts">
/**
 * Label + control + hint, with the id wiring done once.
 *
 * Every filter control needs a real `<label for>`, and a filter panel is
 * exactly where ids get hand-invented and drift. `useId` keeps them unique
 * across the two table instances a dashboard can mount at once.
 */
import { useId } from 'vue';

interface Props {
    label: string;
    hint?: string;
}

const props = defineProps<Props>();

defineSlots<{ default(slotProps: { id: string; describedBy: string | undefined }): unknown }>();

const id = useId();
const hintId = `${id}-hint`;
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="id" class="text-sm font-medium text-surface-700 dark:text-surface-200">
            {{ props.label }}
        </label>

        <slot :id="id" :described-by="props.hint ? hintId : undefined" />

        <p v-if="props.hint" :id="hintId" class="text-xs text-surface-500 dark:text-surface-300">
            {{ props.hint }}
        </p>
    </div>
</template>
