<script setup lang="ts">
import { useTemplateRef } from 'vue';
import { Head } from '@inertiajs/vue3';
import { z } from 'zod';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { AppButton, FormErrorSummary, PasswordField, TextField } from '@/Components/Forms';
import { useZodForm } from '@/lib/forms';

const props = defineProps<{
    email: string;
    token: string;
}>();

const schema = z
    .object({
        token: z.string().min(1),
        email: z.email('Enter a valid email address'),
        password: z.string().min(8, 'Use at least 8 characters'),
        password_confirmation: z.string(),
    })
    .refine((values) => values.password === values.password_confirmation, {
        path: ['password_confirmation'],
        message: 'Passwords do not match',
    });

const resetForm = useTemplateRef<HTMLFormElement>('resetForm');

const { form, formEl, errors, submit, touch, clearError } = useZodForm({
    initial: {
        token: props.token,
        email: props.email,
        password: '',
        password_confirmation: '',
    },
    schema,
    formEl: resetForm,
});

const onSubmit = (): void => {
    submit(() =>
        form.post('/reset-password', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        }),
    );
};
</script>

<template>
    <Head title="Reset password" />

    <AuthLayout title="Reset password" subtitle="Choose a new password">
        <form ref="resetForm" class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
            <FormErrorSummary :errors="errors" :form-el="formEl" />

            <TextField
                v-model="form.email"
                name="email"
                label="Email"
                type="email"
                autocomplete="username"
                required
                :error="errors.email"
                @update:model-value="clearError('email')"
                @blur="touch('email')"
            />

            <PasswordField
                v-model="form.password"
                name="password"
                label="New password"
                autocomplete="new-password"
                required
                hint="At least 8 characters."
                :error="errors.password"
                @update:model-value="clearError('password')"
                @blur="touch('password')"
            />

            <PasswordField
                v-model="form.password_confirmation"
                name="password_confirmation"
                label="Confirm password"
                autocomplete="new-password"
                required
                :error="errors.password_confirmation"
                @update:model-value="clearError('password_confirmation')"
                @blur="touch('password_confirmation')"
            />

            <AppButton type="submit" :loading="form.processing" class="w-full">
                Reset password
            </AppButton>
        </form>
    </AuthLayout>
</template>
