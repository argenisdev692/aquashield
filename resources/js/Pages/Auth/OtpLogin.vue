<script setup lang="ts">
import { useTemplateRef } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { z } from 'zod';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { AppButton, FormErrorSummary, TextField } from '@/Components/Forms';
import Message from '@/volt/Message.vue';
import { useZodForm } from '@/lib/forms';

/**
 * Two-step email login. The server holds the address in the session between
 * the steps, so the verify form intentionally has no email field: a visitor
 * cannot request a code for one account and try it against another.
 */
const props = defineProps<{
    email: string | null;
    status?: string | null;
}>();

const sendSchema = z.object({
    email: z.email('Enter a valid email address'),
});

const verifySchema = z.object({
    code: z.string().regex(/^\d{6}$/, 'Enter the 6-digit code'),
});

const sendFormEl = useTemplateRef<HTMLFormElement>('sendFormEl');
const verifyFormEl = useTemplateRef<HTMLFormElement>('verifyFormEl');

const {
    form: sendForm,
    formEl: sendFormElement,
    errors: sendErrors,
    submit: submitSend,
    touch: touchSend,
    clearError: clearSendError,
} = useZodForm({
    initial: { email: props.email ?? '' },
    schema: sendSchema,
    formEl: sendFormEl,
});

const {
    form: verifyForm,
    formEl: verifyFormElement,
    errors: verifyErrors,
    submit: submitVerify,
    touch: touchVerify,
    clearError: clearVerifyError,
} = useZodForm({
    initial: { code: '' },
    schema: verifySchema,
    formEl: verifyFormEl,
});

const onSend = (): void => {
    submitSend(() => sendForm.post('/login/otp', { preserveScroll: true }));
};

const onVerify = (): void => {
    submitVerify(() =>
        verifyForm.post('/login/otp/verify', {
            preserveScroll: true,
            onSuccess: () => verifyForm.reset('code'),
        }),
    );
};
</script>

<template>
    <Head title="Email sign-in code" />

    <AuthLayout title="Email sign-in code" subtitle="Sign in without a password">
        <Message v-if="props.status" severity="info" class="mb-4">{{ props.status }}</Message>

        <form ref="sendFormEl" class="flex flex-col gap-4" novalidate @submit.prevent="onSend">
            <FormErrorSummary :errors="sendErrors" :form-el="sendFormElement" />

            <TextField
                v-model="sendForm.email"
                name="email"
                label="Email"
                type="email"
                autocomplete="username"
                required
                autofocus
                :error="sendErrors.email"
                @update:model-value="clearSendError('email')"
                @blur="touchSend('email')"
            />

            <AppButton type="submit" :loading="sendForm.processing" class="w-full">
                Send sign-in code
            </AppButton>
        </form>

        <form
            v-if="props.email"
            ref="verifyFormEl"
            class="mt-6 flex flex-col gap-4 border-t border-aq-line pt-6"
            novalidate
            @submit.prevent="onVerify"
        >
            <p class="text-sm text-aq-ink-muted">
                Enter the six-digit code sent to
                <span class="font-semibold text-aq-ink">{{ props.email }}</span
                >. It expires in two minutes.
            </p>

            <FormErrorSummary :errors="verifyErrors" :form-el="verifyFormElement" />

            <TextField
                v-model="verifyForm.code"
                name="code"
                label="Sign-in code"
                inputmode="numeric"
                autocomplete="one-time-code"
                :maxlength="6"
                required
                :error="verifyErrors.code"
                @update:model-value="clearVerifyError('code')"
                @blur="touchVerify('code')"
            />

            <AppButton type="submit" :loading="verifyForm.processing" class="w-full">
                Verify and sign in
            </AppButton>
        </form>

        <template #footer>
            <Link href="/login" class="underline">Back to password sign in</Link>
        </template>
    </AuthLayout>
</template>
