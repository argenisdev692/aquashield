<script setup lang="ts">
import { ref } from 'vue';
import EyeIcon from '@primevue/icons/eye';
import EyeSlashIcon from '@primevue/icons/eyeslash';
import InputPassword from '@/volt/InputPassword.vue';
import FieldShell from './FieldShell.vue';

/**
 * Password field with an explicit show/hide control.
 *
 * Built on PrimeVue 5's `InputPassword`, not the deprecated `Password`, so the
 * control survives the v6 removal. The toggle is a real button with
 * `aria-pressed`, and the field keeps the same label/error wiring as every
 * other input in the kit.
 */

interface Props {
    label: string;
    name: string;
    error?: string;
    hint?: string;
    required?: boolean;
    autocomplete?: string;
    placeholder?: string;
    disabled?: boolean;
    autofocus?: boolean;
    maxlength?: number;
    minlength?: number;
}

const props = withDefaults(defineProps<Props>(), {
    autocomplete: 'current-password',
});

const model = defineModel<string>({ default: '' });
const emit = defineEmits<{ blur: [] }>();

const masked = ref<boolean>(true);
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
        <div class="relative" @focusout="emit('blur')">
            <InputPassword
                :id="id"
                v-model="model"
                v-model:mask="masked"
                :name="props.name"
                :autocomplete="props.autocomplete"
                :placeholder="props.placeholder"
                :disabled="props.disabled"
                :autofocus="props.autofocus"
                :maxlength="props.maxlength"
                :minlength="props.minlength"
                :aria-describedby="describedBy"
                :aria-invalid="props.error ? true : undefined"
                :invalid="Boolean(props.error)"
                fluid
            />

            <button
                type="button"
                class="absolute inset-y-0 end-0 flex items-center rounded-e-md px-3 text-surface-500 transition-colors duration-150 hover:text-surface-700 focus-visible:text-surface-700 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-500 disabled:pointer-events-none disabled:opacity-50 dark:text-surface-400 dark:hover:text-surface-200 dark:focus-visible:text-surface-200 motion-reduce:transition-none"
                :disabled="props.disabled"
                :aria-label="masked ? 'Show password' : 'Hide password'"
                :aria-pressed="!masked"
                @click="masked = !masked"
            >
                <EyeIcon v-if="masked" class="size-4" aria-hidden="true" />
                <EyeSlashIcon v-else class="size-4" aria-hidden="true" />
            </button>
        </div>
    </FieldShell>
</template>
