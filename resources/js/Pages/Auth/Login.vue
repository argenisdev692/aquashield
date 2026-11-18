<script setup lang="ts">
import { computed, nextTick, ref, useTemplateRef } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { z } from 'zod';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import {
    AppButton,
    CheckboxField,
    FormErrorSummary,
    OtpField,
    PasswordField,
    TextField,
} from '@/Components/Forms';
import { useEmailCodeLogin } from '@/Modules/Auth/composables/useEmailCodeLogin';
import { useZodForm } from '@/lib/forms';
import Message from '@/volt/Message.vue';

const props = defineProps<{
    canResetPassword: boolean;
    status?: string;
    demoCredentials?: {
        email: string;
        password: string;
        name: string;
    } | null;
}>();

/*
 * One screen, one task. Password sign-in is the body of the card; the two
 * passwordless transports are progressive-disclosure alternatives opened from
 * text links, not tabs competing with the primary path. The email typed in one
 * transport is carried into the next and back, so nobody retypes their address
 * on the most visited form in the product.
 */
type Method = 'password' | 'email-code' | 'authenticator';

const method = ref<Method>('password');
const panel = useTemplateRef<HTMLDivElement>('panel');

const focusPanel = (selector = 'input'): void => {
    void nextTick(() => {
        panel.value?.querySelector<HTMLInputElement>(selector)?.focus();
    });
};

const linkClass =
    'rounded-lg text-sm font-medium text-aq-accent underline-offset-4 transition-colors duration-150 hover:text-aq-accent-soft hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none';

const backClass =
    'inline-flex items-center gap-1.5 self-center rounded-lg text-sm text-aq-ink-muted transition-colors duration-150 hover:text-aq-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none';

const loginSchema = z.object({
    email: z.email('Enter a valid email address'),
    // The 6–10 policy applies to NEW passwords, never to sign-in. A user whose
    // password predates the policy must still be able to authenticate; the
    // server is the authority on the credential anyway.
    password: z.string().min(1, 'Enter your password'),
    remember: z.boolean(),
});

const loginFormEl = useTemplateRef<HTMLFormElement>('loginForm');

const {
    form,
    formEl: loginFormElement,
    errors: loginErrors,
    submit: submitLogin,
    touch: touchLogin,
    clearError: clearLoginError,
} = useZodForm({
    initial: {
        email: props.demoCredentials?.email ?? '',
        password: props.demoCredentials?.password ?? '',
        remember: false,
    },
    schema: loginSchema,
    formEl: loginFormEl,
});

const emailCodeSchema = z.object({
    email: z.email('Enter a valid email address'),
});

const {
    step: emailCodeStep,
    email: emailCodeEmail,
    error: emailCodeError,
    sending: emailCodeSending,
    verifying: emailCodeVerifying,
    send: sendEmailCode,
    verify: verifyEmailCode,
    reset: resetEmailCode,
} = useEmailCodeLogin();

/*
 * The ref keys carry the `El` suffix on purpose. With inline templates in a
 * production build the compiler resolves `ref="name"` to the setup binding of
 * that name; a key that matched the Inertia form object below would be passed
 * to `setRef` as a plain reactive object, which has no owner instance and
 * throws "Cannot read properties of undefined (reading 'refs')".
 */
const emailCodeFormEl = useTemplateRef<HTMLFormElement>('emailCodeFormEl');
const codeFormEl = useTemplateRef<HTMLFormElement>('codeFormEl');

const {
    form: emailCodeForm,
    formEl: emailCodeFormElement,
    errors: emailCodeErrors,
    submit: submitEmailCode,
    touch: touchEmailCode,
    clearError: clearEmailCodeError,
} = useZodForm({
    initial: { email: '' },
    schema: emailCodeSchema,
    formEl: emailCodeFormEl,
});

const codeSchema = z.object({
    code: z.string().regex(/^\d{6}$/, 'Enter the 6-digit code'),
});

const {
    form: codeForm,
    formEl: codeFormElement,
    errors: codeErrors,
    submit: submitCode,
    touch: touchCode,
    clearError: clearCodeError,
} = useZodForm({
    initial: { code: '' },
    schema: codeSchema,
    formEl: codeFormEl,
});

const authenticatorSchema = z.object({
    email: z.email('Enter a valid email address'),
    code: z.string().regex(/^\d{6}$/, 'Enter the 6-digit code from your authenticator app'),
});

const authenticatorFormEl = useTemplateRef<HTMLFormElement>('authenticatorFormEl');

const {
    form: authenticatorForm,
    formEl: authenticatorFormElement,
    errors: authenticatorErrors,
    submit: submitAuthenticator,
    touch: touchAuthenticator,
    clearError: clearAuthenticatorError,
} = useZodForm({
    initial: { email: '', code: '' },
    schema: authenticatorSchema,
    formEl: authenticatorFormEl,
});

const fillDemo = (): void => {
    if (!props.demoCredentials) {
        return;
    }

    form.email = props.demoCredentials.email;
    form.password = props.demoCredentials.password;
    emailCodeForm.email = props.demoCredentials.email;
    authenticatorForm.email = props.demoCredentials.email;
    form.clearErrors();
    clearLoginError('email');
    clearLoginError('password');
};

