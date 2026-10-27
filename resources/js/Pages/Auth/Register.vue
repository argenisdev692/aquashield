<script setup lang="ts">
import { useTemplateRef } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { z } from 'zod';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import { AppButton, FormErrorSummary, PasswordField, TextField } from '@/Components/Forms';
import { useZodForm } from '@/lib/forms';

const registerSchema = z
    .object({
        name: z.string().min(2, 'Enter your full name'),
        email: z.email('Enter a valid email address'),
        password: z.string().min(8, 'Use at least 8 characters'),
        password_confirmation: z.string(),
    })
    .refine((values) => values.password === values.password_confirmation, {
        path: ['password_confirmation'],
        message: 'Passwords do not match',
    });

const registerForm = useTemplateRef<HTMLFormElement>('registerForm');

const { form, formEl, errors, submit, touch, clearError } = useZodForm({
    initial: {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    },
    schema: registerSchema,
    formEl: registerForm,
});

const onSubmit = (): void => {
    submit(() =>
        form.post('/register', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        }),
    );
};
</script>

<template>
    <Head title="Create account" />

    <AuthLayout title="Create account" subtitle="Sign up to get started">
        <form ref="registerForm" class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
            <FormErrorSummary :errors="errors" :form-el="formEl" />

            <TextField
                v-model="form.name"
                name="name"
                label="Name"
                autocomplete="name"
                required
                autofocus
                :error="errors.name"
                @update:model-value="clearError('name')"
                @blur="touch('name')"
            />

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
                label="Password"
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
                Create account
            </AppButton>
        </form>

        <template #footer>
            Already have an account?
            <Link href="/login" class="underline">Sign in</Link>
        </template>
    </AuthLayout>
</template>
