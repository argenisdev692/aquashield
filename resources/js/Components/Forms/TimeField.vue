<script setup lang="ts">
import { computed } from 'vue';
import Datepicker from '@/volt/Datepicker.vue';
import FieldShell from './FieldShell.vue';

/**
 * Time of day on the wire as `HH:mm`.
 *
 * A whole class of CRM data is time-without-a-date: business hours, the best
 * time to call, a recurring task. `DateTimeField` is the wrong shape for it —
 * it would force a date the record does not have — so this field stores the
 * wall-clock string directly and lets the server attach it to whatever date the
 * schedule uses.
 *
 * The picker needs a `Date` internally; it is built against today and never
 * emitted, which keeps the value timezone-free.
 */

interface Props {
    label: string;
    name: string;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
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
    placeholder = 'Select time',
    stepMinute = 15,
    showSeconds = false,
} = defineProps<Props>();

const model = defineModel<string | null>({ default: null });
const emit = defineEmits<{ blur: [] }>();

const pad = (value: number): string => String(value).padStart(2, '0');

const toTimeString = (date: Date): string => {
    const base = `${pad(date.getHours())}:${pad(date.getMinutes())}`;

    return showSeconds ? `${base}:${pad(date.getSeconds())}` : base;
};

const displayValue = computed<Date | null>(() => {
    if (model.value === null || model.value === '') {
        return null;
    }

    const [hours, minutes, seconds] = model.value.split(':').map(Number);

    if (hours === undefined || Number.isNaN(hours)) {
        return null;
    }

    const date = new Date();
    date.setHours(hours, minutes ?? 0, seconds ?? 0, 0);

    return date;
});

const onUpdate = (value: unknown): void => {
    if (value === null || value === undefined) {
        model.value = null;
        return;
    }

    if (value instanceof Date) {
        model.value = toTimeString(value);
        return;
    }

    if (Array.isArray(value) && value[0] instanceof Date) {
        model.value = toTimeString(value[0]);
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
            time-only
            hour-format="24"
            :show-seconds="showSeconds"
            :step-minute="stepMinute"
            :placeholder="placeholder"
            :disabled="disabled"
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
