<script setup lang="ts">
import { computed } from 'vue';
import Datepicker from '@/volt/Datepicker.vue';
import FieldShell from './FieldShell.vue';

/**
 * A from/to range on the wire as two ISO dates, not a `Date[]`.
 *
 * Reporting and pipeline filters ask for a period far more often than a single
 * day, and a range picker with two calendar panes is faster than two separate
 * `DateField`s. The models stay `YYYY-MM-DD` so the server never receives a
 * timezone-shifted timestamp, and the presets are computed in local time like
 * `DateField`.
 *
 * The optional presets are the relative ranges CRM users actually ask for
 * ("last 30 days", "this quarter"); a form that edits a fixed contract period
 * leaves them off.
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
    manualInput?: boolean;
    numberOfMonths?: number;
    minDate?: Date;
    maxDate?: Date;
    /** Relative-range shortcuts. Off for a fixed period, on for a filter. */
    presets?: boolean;
}

const {
    label,
    name,
    error,
    hint,
    required = false,
    disabled = false,
    placeholder,
    dateFormat = 'dd/mm/yy',
    showButtonBar = true,
    manualInput = true,
    numberOfMonths = 2,
    minDate,
    maxDate,
    presets = false,
} = defineProps<Props>();

const from = defineModel<string | null>('from', { default: null });
const to = defineModel<string | null>('to', { default: null });
const emit = defineEmits<{ blur: [] }>();

const pad = (value: number): string => String(value).padStart(2, '0');

const toIsoDate = (date: Date): string =>
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

const parseIsoDate = (value: string | null): Date | null => {
    if (value === null || value === '') {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return null;
    }

    return new Date(year, month - 1, day);
};

const displayValue = computed<Date[] | null>(() => {
    const start = parseIsoDate(from.value);
    const end = parseIsoDate(to.value);
    const first = start ?? end;

    if (first === null) {
        return null;
    }

    return end === null ? [first] : [first, end];
});

const onUpdate = (value: unknown): void => {
    if (value === null || value === undefined) {
        from.value = null;
        to.value = null;
        return;
    }

    if (value instanceof Date) {
        from.value = toIsoDate(value);
        to.value = null;
        return;
    }

    if (!Array.isArray(value)) {
        return;
    }

    const start = value[0];
    const end = value[1];

    from.value = start instanceof Date ? toIsoDate(start) : null;
    to.value = end instanceof Date ? toIsoDate(end) : null;
};

const today = (): Date => {
    const date = new Date();
    date.setHours(0, 0, 0, 0);

    return date;
};

const applyLastDays = (days: number): void => {
    const end = today();
    const start = new Date(end);
    start.setDate(start.getDate() - (days - 1));

    from.value = toIsoDate(start);
    to.value = toIsoDate(end);
};

const applyThisQuarter = (): void => {
    const end = today();
    const start = new Date(end.getFullYear(), Math.floor(end.getMonth() / 3) * 3, 1);

    from.value = toIsoDate(start);
    to.value = toIsoDate(end);
};

const clearRange = (): void => {
    from.value = null;
    to.value = null;
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
        <div class="flex flex-col gap-2">
            <Datepicker
                :input-id="id"
                :model-value="displayValue"
                :name="name"
                selection-mode="range"
                :placeholder="placeholder"
                :disabled="disabled"
                :date-format="dateFormat"
                :show-button-bar="showButtonBar"
                :manual-input="manualInput"
                :number-of-months="numberOfMonths"
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

            <div v-if="presets" class="flex flex-wrap gap-1.5">
                <button
                    v-for="preset in [
                        { label: 'Last 7 days', days: 7 },
                        { label: 'Last 30 days', days: 30 },
                        { label: 'Last 90 days', days: 90 },
                    ]"
                    :key="preset.days"
                    type="button"
                    :disabled="disabled"
                    class="rounded-full border border-content-border px-2.5 py-1 text-xs font-medium text-surface-700 transition-colors duration-150 hover:border-surface-400 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 disabled:pointer-events-none disabled:opacity-50 dark:text-surface-200 dark:hover:border-surface-600 dark:hover:bg-surface-800 motion-reduce:transition-none"
                    @click="applyLastDays(preset.days)"
                >
                    {{ preset.label }}
                </button>

                <button
                    type="button"
                    :disabled="disabled"
                    class="rounded-full border border-content-border px-2.5 py-1 text-xs font-medium text-surface-700 transition-colors duration-150 hover:border-surface-400 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 disabled:pointer-events-none disabled:opacity-50 dark:text-surface-200 dark:hover:border-surface-600 dark:hover:bg-surface-800 motion-reduce:transition-none"
                    @click="applyThisQuarter"
                >
                    This quarter
                </button>

                <button
                    type="button"
                    :disabled="disabled"
                    class="rounded-full px-2.5 py-1 text-xs font-medium text-surface-600 transition-colors duration-150 hover:bg-surface-100 hover:text-surface-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 disabled:pointer-events-none disabled:opacity-50 dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-surface-0 motion-reduce:transition-none"
                    @click="clearRange"
                >
                    Clear
                </button>
            </div>
        </div>
    </FieldShell>
</template>
