<script setup lang="ts">
import Inputtext from '@/volt/Inputtext.vue';
import FieldShell from './FieldShell.vue';

/**
 * Text input with label, hint and error already wired.
 * The model is a plain string; validation stays in the page's Zod schema.
 */

interface Props {
    label: string;
    name: string;
    error?: string;
    hint?: string;
    required?: boolean;
    type?: 'text' | 'email' | 'search' | 'tel' | 'url';
    autocomplete?: string;
    placeholder?: string;
    disabled?: boolean;
    autofocus?: boolean;
    inputmode?: 'text' | 'email' | 'tel' | 'numeric' | 'decimal' | 'search' | 'url';
    maxlength?: number;
    minlength?: number;
    spellcheck?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    type: 'text',
    spellcheck: false,
});

const model = defineModel<string>({ default: '' });
const emit = defineEmits<{ blur: [] }>();
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
        <Inputtext
            :id="id"
            v-model="model"
            :name="props.name"
            :type="props.type"
            :autocomplete="props.autocomplete"
            :placeholder="props.placeholder"
            :disabled="props.disabled"
            :autofocus="props.autofocus"
            :inputmode="props.inputmode"
            :maxlength="props.maxlength"
            :minlength="props.minlength"
            :spellcheck="props.spellcheck"
            :aria-describedby="describedBy"
            :aria-invalid="props.error ? true : undefined"
            :invalid="Boolean(props.error)"
            fluid
            @blur="emit('blur')"
        />
    </FieldShell>
</template>
