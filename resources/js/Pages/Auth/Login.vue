<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { z } from 'zod';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Button from '@/volt/Button.vue';
import Checkbox from '@/volt/Checkbox.vue';
import Inputtext from '@/volt/Inputtext.vue';
import Message from '@/volt/Message.vue';
import Password from '@/volt/Password.vue';

const props = defineProps<{
    canResetPassword: boolean;
    status?: string;
}>();

const loginSchema = z.object({
    email: z.email('Enter a valid email address'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    const result = loginSchema.safeParse({
        email: form.email,
        password: form.password,
    });

    if (!result.success) {
        for (const issue of result.error.issues) {
            const field = issue.path[0];

            if (field === 'email' || field === 'password') {
                form.setError(field, issue.message);
            }
        }

        return;
    }

    form.clearErrors('email', 'password');

    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Sign in" />

    <AuthLayout title="Sign in" subtitle="Access your account">
        <Message v-if="status" severity="info" class="mb-4">{{ status }}</Message>

        <form class="flex flex-col gap-4" novalidate @submit.prevent="submit">
            <div class="flex flex-col gap-1">
                <label for="email" class="text-sm font-medium">Email</label>
                <Inputtext
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    autofocus
                    :invalid="!!form.errors.email"
                    @update:model-value="form.clearErrors('email')"
                />
                <span v-if="form.errors.email" class="text-sm text-aq-danger">{{
                    form.errors.email
                }}</span>
            </div>

            <div class="flex flex-col gap-1">
                <label for="password" class="text-sm font-medium">Password</label>
                <Password
                    input-id="password"
                    v-model="form.password"
                    autocomplete="current-password"
                    toggle-mask
                    :feedback="false"
                    required
                    :invalid="!!form.errors.password"
                    @update:model-value="form.clearErrors('password')"
                />
                <span v-if="form.errors.password" class="text-sm text-aq-danger">{{
                    form.errors.password
                }}</span>
            </div>

            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <Checkbox v-model="form.remember" input-id="remember" binary />
                    <label for="remember" class="text-sm">Remember me</label>
                </div>
                <Link
                    v-if="props.canResetPassword"
                    href="/forgot-password"
                    class="text-sm text-aq-accent transition-colors hover:text-aq-accent-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent"
                >
                    Forgot password?
                </Link>
            </div>

            <Button type="submit" :loading="form.processing" class="aq-btn-shine w-full">
                <span>Sign in</span>
            </Button>
        </form>

        <div class="mt-5">
            <div class="flex items-center gap-3" aria-hidden="true">
                <span class="h-px grow bg-[var(--aq-line)]"></span>
                <span class="text-xs tracking-wide text-aq-ink-muted">or</span>
                <span class="h-px grow bg-[var(--aq-line)]"></span>
            </div>

            <a
                href="/auth/google"
                class="mt-4 flex h-11 w-full items-center justify-center gap-3 rounded-xl border border-[var(--aq-line)] bg-aq-elevated/40 text-sm font-semibold text-aq-ink transition-colors duration-200 hover:border-[var(--aq-line-strong)] hover:bg-aq-overlay/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent"
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
