<script setup lang="ts">
import { useTemplateRef } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { z } from 'zod';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import {
    AppButton,
    CheckboxField,
    FormErrorSummary,
    PasswordField,
    TextField,
} from '@/Components/Forms';
import { PASSWORD_HINT, PASSWORD_MAX_LENGTH, passwordSchema } from '@/lib/auth';
import { useZodForm } from '@/lib/forms';
import { USERNAME_HINT, USERNAME_MAX, usernameSchema } from '@/lib/username';

const registerSchema = z
    .object({
        first_name: z.string().min(1, 'Enter your first name').max(255),
        last_name: z.string().max(255),
        username: usernameSchema,
        email: z.email('Enter a valid email address'),
        password: passwordSchema,
        password_confirmation: z.string(),
        terms_and_conditions: z.boolean(),
    })
    .refine((values) => values.password === values.password_confirmation, {
        path: ['password_confirmation'],
        message: 'Passwords do not match',
    });

const registerForm = useTemplateRef<HTMLFormElement>('registerForm');

const { form, formEl, errors, submit, touch, clearError } = useZodForm({
    initial: {
        first_name: '',
        last_name: '',
        username: '',
        email: '',
        password: '',
        password_confirmation: '',
        terms_and_conditions: false,
    },
    schema: registerSchema,
    formEl: registerForm,
});

const onUsernameInput = (): void => {
    form.username = form.username.toLowerCase();
    clearError('username');
};

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

            <div class="grid gap-4 sm:grid-cols-2">
                <TextField
                    v-model="form.first_name"
                    name="first_name"
                    label="First name"
                    autocomplete="given-name"
                    required
                    autofocus
                    :error="errors.first_name"
                    @update:model-value="clearError('first_name')"
                    @blur="touch('first_name')"
                />

                <TextField
                    v-model="form.last_name"
                    name="last_name"
                    label="Last name"
                    autocomplete="family-name"
                    :error="errors.last_name"
                    @update:model-value="clearError('last_name')"
                    @blur="touch('last_name')"
                />
            </div>

            <TextField
                v-model="form.username"
                name="username"
                label="Username"
                autocomplete="username"
                :hint="USERNAME_HINT"
                :maxlength="USERNAME_MAX"
                :error="errors.username"
                @update:model-value="onUsernameInput"
                @blur="touch('username')"
            />

            <TextField
                v-model="form.email"
                name="email"
                label="Email"
                type="email"
                autocomplete="email"
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
                :hint="PASSWORD_HINT"
                :maxlength="PASSWORD_MAX_LENGTH"
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
                :maxlength="PASSWORD_MAX_LENGTH"
                :error="errors.password_confirmation"
                @update:model-value="clearError('password_confirmation')"
                @blur="touch('password_confirmation')"
            />

            <CheckboxField
                v-model="form.terms_and_conditions"
                name="terms_and_conditions"
                label="I accept the terms and conditions"
                :error="errors.terms_and_conditions"
                @update:model-value="clearError('terms_and_conditions')"
                @blur="touch('terms_and_conditions')"
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
