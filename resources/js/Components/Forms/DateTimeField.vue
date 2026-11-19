<script setup lang="ts">
import { computed } from 'vue';
import Datepicker from '@/volt/Datepicker.vue';
import FieldShell from './FieldShell.vue';

/**
 * Date and time in one control, on the wire as local `YYYY-MM-DDTHH:mm`.
 *
 * Activities (call, meeting, task) always carry both. Sending a `Date` or an
 * ISO string with a `Z` lets the browser's timezone move a 09:00 meeting to
 * 14:00 for the next user, so this field deliberately keeps the wall-clock
 * value the user picked. The page decides the timezone the business runs in.
 *
 * Seconds are omitted unless the caller opts in: a CRM activity is scheduled to
 * the minute, not to the millisecond.
 */

interface Props {
    label: string;
    name: string;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    dateFormat?: string;
    showButtonBar?: boolean;
    minDate?: Date;
    maxDate?: Date;
    stepMinute?: number;
    showSeconds?: boolean;
}

const {
    label,
    name,
    error,
    hint,
    required = false,
    disabled = false,
    placeholder = 'Select date and time',
    dateFormat = 'dd/mm/yy',
    showButtonBar = true,
    minDate,
    maxDate,
    stepMinute = 15,
    showSeconds = false,
} = defineProps<Props>();

const model = defineModel<string | null>({ default: null });
const emit = defineEmits<{ blur: [] }>();

const pad = (value: number): string => String(value).padStart(2, '0');

const toLocalIso = (date: Date): string => {
    const base = `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(
        date.getHours(),
    )}:${pad(date.getMinutes())}`;

    return showSeconds ? `${base}:${pad(date.getSeconds())}` : base;
};

const displayValue = computed<Date | null>(() => {
    if (model.value === null || model.value === '') {
        return null;
    }

    const [datePart, timePart = '00:00'] = model.value.split('T');
    const [year, month, day] = (datePart ?? '').split('-').map(Number);
    const [hours, minutes] = timePart.split(':').map(Number);

    if (!year || !month || !day) {
        return null;
    }

    return new Date(year, month - 1, day, hours ?? 0, minutes ?? 0);
});

const onUpdate = (value: unknown): void => {
    if (value === null || value === undefined) {
        model.value = null;
        return;
    }

    if (value instanceof Date) {
        model.value = toLocalIso(value);
        return;
    }

    if (Array.isArray(value) && value[0] instanceof Date) {
        model.value = toLocalIso(value[0]);
    }
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
        <Datepicker
            :input-id="id"
            :model-value="displayValue"
            :name="name"
            show-time
            hour-format="24"
            :show-seconds="showSeconds"
            :step-minute="stepMinute"
            :placeholder="placeholder"
            :disabled="disabled"
            :date-format="dateFormat"
            :show-button-bar="showButtonBar"
            :min-date="minDate"
            :max-date="maxDate"
            :aria-describedby="describedBy"
            :aria-invalid="error ? true : undefined"
            :invalid="Boolean(error)"
            show-icon
            fluid
            @update:model-value="onUpdate"
            @blur="emit('blur')"
        />
    </FieldShell>
</template>
