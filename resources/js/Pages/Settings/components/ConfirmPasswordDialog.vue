<script setup lang="ts">
/**
 * ConfirmPasswordDialog — the short password gate Fortify requires before
 * two-factor changes.
 *
 * It posts through the security composable (JSON + XSRF), not through Inertia,
 * so the surrounding tab stays mounted and the pending action can resume as
 * soon as the session records `auth.password_confirmed_at`.
 */
import { ref, watch } from 'vue';
import { AppButton, PasswordField } from '@/Components/Forms';
import { useZodForm } from '@/lib/forms';
import { passwordGateSchema } from '@/Modules/Users/schemas/profileSettings';
import Button from '@/volt/Button.vue';
import Dialog from '@/volt/Dialog.vue';

const props = defineProps<{
    open: boolean;
    /** Resolves when Fortify accepted the password; rejects with a message. */
    confirm: (password: string) => Promise<void>;
}>();

const emit = defineEmits<{
    'update:open': [open: boolean];
    confirmed: [];
}>();

const busy = ref<boolean>(false);
const formError = ref<string | null>(null);

const { form, errors, submit, touch, clearError } = useZodForm({
    initial: { password: '' },
    schema: passwordGateSchema,
});

watch(
    () => props.open,
    (open) => {
        if (!open) {
            form.reset('password');
            formError.value = null;
        }
    },
);

const close = (next: boolean): void => {
    if (!next && busy.value) {
        return;
    }

    emit('update:open', next);
};

const run = async (): Promise<void> => {
    busy.value = true;
    formError.value = null;

    try {
        await props.confirm(form.password);
        form.reset('password');
        /*
         * Resume before closing: the parent's close handler clears the parked
         * action, so closing first would drop the action this gate unlocks.
         */
        emit('confirmed');
        emit('update:open', false);
    } catch (caught) {
        formError.value =
            caught instanceof Error && caught.message !== ''
                ? caught.message
                : 'The password could not be confirmed. Try again.';
    } finally {
        busy.value = false;
    }
};

const onSubmit = (): void => {
    submit(() => {
        void run();
    });
};
</script>

<template>
    <Dialog
        :visible="props.open"
        modal
        header="Confirm your password"
        class="w-full max-w-md"
        :draggable="false"
        @update:visible="close"
    >
        <p class="text-sm text-surface-600 dark:text-surface-300">
            Two-factor changes need a fresh password confirmation. This stays valid for a short
            time, then you will be asked again.
        </p>

        <form class="mt-4 flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
            <PasswordField
                v-model="form.password"
                name="password"
                label="Current password"
                autocomplete="current-password"
                required
                autofocus
                :disabled="busy"
                :error="errors.password ?? formError ?? undefined"
                @update:model-value="clearError('password')"
                @blur="touch('password')"
            />

            <div class="flex flex-wrap justify-end gap-2">
                <Button
                    type="button"
                    text
                    severity="secondary"
                    :disabled="busy"
                    @click="close(false)"
                >
                    Cancel
                </Button>

                <AppButton type="submit" :loading="busy" shine>Confirm password</AppButton>
            </div>
        </form>
    </Dialog>
</template>
