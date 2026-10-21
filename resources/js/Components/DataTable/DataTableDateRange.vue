<script setup lang="ts">
/**
 * From/to date filters without a Date object in the wire contract.
 *
 * PrimeVue's Datepicker is the right control for a scheduling form; a table
 * filter only needs ISO `YYYY-MM-DD` strings, and the native date input is
 * keyboard-accessible, has a real picker, and serialises without a conversion
 * layer. The min/max attributes give the picker the rule; the inline alert is
 * the visible half of the same rule, and the page still validates server-side.
 */
import { computed, useId } from 'vue';

interface Props {
    label?: string;
    hint?: string;
}

const props = defineProps<Props>();
const from = defineModel<string>('from', { default: '' });
const to = defineModel<string>('to', { default: '' });

const fromId = useId();
const toId = useId();
const hintId = useId();

const invalid = computed(() => from.value !== '' && to.value !== '' && from.value > to.value);

const describedBy = computed(() => {
    const ids: string[] = [];

    if (props.hint) {
        ids.push(hintId);
    }

    if (invalid.value) {
        ids.push(`${hintId}-error`);
    }

    return ids.length > 0 ? ids.join(' ') : undefined;
});

const inputClass = (isInvalid: boolean): string =>
    [
        'h-9 w-full rounded-md border bg-surface-0 px-2.5 text-sm text-surface-700 shadow-sm',
        'transition-colors duration-150 placeholder:text-surface-500 focus:border-primary',
        'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500',
        'dark:bg-surface-900 dark:text-surface-0 dark:placeholder:text-surface-300',
        '[color-scheme:light] dark:[color-scheme:dark] motion-reduce:transition-none',
        isInvalid
            ? 'border-aq-danger-strong'
            : 'border-content-border hover:border-surface-400 dark:hover:border-surface-600',
    ].join(' ');
</script>

<template>
    <fieldset class="flex flex-col gap-1.5">
        <legend class="text-sm font-medium text-surface-700 dark:text-surface-200">
            {{ props.label ?? 'Date range' }}
        </legend>

        <div class="grid grid-cols-2 gap-2">
            <div class="flex flex-col gap-1">
                <label
                    :for="fromId"
                    class="text-xs font-medium text-surface-600 dark:text-surface-300"
                >
                    From
                </label>
                <input
                    :id="fromId"
                    v-model="from"
                    type="date"
                    :max="to || undefined"
                    :aria-invalid="invalid"
                    :aria-describedby="describedBy"
                    :class="inputClass(invalid)"
                />
            </div>

            <div class="flex flex-col gap-1">
                <label
                    :for="toId"
                    class="text-xs font-medium text-surface-600 dark:text-surface-300"
                >
                    To
                </label>
                <input
                    :id="toId"
                    v-model="to"
                    type="date"
                    :min="from || undefined"
                    :aria-invalid="invalid"
                    :aria-describedby="describedBy"
                    :class="inputClass(invalid)"
                />
            </div>
        </div>

        <p v-if="props.hint" :id="hintId" class="text-xs text-surface-500 dark:text-surface-300">
            {{ props.hint }}
        </p>

        <p
            v-if="invalid"
            :id="`${hintId}-error`"
            role="alert"
            class="text-xs font-medium text-aq-danger-strong"
        >
            The start date must be on or before the end date.
        </p>
    </fieldset>
</template>
