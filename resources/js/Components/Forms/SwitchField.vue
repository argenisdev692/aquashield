<script setup lang="ts">
import { computed, useId } from 'vue';
import Toggleswitch from '@/volt/Toggleswitch.vue';

/**
 * Boolean setting with the label on the left and the switch on the right.
 *
 * A checkbox says "select this"; a switch says "this is on or off" and applies
 * immediately. CRM records are full of the second kind — do not call, active,
 * auto-assign, sync to marketing — so the kit needs both controls.
 *
 * The component keeps the same error contract as `CheckboxField`: the message
 * has an id, `role="alert"`, and the input points at it with
 * `aria-describedby`.
 */

interface Props {
    label: string;
    name: string;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
}

const { label, name, error, hint, required = false, disabled = false } = defineProps<Props>();

const model = defineModel<boolean>({ default: false });
const emit = defineEmits<{ blur: [] }>();

const uid = useId();
const inputId = `switch-${name}-${uid}`;
const hintId = `${inputId}-hint`;
const errorId = `${inputId}-error`;

const describedBy = computed<string | undefined>(() => {
    const ids = [hint && !error ? hintId : undefined, error ? errorId : undefined].filter(
        (value): value is string => value !== undefined,
    );

    return ids.length > 0 ? ids.join(' ') : undefined;
});

const onUpdate = (value: unknown): void => {
    if (typeof value === 'boolean') {
        model.value = value;
    }
};
</script>

<template>
    <div class="flex flex-col gap-1">
        <div class="flex items-start justify-between gap-4">
            <label
                :for="inputId"
                class="min-w-0 text-sm font-medium text-surface-800 dark:text-surface-100"
            >
                {{ label }}
                <span v-if="required" class="text-aq-danger-strong" aria-hidden="true">*</span>
                <span v-if="required" class="sr-only">(required)</span>
            </label>

            <Toggleswitch
                :input-id="inputId"
                :model-value="model"
                :name="name"
                :disabled="disabled"
                :invalid="Boolean(error)"
                :aria-describedby="describedBy"
                :aria-invalid="error ? true : undefined"
                @update:model-value="onUpdate"
                @blur="emit('blur')"
            />
        </div>

        <p
            v-if="hint && !error"
            :id="hintId"
            class="text-xs text-surface-600 dark:text-surface-300"
        >
            {{ hint }}
        </p>

        <p
            v-if="error"
            :id="errorId"
            role="alert"
            class="text-xs font-medium text-aq-danger-strong"
        >
            {{ error }}
        </p>
    </div>
</template>
