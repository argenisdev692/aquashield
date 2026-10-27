<script setup lang="ts">
import { computed } from 'vue';
import Datepicker from '@/volt/Datepicker.vue';
import FieldShell from './FieldShell.vue';

/**
 * Date field whose wire value is an ISO calendar date (`YYYY-MM-DD`), never a
 * JavaScript `Date`. The picker displays a localised string; the form stores
 * the date the server can validate without a timezone conversion.
 */

interface Props {
    label: string;
    name: string;
    error?: string;
    hint?: string;
    required?: boolean;
    placeholder?: string;
    disabled?: boolean;
    dateFormat?: string;
    showButtonBar?: boolean;
    manualInput?: boolean;
    minDate?: Date;
    maxDate?: Date;
}

const props = withDefaults(defineProps<Props>(), {
    dateFormat: 'dd/mm/yy',
    showButtonBar: true,
    manualInput: true,
});

const model = defineModel<string | null>({ default: null });
const emit = defineEmits<{ blur: [] }>();

const pad = (value: number): string => String(value).padStart(2, '0');

const toIsoDate = (date: Date): string =>
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

const displayValue = computed<Date | null>(() => {
    if (model.value === null || model.value === '') {
        return null;
    }

    const [year, month, day] = model.value.split('-').map(Number);

    if (!year || !month || !day) {
        return null;
    }

    return new Date(year, month - 1, day);
});

const onUpdate = (value: unknown): void => {
    if (value === null || value === undefined) {
        model.value = null;
        return;
    }

    if (value instanceof Date) {
        model.value = toIsoDate(value);
        return;
    }

    if (Array.isArray(value)) {
        const [first] = value;

        if (first instanceof Date) {
            model.value = toIsoDate(first);
        }

        return;
    }

    if (typeof value === 'string') {
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
        <Datepicker
            :input-id="id"
            :model-value="displayValue"
            :name="props.name"
            :placeholder="props.placeholder"
            :disabled="props.disabled"
            :date-format="props.dateFormat"
            :show-button-bar="props.showButtonBar"
            :manual-input="props.manualInput"
            :min-date="props.minDate"
            :max-date="props.maxDate"
            :aria-describedby="describedBy"
            :aria-invalid="props.error ? true : undefined"
            :invalid="Boolean(props.error)"
            show-icon
            fluid
            @update:model-value="onUpdate"
            @blur="emit('blur')"
        />
    </FieldShell>
</template>
