<script setup lang="ts">
/**
 * CrudDialog — the create/edit shell for a list screen.
 *
 * It owns the dialog chrome, the form element and the submit/cancel wiring. It
 * does not own a model, a schema, an endpoint or a query: the page passes the
 * fields and handles `submit`, so the kit stays presentation-only.
 *
 * The default slot is wrapped in a native form, which gives Enter-to-submit and
 * browser constraint validation for free. A page that needs its own form
 * component (for example the module's PrimeVue Forms + Zod setup) overrides the
 * `form` slot and keeps the same `formId`, so the footer submit button still
 * targets it.
 *
 * Delete and restore are not form operations. They use `RowAction.confirm` and
 * the global ConfirmDialog instead; this dialog is for create, edit or a
 * destructive action that genuinely needs fields (such as a deletion reason).
 */
import { computed, useId } from 'vue';
import Button from '@/volt/Button.vue';
import Dialog from '@/volt/Dialog.vue';
import SecondaryButton from '@/volt/SecondaryButton.vue';
import type { CrudDialogSize } from './types';

interface Props {
    title: string;
    description?: string;
    submitLabel?: string;
    cancelLabel?: string;
    busy?: boolean;
    size?: CrudDialogSize;
    /**
     * Controls the close icon, Escape and the mask. Cancel remains available;
     * set `busy` to disable every dismissal while a mutation is in flight.
     */
    dismissible?: boolean;
}

const props = defineProps<Props>();
const visible = defineModel<boolean>({ required: true });
const emit = defineEmits<{ submit: []; cancel: [] }>();

defineSlots<{
    default(slotProps: { formId: string; busy: boolean }): unknown;
    form(slotProps: { formId: string; busy: boolean }): unknown;
    footer(slotProps: { formId: string; busy: boolean; cancel: () => void }): unknown;
}>();

const formId = useId();
const descriptionId = useId();

const SIZE_WIDTH: Record<CrudDialogSize, string> = {
    sm: '24rem',
    md: '32rem',
    lg: '42rem',
};

const dialogStyle = computed(() => ({
    width: SIZE_WIDTH[props.size ?? 'md'],
    maxWidth: 'calc(100vw - 2rem)',
}));

const isBusy = computed(() => props.busy === true);
const isDismissible = computed(() => props.dismissible !== false && !isBusy.value);

const onVisibleChange = (value: boolean): void => {
    if (!value && !isDismissible.value) {
        return;
    }

    visible.value = value;

    if (!value) {
        emit('cancel');
    }
};

const close = (): void => {
    if (isBusy.value) {
        return;
    }

    visible.value = false;
    emit('cancel');
};

const onSubmit = (): void => {
    if (isBusy.value) {
        return;
    }

    emit('submit');
};
</script>

<template>
    <Dialog
        :visible="visible"
        modal
        block-scroll
        :header="props.title"
        :closable="isDismissible"
        :close-on-escape="isDismissible"
        :dismissable-mask="false"
        :draggable="false"
        :style="dialogStyle"
        :aria-describedby="props.description ? descriptionId : undefined"
        @update:visible="onVisibleChange"
    >
        <p
            v-if="props.description"
            :id="descriptionId"
            class="mb-4 text-sm text-surface-500 dark:text-surface-300"
        >
            {{ props.description }}
        </p>

        <slot name="form" :form-id="formId" :busy="isBusy">
            <form :id="formId" class="flex flex-col gap-4" @submit.prevent="onSubmit">
                <slot :form-id="formId" :busy="isBusy" />
            </form>
        </slot>

        <template #footer>
            <slot name="footer" :form-id="formId" :busy="isBusy" :cancel="close">
                <SecondaryButton class="font-medium" :disabled="isBusy" @click="close">
                    {{ props.cancelLabel ?? 'Cancel' }}
                </SecondaryButton>

                <Button
                    class="font-medium"
                    type="submit"
                    :form="formId"
                    :disabled="isBusy"
                    :aria-busy="isBusy"
                >
                    <i
                        v-if="isBusy"
                        class="pi pi-spinner animate-spin text-sm motion-reduce:animate-none"
                        aria-hidden="true"
                    />
                    {{ props.submitLabel ?? 'Save' }}
                </Button>
            </slot>
        </template>
    </Dialog>
</template>
