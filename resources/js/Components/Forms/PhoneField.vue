<script setup lang="ts">
import { ref, watch } from 'vue';
import type { CountryCode } from 'libphonenumber-js';
import Inputtext from '@/volt/Inputtext.vue';
import FieldShell from './FieldShell.vue';
import { DEFAULT_PHONE_COUNTRY, formatPhone, toE164 } from '@/lib/phone';

/**
 * International phone input.
 *
 * The visible value is formatted as the user types (`AsYouType`); the model is
 * the E.164 number once it is valid, so the schema and the server receive one
 * canonical shape. An incomplete number keeps the raw draft, which lets the
 * Zod rule explain what is missing instead of silently writing `+1`.
 */

interface Props {
    label: string;
    name: string;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    autofocus?: boolean;
    placeholder?: string;
    country?: CountryCode;
}

const props = withDefaults(defineProps<Props>(), {
    country: DEFAULT_PHONE_COUNTRY,
    placeholder: '(555) 000-0000',
});

const model = defineModel<string>({ default: '' });
const emit = defineEmits<{ blur: [] }>();

const display = ref<string>(formatPhone(model.value, props.country));

watch(model, (value) => {
    const next = formatPhone(value, props.country);

    if (next !== display.value) {
        display.value = next;
    }
});

const onUpdate = (value: string): void => {
    display.value = formatPhone(value, props.country);
    model.value = toE164(value, props.country) ?? value;
};
</script>

<template>
    <FieldShell
        v-slot="{ id, describedBy }"
        :label="props.label"
        :name="props.name"
        :error="props.error"
        :hint="props.hint ?? 'Include the country code when dialling from outside the US.'"
        :required="props.required"
    >
        <Inputtext
            :id="id"
            :model-value="display"
            :name="props.name"
            type="tel"
            inputmode="tel"
            autocomplete="tel"
            :placeholder="props.placeholder"
            :disabled="props.disabled"
            :autofocus="props.autofocus"
            maxlength="24"
            :aria-describedby="describedBy"
            :aria-invalid="props.error ? true : undefined"
            :invalid="Boolean(props.error)"
            fluid
            @update:model-value="onUpdate"
            @blur="emit('blur')"
        />
    </FieldShell>
</template>
