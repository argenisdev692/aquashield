<script setup lang="ts">
import { FieldShell } from '@/Components/Forms';
import type { SelectOption } from '@/Components/Forms';
import Select from '@/volt/Select.vue';

/**
 * Multi-value select with the form kit's label/error wiring.
 *
 * PrimeVue 5 replaced `MultiSelect` with `Select multiple`; this wrapper keeps
 * that v5 API in one page-private place and narrows the emitted value back to
 * `string[]` so the form never receives `unknown`.
 */

interface Props {
    label: string;
    name: string;
    options: SelectOption[];
    error?: string;
    hint?: string;
    filterPlaceholder?: string;
}

const props = defineProps<Props>();

const model = defineModel<string[]>({ default: () => [] });

const onUpdate = (value: unknown): void => {
    if (Array.isArray(value)) {
        model.value = value.filter((item): item is string => typeof item === 'string');
    }
};
</script>

<template>
    <FieldShell
        v-slot="{ id, describedBy }"
        :label="props.label"
        :name="props.name"
        :error="props.error"
        :hint="props.hint"
    >
        <Select
            :input-id="id"
            :model-value="model"
            :options="props.options"
            option-label="label"
            option-value="value"
            multiple
            filter
            show-clear
            :filter-placeholder="props.filterPlaceholder ?? 'Filter…'"
            :aria-describedby="describedBy"
            :aria-invalid="props.error ? true : undefined"
            :invalid="Boolean(props.error)"
            fluid
            @update:model-value="onUpdate"
        />
    </FieldShell>
</template>
