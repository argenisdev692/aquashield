<script setup lang="ts">
import { nextTick, ref, useTemplateRef } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { z } from 'zod';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { AppButton, FormErrorSummary, OtpField, TextField } from '@/Components/Forms';
import Message from '@/volt/Message.vue';
import { useZodForm } from '@/lib/forms';

/**
 * Fortify's second factor, in one card with no tab strip.
 *
 * The authenticator code is the default field; a recovery code is a distinct
 * fallback that replaces it. Only the active value is submitted, so a stale
 * recovery code can never win over the code the user just typed (Fortify
 * checks `filled('recovery_code')` first).
 */
const props = defineProps<{ status?: string }>();

type Mode = 'code' | 'recovery';

const mode = ref<Mode>('code');

const schema = z
    .object({
        code: z.string(),
        recovery_code: z.string(),
    })
    .superRefine((values, ctx) => {
        if (mode.value === 'code' && !/^\d{6}$/.test(values.code)) {
            ctx.addIssue({
                code: 'custom',
                path: ['code'],
                message: 'Enter the 6-digit code from your authenticator app.',
            });
        }

        if (mode.value === 'recovery' && values.recovery_code.trim() === '') {
            ctx.addIssue({
                code: 'custom',
                path: ['recovery_code'],
                message: 'Enter a recovery code.',
            });
        }
    });

const twoFactorForm = useTemplateRef<HTMLFormElement>('twoFactorForm');

const { form, formEl, errors, submit, touch, clearError } = useZodForm({
    initial: { code: '', recovery_code: '' },
    schema,
    formEl: twoFactorForm,
});

const focusField = (name: string): void => {
    void nextTick(() => {
        formEl.value?.querySelector<HTMLInputElement>(`[name="${name}"]`)?.focus();
    });
};

const onSubmit = (): void => {
    if (mode.value === 'code') {
        form.recovery_code = '';
    } else {
        form.code = '';
    }

    submit(() => form.post('/two-factor-challenge'));
};

const switchMode = (): void => {
    if (mode.value === 'code') {
        mode.value = 'recovery';
        form.code = '';
        clearError('code');
        focusField('recovery_code');

        return;
    }

    mode.value = 'code';
    form.recovery_code = '';
    clearError('recovery_code');
    focusField('code');
};

const linkClass =
    'self-center rounded-lg text-sm font-medium text-aq-accent underline-offset-4 transition-colors duration-150 hover:text-aq-accent-soft hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none';
</script>

<template>
    <Head title="Two-factor authentication" />

    <AuthLayout title="Two-factor authentication" subtitle="Confirm your identity">
        <Message v-if="props.status" severity="info" class="mb-4">{{ props.status }}</Message>

        <form ref="twoFactorForm" class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
            <Message severity="warn">
                After several failed attempts your account will be temporarily locked.
            </Message>

            <p class="text-center text-sm text-aq-ink-muted">
                <template v-if="mode === 'code'">
                    Enter the six-digit code from your authenticator app.
                </template>
                <template v-else>
                    Enter one of the recovery codes you saved when you enabled two-factor
                    authentication.
                </template>
            </p>

            <FormErrorSummary :errors="errors" :form-el="formEl" />

            <OtpField
                v-if="mode === 'code'"
                v-model="form.code"
                name="code"
                label="Authenticator code"
                required
                autofocus
                :error="errors.code"
                @update:model-value="clearError('code')"
                @blur="touch('code')"
            />

            <TextField
                v-else
                v-model="form.recovery_code"
                name="recovery_code"
                label="Recovery code"
                autocomplete="off"
                required
                autofocus
                :error="errors.recovery_code"
                @update:model-value="clearError('recovery_code')"
                @blur="touch('recovery_code')"
            />

            <AppButton type="submit" :loading="form.processing" class="w-full"> Verify </AppButton>

            <button type="button" :class="linkClass" @click="switchMode">
                {{ mode === 'code' ? 'Use a recovery code' : 'Use your authenticator app' }}
            </button>
        </form>

        <template #footer>
            <Link href="/login" class="underline">Back to sign in</Link>
        </template>
    </AuthLayout>
</template>