const onLogin = (): void => {
    submitLogin(() =>
        form.post('/login', {
            onFinish: () => form.reset('password'),
        }),
    );
};

const onSendEmailCode = (): void => {
    submitEmailCode(() => {
        void sendEmailCode(emailCodeForm.email).then((sent: boolean) => {
            if (sent) {
                focusPanel('[name="code"]');
            }
        });
    });
};

const onVerifyEmailCode = (): void => {
    submitCode(() => {
        void verifyEmailCode(codeForm.code).then((result) => {
            if (result !== null) {
                router.visit(result.redirect);
            }
        });
    });
};

const onAuthenticatorSubmit = (): void => {
    submitAuthenticator(() =>
        authenticatorForm.post('/login/authenticator', {
            onSuccess: () => authenticatorForm.reset('code'),
        }),
    );
};

const openMethod = (next: Exclude<Method, 'password'>): void => {
    clearLoginError('email');
    clearLoginError('password');

    if (next === 'email-code') {
        if (emailCodeStep.value === 'email') {
            emailCodeForm.email = form.email;
            emailCodeForm.clearErrors();
        }
    } else {
        authenticatorForm.email = form.email;
        authenticatorForm.clearErrors();
        authenticatorForm.reset('code');
    }

    method.value = next;

    if (next === 'authenticator') {
        focusPanel(authenticatorForm.email === '' ? 'input' : '[name="code"]');

        return;
    }

    focusPanel(emailCodeStep.value === 'code' ? '[name="code"]' : 'input');
};

const backToPassword = (): void => {
    if (method.value === 'email-code') {
        form.email = emailCodeEmail.value !== '' ? emailCodeEmail.value : emailCodeForm.email;
        emailCodeForm.clearErrors();
        codeForm.clearErrors();
    }

    if (method.value === 'authenticator') {
        form.email = authenticatorForm.email;
        authenticatorForm.clearErrors();
    }

    method.value = 'password';
    focusPanel();
};

const onUseDifferentEmail = (): void => {
    const previous = emailCodeEmail.value;

    resetEmailCode();
    codeForm.reset('code');
    emailCodeForm.clearErrors();
    emailCodeForm.email = previous;
    focusPanel('input');
};

/**
 * The server returns the verified-email refusal on the `email` key. Surfacing
 * it above the card body makes the reason visible on every login transport,
 * not only under the field that happened to receive the 422.
 */
const loginAlert = computed<string | null>(
    () => loginErrors.value.email ?? authenticatorErrors.value.email ?? null,
);
</script>

