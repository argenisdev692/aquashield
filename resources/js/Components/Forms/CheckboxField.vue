<script setup lang="ts">
import { computed, useId } from 'vue';
import Checkbox from '@/volt/Checkbox.vue';

/**
 * Binary checkbox with an inline label.
 *
 * A checkbox label belongs beside the box, not above it, so this does not use
 * `FieldShell`. The error still gets an id, `role="alert"` and an
 * `aria-describedby` reference on the input.
 */

interface Props {
    label: string;
    name: string;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    required: false,
    disabled: false,
});

const model = defineModel<boolean>({ default: false });
const emit = defineEmits<{ blur: [] }>();

const uid = useId();
const inputId = computed<string>(() => `checkbox-${props.name}-${uid}`);
const hintId = computed<string>(() => `${inputId.value}-hint`);
const errorId = computed<string>(() => `${inputId.value}-error`);
const describedBy = computed<string | undefined>(() => {
    const ids = [
        props.hint ? hintId.value : undefined,
        props.error ? errorId.value : undefined,
    ].filter((value): value is string => value !== undefined);

    return ids.length ? ids.join(' ') : undefined;
});
</script>

<template>
    <div class="flex flex-col gap-1">
        <div class="flex items-start gap-2">
            <Checkbox
                :input-id="inputId"
                v-model="model"
                :name="props.name"
                binary
                :disabled="props.disabled"
                :invalid="Boolean(props.error)"
                :aria-describedby="describedBy"
                :aria-invalid="props.error ? true : undefined"
                @blur="emit('blur')"
            />

            <label :for="inputId" class="text-sm text-surface-700 dark:text-surface-200">
                {{ props.label }}
                <span v-if="props.required" class="text-aq-danger-strong" aria-hidden="true"
                    >*</span
                >
                <span v-if="props.required" class="sr-only">(required)</span>
            </label>
        </div>

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
