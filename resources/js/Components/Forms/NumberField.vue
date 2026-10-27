<script setup lang="ts">
import InputNumber from '@/volt/InputNumber.vue';
import FieldShell from './FieldShell.vue';

interface Props {
    label: string;
    name: string;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    min?: number;
    max?: number;
    step?: number;
    showButtons?: boolean;
    mode?: 'decimal' | 'currency';
    currency?: string;
    locale?: string;
    placeholder?: string;
}

const props = withDefaults(defineProps<Props>(), {
    showButtons: false,
    mode: 'decimal',
});

const model = defineModel<number | null>({ default: null });
const emit = defineEmits<{ blur: [] }>();

const onUpdate = (value: unknown): void => {
    if (value === null || typeof value === 'number') {
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
        <InputNumber
            :input-id="id"
            :model-value="model"
            :name="props.name"
            :min="props.min"
            :max="props.max"
            :step="props.step"
            :show-buttons="props.showButtons"
            :mode="props.mode"
            :currency="props.currency"
            :locale="props.locale"
            :placeholder="props.placeholder"
            :disabled="props.disabled"
            :aria-describedby="describedBy"
            :aria-invalid="props.error ? true : undefined"
            :invalid="Boolean(props.error)"
            fluid
            @update:model-value="onUpdate"
            @blur="emit('blur')"
        />
    </FieldShell>
</template>