<template>
    <Head title="Sign in" />

    <AuthLayout title="Sign in" subtitle="Access your account" width="md">
        <Message v-if="status" severity="info" class="mb-4">{{ status }}</Message>
        <Message v-if="loginAlert" severity="error" class="mb-4" role="alert">
            {{ loginAlert }}
        </Message>

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

        <div ref="panel">
            <template v-if="method === 'password'">
                <a
                    href="/auth/google"
                    class="flex h-11 w-full items-center justify-center gap-3 rounded-xl border border-aq-line bg-aq-elevated/40 text-sm font-semibold text-aq-ink transition-colors duration-200 hover:border-aq-line-strong hover:bg-aq-overlay/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
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

                <div class="mt-5 flex items-center gap-3" aria-hidden="true">
                    <span class="h-px grow bg-aq-line"></span>
                    <span class="text-xs tracking-wide text-aq-ink-muted">or</span>
                    <span class="h-px grow bg-aq-line"></span>
                </div>

                <form
                    ref="loginForm"
                    class="mt-5 flex flex-col gap-4"
                    novalidate
                    @submit.prevent="onLogin"
                >
                    <FormErrorSummary :errors="loginErrors" :form-el="loginFormElement" />

                    <TextField
                        v-model="form.email"
                        name="email"
                        label="Email"
                        type="email"
                        autocomplete="username"
                        required
                        autofocus
                        :error="loginErrors.email"
                        @update:model-value="clearLoginError('email')"
                        @blur="touchLogin('email')"
                    />

                    <PasswordField
                        v-model="form.password"
                        name="password"
                        label="Password"
                        autocomplete="current-password"
                        required
                        :error="loginErrors.password"
                        @update:model-value="clearLoginError('password')"
                        @blur="touchLogin('password')"
                    />

                    <div class="flex items-center justify-between gap-4">
                        <CheckboxField
                            v-model="form.remember"
                            name="remember"
                            label="Remember me"
                            :error="loginErrors.remember"
                        />

                        <Link
                            v-if="props.canResetPassword"
                            href="/forgot-password"
                            class="rounded-lg text-sm text-aq-accent transition-colors hover:text-aq-accent-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
                        >
                            Forgot password?
                        </Link>
                    </div>

                    <AppButton type="submit" shine :loading="form.processing" class="w-full">
                        Sign in
                    </AppButton>
                </form>

                <div class="mt-6 border-t border-aq-line pt-5">
                    <p
                        class="text-center text-xs font-semibold tracking-wide text-aq-ink-muted uppercase"
                    >
                        Other ways to sign in
                    </p>

                    <div
                        class="mt-3 flex flex-col items-center gap-2 sm:flex-row sm:justify-center sm:gap-4"
                    >
                        <button type="button" :class="linkClass" @click="openMethod('email-code')">
                            Email me a sign-in code
                        </button>

                        <span aria-hidden="true" class="hidden text-aq-ink-muted sm:inline">·</span>

                        <button
                            type="button"
                            :class="linkClass"
                            @click="openMethod('authenticator')"
                        >
                            Use an authenticator app
                        </button>
                    </div>
                </div>
            </template>

            <template v-else-if="method === 'email-code'">
                <div class="text-center">
                    <h2 class="text-base font-semibold text-aq-ink">Email sign-in code</h2>
                    <p class="mt-1 text-sm text-aq-ink-muted">
                        We will email a six-digit code. It expires in two minutes.
                    </p>
                </div>

                <Message
                    v-if="emailCodeStep === 'email' && emailCodeError"
                    severity="error"
                    class="mt-4"
                    role="alert"
                >
                    {{ emailCodeError }}
                </Message>

                <form
                    v-if="emailCodeStep === 'email'"
                    ref="emailCodeFormEl"
                    class="mt-4 flex flex-col gap-4"
                    novalidate
                    @submit.prevent="onSendEmailCode"
                >
                    <FormErrorSummary :errors="emailCodeErrors" :form-el="emailCodeFormElement" />

                    <TextField
                        v-model="emailCodeForm.email"
                        name="email"
                        label="Email"
                        type="email"
                        autocomplete="username"
                        required
                        autofocus
                        :error="emailCodeErrors.email"
                        @update:model-value="clearEmailCodeError('email')"
                        @blur="touchEmailCode('email')"
                    />

                    <AppButton type="submit" :loading="emailCodeSending" class="w-full">
                        Send sign-in code
                    </AppButton>
                </form>

                <div v-else class="mt-4 flex flex-col gap-4">
                    <p class="text-center text-sm text-aq-ink-muted">
                        Enter the six-digit code sent to
                        <span class="font-semibold text-aq-ink">{{ emailCodeEmail }}</span
                        >.
                    </p>

                    <Message v-if="emailCodeError" severity="error" role="alert">{{
                        emailCodeError
                    }}</Message>

                    <form
                        ref="codeFormEl"
                        class="flex flex-col gap-4"
                        novalidate
                        @submit.prevent="onVerifyEmailCode"
                    >
                        <FormErrorSummary :errors="codeErrors" :form-el="codeFormElement" />

                        <OtpField
                            v-model="codeForm.code"
                            name="code"
                            label="Sign-in code"
                            required
                            autofocus
                            :error="codeErrors.code"
                            @update:model-value="clearCodeError('code')"
                            @blur="touchCode('code')"
                        />

                        <AppButton type="submit" :loading="emailCodeVerifying" class="w-full">
                            Verify and sign in
                        </AppButton>
                    </form>

                    <button type="button" :class="linkClass" @click="onUseDifferentEmail">
                        Use a different email
                    </button>
                </div>

                <button type="button" :class="[backClass, 'mt-5']" @click="backToPassword">
                    <span aria-hidden="true">&larr;</span>
                    Back to password sign-in
                </button>
            </template>

            <template v-else>
                <div class="text-center">
                    <h2 class="text-base font-semibold text-aq-ink">Authenticator app</h2>
                    <p class="mt-1 text-sm text-aq-ink-muted">
                        Use the six-digit code from the authenticator app you enrolled in Profile
                        &amp; security. This signs you in without a password.
                    </p>
                </div>

                <form
                    ref="authenticatorFormEl"
                    class="mt-4 flex flex-col gap-4"
                    novalidate
                    @submit.prevent="onAuthenticatorSubmit"
                >
                    <FormErrorSummary
                        :errors="authenticatorErrors"
                        :form-el="authenticatorFormElement"
                    />

                    <TextField
                        v-model="authenticatorForm.email"
                        name="email"
                        label="Email"
                        type="email"
                        autocomplete="username"
                        required
                        :error="authenticatorErrors.email"
                        @update:model-value="clearAuthenticatorError('email')"
                        @blur="touchAuthenticator('email')"
                    />

                    <OtpField
                        v-model="authenticatorForm.code"
                        name="code"
                        label="Authenticator code"
                        required
                        autofocus
                        :error="authenticatorErrors.code"
                        @update:model-value="clearAuthenticatorError('code')"
                        @blur="touchAuthenticator('code')"
                    />

                    <AppButton type="submit" :loading="authenticatorForm.processing" class="w-full">
                        Sign in with authenticator
                    </AppButton>
                </form>

                <p class="mt-4 text-center text-xs leading-relaxed text-aq-ink-muted">
                    No authenticator yet? Sign in with your password, then scan the QR in Profile
                    &amp; security.
                </p>

                <button type="button" :class="[backClass, 'mt-5']" @click="backToPassword">
                    <span aria-hidden="true">&larr;</span>
                    Back to password sign-in
                </button>
            </template>
        </div>
    </AuthLayout>
</template>
