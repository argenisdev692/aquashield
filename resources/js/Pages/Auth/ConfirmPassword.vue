<script setup lang="ts">
import { useTemplateRef } from 'vue';
import { Head } from '@inertiajs/vue3';
import { z } from 'zod';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { AppButton, FormErrorSummary, PasswordField } from '@/Components/Forms';
import { useZodForm } from '@/lib/forms';

const schema = z.object({
    password: z.string().min(1, 'Enter your password'),
});

const confirmForm = useTemplateRef<HTMLFormElement>('confirmForm');

const { form, formEl, errors, submit, touch, clearError } = useZodForm({
    initial: { password: '' },
    schema,
    formEl: confirmForm,
});

const onSubmit = (): void => {
    submit(() =>
        form.post('/user/confirm-password', {
            onFinish: () => form.reset(),
        }),
    );
};
</script>

<template>
    <Head title="Confirm password" />

    <AuthLayout title="Confirm password" subtitle="This is a secure area">
        <form ref="confirmForm" class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
            <p class="text-sm text-surface-600 dark:text-surface-300">
                Re-enter your password to confirm your identity before continuing.
            </p>

            <FormErrorSummary :errors="errors" :form-el="formEl" />

            <PasswordField
                v-model="form.password"
                name="password"
                label="Password"
                autocomplete="current-password"
                required
                autofocus
                :error="errors.password"
                @update:model-value="clearError('password')"
                @blur="touch('password')"
            />

            <AppButton type="submit" :loading="form.processing" class="w-full"> Confirm </AppButton>
        </form>
    </AuthLayout>
</template>
