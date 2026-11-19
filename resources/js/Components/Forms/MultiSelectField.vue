<script setup lang="ts" generic="T extends string | number">
import Select from '@/volt/Select.vue';
import FieldShell from './FieldShell.vue';
import type { SelectOption } from './types';

/**
 * Multiple values from a known list: labels, segments, assignees, territories.
 *
 * PrimeVue 5 deprecates `MultiSelect`; the supported path is `Select` with
 * `multiple`. The wrapper is what keeps the wire contract honest: the model is
 * always `T[]`, never `null` or a scalar, so a page cannot accidentally submit a
 * comma-joined string to Laravel. The generic parameter lets a page keep
 * `string[]` or `number[]` instead of widening every field to a union.
 */

interface Props {
    label: string;
    name: string;
    options: SelectOption<T>[];
    error?: string;
    hint?: string;
    required?: boolean;
    placeholder?: string;
    disabled?: boolean;
    filter?: boolean;
    showClear?: boolean;
    filterPlaceholder?: string;
}

const {
    label,
    name,
    options,
    error,
    hint,
    required = false,
    placeholder,
    disabled = false,
    filter = false,
    showClear = true,
    filterPlaceholder,
} = defineProps<Props>();

const model = defineModel<T[]>({ default: () => [] });
const emit = defineEmits<{ blur: [] }>();

const onUpdate = (value: unknown): void => {
    if (!Array.isArray(value)) {
        return;
    }

    const allowed = new Set<string | number>(options.map((option) => option.value));

    model.value = value.filter(
        (item): item is T =>
            (typeof item === 'string' || typeof item === 'number') && allowed.has(item),
    );
};
</script>

<template>
    <FieldShell
        v-slot="{ id, describedBy }"
        :label="label"
        :name="name"
        :error="error"
        :hint="hint"
        :required="required"
    >
        <Select
            :input-id="id"
            :model-value="model"
            :name="name"
            :options="options"
            option-label="label"
            option-value="value"
            multiple
            :placeholder="placeholder"
            :disabled="disabled"
            :filter="filter"
            :show-clear="showClear"
            :filter-placeholder="filterPlaceholder"
            :aria-describedby="describedBy"
            :aria-invalid="error ? true : undefined"
            :invalid="Boolean(error)"
            fluid
            @update:model-value="onUpdate"
            @blur="emit('blur')"
        />
    </FieldShell>
</template>
