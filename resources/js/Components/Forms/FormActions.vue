<script setup lang="ts">
import AppButton from './AppButton.vue';
import SecondaryButton from '@/volt/SecondaryButton.vue';

/**
 * The submit/cancel row shared by page forms and dialogs.
 * Kept semantic: submit is the primary action, cancel is secondary, and the
 * order is reversed on narrow screens so the primary target is closest to the
 * thumb.
 */

interface Props {
    submitLabel?: string;
    cancelLabel?: string;
    busy?: boolean;
    showCancel?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    submitLabel: 'Save',
    cancelLabel: 'Cancel',
    busy: false,
    showCancel: true,
});

const emit = defineEmits<{ cancel: [] }>();
</script>

<template>
    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
        <SecondaryButton
            v-if="props.showCancel"
            type="button"
            :disabled="props.busy"
            @click="emit('cancel')"
        >
            {{ props.cancelLabel }}
        </SecondaryButton>

        <AppButton type="submit" :loading="props.busy">
            {{ props.submitLabel }}
        </AppButton>
    </div>
</template>
