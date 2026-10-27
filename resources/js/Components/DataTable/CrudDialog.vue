<script setup lang="ts">
/**
 * CrudDialog — the create/edit shell for a list screen.
 *
 * It owns the dialog chrome, the form element and the submit/cancel wiring. It
 * does not own a model, a schema, an endpoint or a query: the page passes the
 * fields and handles `submit`, so the kit stays presentation-only.
 *
 * Validation is never delegated to browser bubbles. The page uses the form kit
 * (`useZodForm` + the field components) and passes the merged error map here for
 * the summary; the native `<form>` exists only for Enter-to-submit and the
 * submit button's `form` attribute.
 *
 * Delete and restore are not form operations. They use `RowAction.confirm` and
 * the global ConfirmDialog instead; this dialog is for create, edit or a
 * destructive action that genuinely needs fields (such as a deletion reason).
 */
import { computed, useId, useTemplateRef } from 'vue';
import { useConfirm } from 'primevue/useconfirm';
import Button from '@/volt/Button.vue';
import Dialog from '@/volt/Dialog.vue';
import SecondaryButton from '@/volt/SecondaryButton.vue';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary.vue';
import type { FormErrorMap } from '@/lib/forms';
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
    /** Enables the discard confirmation when the form has unsaved work. */
    dirty?: boolean;
    /** Client + server errors, rendered in the summary above the fields. */
    errors?: FormErrorMap;
}

const props = withDefaults(defineProps<Props>(), {
    dirty: false,
    errors: () => ({}),
});

const visible = defineModel<boolean>({ required: true });
const emit = defineEmits<{ submit: []; cancel: [] }>();

defineSlots<{
    default(slotProps: { formId: string; busy: boolean }): unknown;
    form(slotProps: { formId: string; busy: boolean }): unknown;
    footer(slotProps: { formId: string; busy: boolean; cancel: () => void }): unknown;
}>();

const confirm = useConfirm();
const formId = useId();
const descriptionId = useId();
const formEl = useTemplateRef<HTMLFormElement>('formEl');

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

const forceClose = (): void => {
    visible.value = false;
    emit('cancel');
};

const requestClose = (): void => {
    if (isBusy.value) {
        return;
    }

    if (!props.dirty) {
        forceClose();
        return;
    }

    confirm.require({
        header: 'Discard changes?',
        message: 'Your edits have not been saved. Close this dialog and lose them?',
        icon: 'pi pi-exclamation-triangle',
        acceptProps: { label: 'Discard' },
        rejectProps: { label: 'Keep editing' },
        accept: forceClose,
    });
};

const onVisibleChange = (value: boolean): void => {
    if (value) {
        visible.value = true;
        return;
    }

    if (!isDismissible.value) {
        return;
    }

    requestClose();
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
        :dismissable-mask="isDismissible"
        :draggable="false"
        :style="dialogStyle"
        :aria-describedby="props.description ? descriptionId : undefined"
        @update:visible="onVisibleChange"
    >
        <p
            v-if="props.description"
            :id="descriptionId"
            class="mb-4 text-sm text-surface-600 dark:text-surface-300"
        >
            {{ props.description }}
        </p>

        <FormErrorSummary :errors="props.errors" :form-el="formEl" />

        <slot name="form" :form-id="formId" :busy="isBusy">
            <form
                ref="formEl"
                :id="formId"
                class="flex flex-col gap-4"
                novalidate
                @submit.prevent="onSubmit"
            >
                <slot :form-id="formId" :busy="isBusy" />
            </form>
        </slot>

        <template #footer>
            <slot name="footer" :form-id="formId" :busy="isBusy" :cancel="requestClose">
                <SecondaryButton class="font-medium" :disabled="isBusy" @click="requestClose">
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
