<script setup lang="ts">
import { ref, useTemplateRef } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { z } from 'zod';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import {
    AppButton,
    CheckboxField,
    FormErrorSummary,
    PasswordField,
    TextField,
} from '@/Components/Forms';
import { useEmailCodeLogin } from '@/Modules/Auth/composables/useEmailCodeLogin';
import { useZodForm } from '@/lib/forms';
import Message from '@/volt/Message.vue';
import Tab from '@/volt/Tab.vue';
import TabList from '@/volt/TabList.vue';
import TabPanel from '@/volt/TabPanel.vue';
import TabPanels from '@/volt/TabPanels.vue';
import Tabs from '@/volt/Tabs.vue';

const props = defineProps<{
    canResetPassword: boolean;
    status?: string;
    demoCredentials?: {
        email: string;
        password: string;
        name: string;
    } | null;
}>();

const activeTab = ref<string>('password');

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
    form: form,
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

const fillDemo = (): void => {
    if (!props.demoCredentials) {
        return;
    }

    form.email = props.demoCredentials.email;
    form.password = props.demoCredentials.password;
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

const emailCodeFormEl = useTemplateRef<HTMLFormElement>('emailCodeForm');
const codeFormEl = useTemplateRef<HTMLFormElement>('codeForm');

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

const onSendEmailCode = (): void => {
    submitEmailCode(() => {
        void sendEmailCode(emailCodeForm.email).then((sent: boolean) => {
            if (sent) {
                emailCodeForm.reset('email');
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

const onUseDifferentEmail = (): void => {
    resetEmailCode();
    emailCodeForm.reset('email');
    codeForm.reset('code');
};

const authenticatorSchema = z.object({
    email: z.email('Enter a valid email address'),
    code: z.string().regex(/^\d{6}$/, 'Enter the 6-digit code from your authenticator app'),
});

const authenticatorFormEl = useTemplateRef<HTMLFormElement>('authenticatorForm');

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

const onAuthenticatorSubmit = (): void => {
    submitAuthenticator(() =>
        authenticatorForm.post('/login/authenticator', {
            onSuccess: () => authenticatorForm.reset('code'),
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

        <Tabs v-model:value="activeTab">
            <TabList>
                <Tab value="password">Password</Tab>
                <Tab value="email-code">Email code</Tab>
                <Tab value="authenticator">Authenticator</Tab>
            </TabList>

            <TabPanels>
                <TabPanel value="password">
                    <form
                        ref="loginForm"
                        class="flex flex-col gap-4 pt-4"
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
                                class="text-sm text-aq-accent transition-colors hover:text-aq-accent-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
                            >
                                Forgot password?
                            </Link>
                        </div>

                        <AppButton type="submit" shine :loading="form.processing" class="w-full">
                            Sign in
                        </AppButton>
                    </form>
                </TabPanel>

                <TabPanel value="email-code">
                    <div class="pt-4">
                        <Message
                            v-if="emailCodeStep === 'email' && emailCodeError"
                            severity="error"
                            class="mb-4"
                        >
                            {{ emailCodeError }}
                        </Message>

                        <form
                            v-if="emailCodeStep === 'email'"
                            ref="emailCodeForm"
                            class="flex flex-col gap-4"
                            novalidate
                            @submit.prevent="onSendEmailCode"
                        >
                            <FormErrorSummary
                                :errors="emailCodeErrors"
                                :form-el="emailCodeFormElement"
                            />

                            <TextField
                                v-model="emailCodeForm.email"
                                name="email"
                                label="Email"
                                type="email"
                                autocomplete="username"
                                required\n
                                :error="emailCodeErrors.email"
                                @update:model-value="clearEmailCodeError('email')"
                                @blur="touchEmailCode('email')"
                            />

                            <p class="text-sm text-aq-ink-muted">
                                We will email a six-digit code. It expires in two minutes.
                            </p>

                            <AppButton type="submit" :loading="emailCodeSending" class="w-full">
                                Send sign-in code
                            </AppButton>
                        </form>

                        <div v-else class="flex flex-col gap-4">
                            <p class="text-sm text-aq-ink-muted">
                                Enter the six-digit code sent to
                                <span class="font-semibold text-aq-ink">{{ emailCodeEmail }}</span
                                >.
                            </p>

                            <Message v-if="emailCodeError" severity="error">{{
                                emailCodeError
                            }}</Message>

                            <form
                                ref="codeForm"
                                class="flex flex-col gap-4"
                                novalidate
                                @submit.prevent="onVerifyEmailCode"
                            >
                                <FormErrorSummary :errors="codeErrors" :form-el="codeFormElement" />

                                <TextField
                                    v-model="codeForm.code"
                                    name="code"
                                    label="Sign-in code"
                                    inputmode="numeric"
                                    autocomplete="one-time-code"
                                    :maxlength="6"
                                    required
                                    autofocus
                                    :error="codeErrors.code"
                                    @update:model-value="clearCodeError('code')"
                                    @blur="touchCode('code')"
                                />

                                <AppButton
                                    type="submit"
                                    :loading="emailCodeVerifying"
                                    class="w-full"
                                >
                                    Verify and sign in
                                </AppButton>
                            </form>

                            <button
                                type="button"
                                class="self-center text-sm text-aq-accent underline-offset-4 transition-colors duration-150 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
                                @click="onUseDifferentEmail"
                            >
                                Use a different email
                            </button>
                        </div>
                    </div>
                </TabPanel>

                <TabPanel value="authenticator">
                    <form
                        ref="authenticatorForm"
                        class="flex flex-col gap-4 pt-4"
                        novalidate
                        @submit.prevent="onAuthenticatorSubmit"
                    >
                        <p class="text-sm text-aq-ink-muted">
                            Use the six-digit code from the authenticator app you enrolled in
                            Profile &amp; security. This signs you in without a password.
                        </p>

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

                        <TextField
                            v-model="authenticatorForm.code"
                            name="code"
                            label="Authenticator code"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            :maxlength="6"
                            required
                            :error="authenticatorErrors.code"
                            @update:model-value="clearAuthenticatorError('code')"
                            @blur="touchAuthenticator('code')"
                        />

                        <AppButton
                            type="submit"
                            :loading="authenticatorForm.processing"
                            class="w-full"
                        >
                            Sign in with authenticator
                        </AppButton>

                        <p class="text-xs leading-relaxed text-aq-ink-muted">
                            No authenticator yet? Sign in with your password, then scan the QR in
                            Profile &amp; security.
                        </p>
                    </form>
                </TabPanel>
            </TabPanels>
        </Tabs>

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
