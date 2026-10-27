<script setup lang="ts">
import Select from '@/volt/Select.vue';
import FieldShell from './FieldShell.vue';
import type { SelectOption } from './types';

interface Props {
    label: string;
    name: string;
    options: SelectOption[];
    error?: string;
    hint?: string;
    required?: boolean;
    placeholder?: string;
    disabled?: boolean;
    filter?: boolean;
    showClear?: boolean;
    filterPlaceholder?: string;
}

const props = withDefaults(defineProps<Props>(), {
    filter: false,
    showClear: false,
});

const model = defineModel<string | number | null>({ default: null });
const emit = defineEmits<{ blur: [] }>();

const onUpdate = (value: unknown): void => {
    if (value === null || typeof value === 'string' || typeof value === 'number') {
        model.value = value;
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
        :required="props.required"
    >
        <Select
            :input-id="id"
            :model-value="model"
            :name="props.name"
            :options="props.options"
            option-label="label"
            option-value="value"
            :placeholder="props.placeholder"
            :disabled="props.disabled"
            :filter="props.filter"
            :show-clear="props.showClear"
            :filter-placeholder="props.filterPlaceholder"
            :aria-describedby="describedBy"
            :aria-invalid="props.error ? true : undefined"
            :invalid="Boolean(props.error)"
            fluid
            @update:model-value="onUpdate"
            @blur="emit('blur')"
        />
    </FieldShell>
</template>
