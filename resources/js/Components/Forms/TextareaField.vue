<script setup lang="ts">
import Textarea from '@/volt/Textarea.vue';
import FieldShell from './FieldShell.vue';

interface Props {
    label: string;
    name: string;
    error?: string;
    hint?: string;
    required?: boolean;
    rows?: number;
    placeholder?: string;
    disabled?: boolean;
    maxlength?: number;
    autoResize?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    rows: 4,
    autoResize: false,
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
        <Textarea
            :id="id"
            v-model="model"
            :name="props.name"
            :rows="props.rows"
            :placeholder="props.placeholder"
            :disabled="props.disabled"
            :maxlength="props.maxlength"
            :auto-resize="props.autoResize"
            :aria-describedby="describedBy"
            :aria-invalid="props.error ? true : undefined"
            :invalid="Boolean(props.error)"
            fluid
            @blur="emit('blur')"
        />
    </FieldShell>
</template>
