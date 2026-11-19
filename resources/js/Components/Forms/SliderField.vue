<script setup lang="ts">
import Slider from '@/volt/Slider.vue';
import FieldShell from './FieldShell.vue';

/**
 * Bounded numeric choice where the range matters more than the exact value.
 *
 * Deal probability, lead score and satisfaction are all 0–100 sliders in a CRM:
 * the user is choosing a position, not typing a number. `NumberField` remains
 * the right control when the exact value is the point (a price, a quantity).
 *
 * The current value is rendered as an `<output>` tied to the input id, so it is
 * visible without hovering and announced when it changes.
 */

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
    showValue?: boolean;
    formatValue?: (value: number) => string;
}

const {
    label,
    name,
    error,
    hint,
    required = false,
    disabled = false,
    min = 0,
    max = 100,
    step = 1,
    showValue = true,
    formatValue = (value: number): string => String(value),
} = defineProps<Props>();

const model = defineModel<number>({ default: 0 });
const emit = defineEmits<{ blur: [] }>();

const onUpdate = (value: unknown): void => {
    if (typeof value === 'number') {
        model.value = value;
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
        <div class="flex flex-col gap-2" @blur.capture="emit('blur')">
            <Slider
                :input-id="id"
                :model-value="model"
                :name="name"
                :min="min"
                :max="max"
                :step="step"
                :disabled="disabled"
                :aria-describedby="describedBy"
                :aria-invalid="error ? true : undefined"
                @update:model-value="onUpdate"
            />

            <div
                v-if="showValue"
                class="flex items-center justify-between gap-2 text-xs text-surface-600 dark:text-surface-300"
            >
                <span class="tabular-nums">{{ formatValue(min) }}</span>

                <output
                    :for="id"
                    class="font-medium tabular-nums text-surface-800 dark:text-surface-100"
                >
                    {{ formatValue(model) }}
                </output>

                <span class="tabular-nums">{{ formatValue(max) }}</span>
            </div>
        </div>
    </FieldShell>
</template>
