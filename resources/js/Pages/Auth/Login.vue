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
import Message from '@/volt/Message.vue';
import { useZodForm } from '@/lib/forms';

const props = defineProps<{
    canResetPassword: boolean;
    status?: string;
    demoCredentials?: {
        email: string;
        password: string;
        name: string;
    } | null;
}>();

const loginSchema = z.object({
    email: z.email('Enter a valid email address'),
    password: z.string().min(6, 'Password must be at least 6 characters'),
    remember: z.boolean(),
});

const loginForm = useTemplateRef<HTMLFormElement>('loginForm');

const { form, formEl, errors, submit, touch, clearError } = useZodForm({
    initial: {
        email: props.demoCredentials?.email ?? '',
        password: props.demoCredentials?.password ?? '',
        remember: false,
    },
    schema: loginSchema,
    formEl: loginForm,
});

const fillDemo = (): void => {
    if (!props.demoCredentials) {
        return;
    }

    form.email = props.demoCredentials.email;
    form.password = props.demoCredentials.password;
    form.clearErrors();
    clearError('email');
    clearError('password');
};

const onSubmit = (): void => {
    submit(() =>
        form.post('/login', {
            onFinish: () => form.reset('password'),
        }),
    );
};
</script>

<template>
    <Head title="Sign in" />

    <AuthLayout title="Sign in" subtitle="Access your account">
        <Message v-if="status" severity="info" class="mb-4">{{ status }}</Message>

        <div
            v-if="demoCredentials"
            class="mb-4 rounded-xl border border-aq-line bg-aq-elevated/60 p-4"
        >
            <div class="mb-2 flex items-center justify-between">
                <span class="text-xs font-semibold tracking-wide text-aq-ink-muted uppercase"
                    >Demo credentials</span
                >
                <button
                    type="button"
                    class="rounded-lg border border-aq-line px-3 py-1 text-xs font-medium text-aq-accent transition-colors hover:bg-aq-overlay/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
                    @click="fillDemo"
                >
                    Fill demo credentials
                </button>
            </div>
            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                <dt class="text-aq-ink-muted">Email</dt>
                <dd class="font-mono text-aq-ink">{{ demoCredentials.email }}</dd>
                <dt class="text-aq-ink-muted">Password</dt>
                <dd class="font-mono text-aq-ink">{{ demoCredentials.password }}</dd>
                <dt class="text-aq-ink-muted">Name</dt>
                <dd class="font-mono text-aq-ink">{{ demoCredentials.name }}</dd>
            </dl>
        </div>

        <form ref="loginForm" class="flex flex-col gap-4" novalidate @submit.prevent="onSubmit">
            <FormErrorSummary :errors="errors" :form-el="formEl" />

            <TextField
                v-model="form.email"
                name="email"
                label="Email"
                type="email"
                autocomplete="username"
                required
                autofocus
                :error="errors.email"
                @update:model-value="clearError('email')"
                @blur="touch('email')"
            />

            <PasswordField
                v-model="form.password"
                name="password"
                label="Password"
                autocomplete="current-password"
                required
                :error="errors.password"
                @update:model-value="clearError('password')"
                @blur="touch('password')"
            />

            <div class="flex items-center justify-between gap-4">
                <CheckboxField
                    v-model="form.remember"
                    name="remember"
                    label="Remember me"
                    :error="errors.remember"
                />

                <Link
                    v-if="props.canResetPassword"
                    href="/forgot-password"
                    class="text-sm text-aq-accent transition-colors hover:text-aq-accent-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
                >
                    Forgot password?
                </Link>
            </div>

            <AppButton type="submit" :loading="form.processing" class="w-full"> Sign in </AppButton>
        </form>

        <div class="mt-5">
            <div class="flex items-center gap-3" aria-hidden="true">
                <span class="h-px grow bg-aq-line"></span>
                <span class="text-xs tracking-wide text-aq-ink-muted">or</span>
                <span class="h-px grow bg-aq-line"></span>
            </div>

            <a
                href="/auth/google"
                class="mt-4 flex h-11 w-full items-center justify-center gap-3 rounded-xl border border-aq-line bg-aq-elevated/40 text-sm font-semibold text-aq-ink transition-colors duration-200 hover:border-aq-line-strong hover:bg-aq-overlay/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
            >
                <svg aria-hidden="true" viewBox="0 0 48 48" class="size-4.5 shrink-0">
                    <path
                        class="fill-google-red"
                        d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"
                    />
                    <path
                        class="fill-google-blue"
                        d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"
                    />
                    <path
                        class="fill-google-yellow"
                        d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"
                    />
                    <path
                        class="fill-google-green"
                        d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"
                    />
                </svg>
                Continue with Google
            </a>
        </div>
    </AuthLayout>
</template>
