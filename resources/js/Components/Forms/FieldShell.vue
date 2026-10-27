<script setup lang="ts">
import { computed, useId } from 'vue';

/**
 * Label + control + hint + error, with the ARIA wiring done once.
 *
 * The ids are derived from the field name so a `FormErrorSummary` link can find
 * the control with `[name="…"]`, and the hint/error ids are referenced through
 * `aria-describedby` by every field wrapper that uses this shell.
 */

interface Props {
    label: string;
    name?: string;
    hint?: string;
    error?: string;
    required?: boolean;
    invalid?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    name: 'field',
    invalid: false,
});

defineSlots<{
    default(props: { id: string; describedBy?: string; invalid: boolean }): unknown;
}>();

const uid = useId();
const safeName = computed<string>(() => props.name.replace(/[^a-zA-Z0-9_-]/g, '-'));
const id = computed<string>(() => `${safeName.value}-${uid}`);
const hintId = computed<string>(() => `${id.value}-hint`);
const errorId = computed<string>(() => `${id.value}-error`);

const describedBy = computed<string | undefined>(() => {
    const ids = [
        props.hint ? hintId.value : undefined,
        props.error ? errorId.value : undefined,
    ].filter((value): value is string => value !== undefined);

    return ids.length > 0 ? ids.join(' ') : undefined;
});
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="id" class="text-sm font-medium text-surface-800 dark:text-surface-100">
            {{ props.label }}
            <span v-if="props.required" class="text-aq-danger-strong" aria-hidden="true">*</span>
            <span v-if="props.required" class="sr-only">(required)</span>
        </label>

        <slot
            :id="id"
            :described-by="describedBy"
            :invalid="props.invalid || Boolean(props.error)"
        />

        <p
            v-if="props.hint && !props.error"
            :id="hintId"
            class="text-xs text-surface-600 dark:text-surface-300"
        >
            {{ props.hint }}
        </p>

        <p
            v-if="props.error"
            :id="errorId"
            role="alert"
            class="text-xs font-medium text-aq-danger-strong"
        >
            {{ props.error }}
        </p>
    </div>
</template>
