<script setup lang="ts" generic="T extends string | number">
import { computed, useId } from 'vue';
import RadioButton from '@/volt/RadioButton.vue';
import type { SelectOption } from './types';

/**
 * Radio group for a short, mutually exclusive choice.
 *
 * A select hides the options behind a click; radios show them. For the two to
 * five options a CRM record actually has — record type, priority, deal status —
 * showing them is faster and works better with a screen reader. Above five,
 * use `SelectField`.
 *
 * The group is a `fieldset`/`legend`, not a `label for`: a label cannot name
 * five inputs. The hint and the error are referenced from the fieldset, so the
 * group name and its description are announced together.
 */

interface Props {
    label: string;
    name: string;
    options: SelectOption<T>[];
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    orientation?: 'vertical' | 'horizontal';
    /** `cards` adds a bordered, clickable surface and the option description. */
    variant?: 'plain' | 'cards';
}

const {
    label,
    name,
    options,
    error,
    hint,
    required = false,
    disabled = false,
    orientation = 'vertical',
    variant = 'plain',
} = defineProps<Props>();

const model = defineModel<T | null>({ default: null });
const emit = defineEmits<{ blur: [] }>();

const uid = useId();
const hintId = `${uid}-hint`;
const errorId = `${uid}-error`;

const describedBy = computed<string | undefined>(() => {
    const ids = [hint && !error ? hintId : undefined, error ? errorId : undefined].filter(
        (value): value is string => value !== undefined,
    );

    return ids.length > 0 ? ids.join(' ') : undefined;
});

const optionClass = (option: SelectOption<T>): string => {
    const muted = disabled || option.disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer';

    if (variant === 'plain') {
        return ['items-center', muted].join(' ');
    }

    const base =
        'items-start rounded-xl border p-3 transition-colors duration-150 motion-reduce:transition-none';
    const selected =
        model.value === option.value
            ? 'border-primary bg-primary-50/70 dark:border-primary-400 dark:bg-primary-950/40'
            : 'border-content-border hover:border-surface-400 dark:hover:border-surface-600';
    const invalid = error ? 'border-aq-danger/70' : '';

    return [base, selected, invalid, muted].filter(Boolean).join(' ');
};
</script>

<template>
    <fieldset
        class="flex flex-col gap-1.5"
        :aria-describedby="describedBy"
        :aria-invalid="error ? true : undefined"
        :aria-required="required ? true : undefined"
    >
        <legend class="text-sm font-medium text-surface-800 dark:text-surface-100">
            {{ label }}
            <span v-if="required" class="text-aq-danger-strong" aria-hidden="true">*</span>
            <span v-if="required" class="sr-only">(required)</span>
        </legend>

        <div
            class="flex gap-2"
            :class="orientation === 'vertical' ? 'flex-col' : 'flex-row flex-wrap'"
        >
            <label
                v-for="option in options"
                :key="String(option.value)"
                class="flex gap-2"
                :class="optionClass(option)"
            >
                <RadioButton
                    v-model="model"
                    :value="option.value"
                    :name="name"
                    :disabled="disabled || option.disabled"
                    :invalid="Boolean(error)"
                    @blur="emit('blur')"
                />

                <span class="min-w-0">
                    <span class="block text-sm text-surface-800 dark:text-surface-100">
                        {{ option.label }}
                    </span>

                    <span
                        v-if="option.description"
                        class="block text-xs text-surface-600 dark:text-surface-300"
                    >
                        {{ option.description }}
                    </span>
                </span>
            </label>
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
    </fieldset>
</template>
