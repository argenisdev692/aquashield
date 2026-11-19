<script setup lang="ts">
/**
 * A read-only address field derived from a Google Place.
 *
 * The value is protected because a keystroke here would silently de-sync the
 * address from its verified coordinates. The pencil is the deliberate,
 * keyboard-reachable override; unlocking is a local UI decision owned by the
 * parent section, which focuses the field after the swap.
 */
import { useId } from 'vue';
import Button from '@/volt/Button.vue';
import Inputtext from '@/volt/Inputtext.vue';

const props = defineProps<{
    label: string;
    name: string;
    value: string;
    error?: string;
}>();

const emit = defineEmits<{ unlock: [] }>();

const uid = useId();
const id = `${props.name}-${uid}`;
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label :for="id" class="text-sm font-medium text-surface-800 dark:text-surface-100">
            {{ props.label }}
        </label>

        <div class="flex items-center gap-2">
            <Inputtext
                :id="id"
                :name="props.name"
                :value="props.value"
                readonly
                aria-readonly="true"
                :aria-invalid="props.error ? true : undefined"
                :invalid="Boolean(props.error)"
                class="w-full bg-surface-50 text-surface-600 dark:bg-surface-950/60 dark:text-surface-300"
                fluid
            />

            <Button
                v-tooltip.bottom="`Edit ${props.label.toLowerCase()} manually`"
                type="button"
                text
                rounded
                severity="secondary"
                class="shrink-0"
                :aria-label="`Edit ${props.label.toLowerCase()} manually`"
                @click="emit('unlock')"
            >
                <i class="pi pi-pencil text-xs" aria-hidden="true" />
            </Button>
        </div>

        <p v-if="props.error" role="alert" class="text-xs font-medium text-aq-danger-strong">
            {{ props.error }}
        </p>

        <p v-else class="text-xs text-surface-500 dark:text-surface-400">
            Filled by Google. Use the pencil to edit it by hand.
        </p>
    </div>
</template>
