/**
 * Auth/Login — sign in page.
 *
 * One primary credential, one disclosure. The password form is the screen;
 * the passkey sits directly under the primary action as a full-width
 * secondary button — same geometry as the Google CTA — because it is the
 * strongest and fastest way in, and it also reaches the visitor through the
 * browser's autofill (WebAuthn conditional UI on the email field). The two
 * one-time-code transports — emailed code and authenticator code — are
 * alternatives to the password, not peers of it, so they live behind a single
 * "Sign-in options" disclosure instead of three permanently visible rows.
 *
 * The label "Other ways to sign in" is deliberately absent here: it is
 * Microsoft's escape hatch inside a second-factor challenge, and using it on
 * the first screen reads as "something already went wrong".
 *
 * The code transports open as steps of the same card — their own heading, the
 * email carried across, and a back affordance — while the passkey runs in
 * place and follows the redirect the server returns. Google sits above the
 * divider because it is a different account space, not another way to use
 * this one.
 */
import { useEffect, useId, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useZodForm } from '@/lib/forms';
import * as z from 'zod';
import { ArrowLeft, ChevronDown, ChevronRight, KeyRound, Mail, ShieldCheck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import TextField from '@/Components/Forms/TextField';
import OtpField from '@/Components/Forms/OtpField';
import PasswordField from '@/Components/Forms/PasswordField';
import CheckboxField from '@/Components/Forms/CheckboxField';
import AppButton from '@/Components/Forms/AppButton';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import AuthLayout from '@/Layouts/AuthLayout';
import { useEmailCodeLogin } from '@/Modules/Auth/hooks/useEmailCodeLogin';
import { usePasskeyLogin } from '@/Modules/Auth/hooks/usePasskeyLogin';

type AuthStep = 'password' | 'email-code' | 'authenticator';

interface DemoCredentials {
    email: string;
    password: string;
    name: string;
}

interface LoginPageProps {
    /** Inertia's PageProps requires an index signature. */
    [key: string]: unknown;
    canResetPassword: boolean;
    status?: string | null;
    demoCredentials?: DemoCredentials | null;
}

const loginSchema = z.object({
    email: z.email('Enter a valid email address'),
    // The 6–10 policy applies to NEW passwords, never to sign-in. A user whose
    // password predates the policy must still be able to authenticate; the
    // server is the authority on the credential anyway.
    password: z.string().min(1, 'Enter your password'),
    remember: z.boolean(),
});

const emailCodeSchema = z.object({
    email: z.email('Enter a valid email address'),
});

const codeSchema = z.object({
    code: z.string().regex(/^\d{6}$/, 'Enter the 6-digit code'),
});

const authenticatorSchema = z.object({
    email: z.email('Enter a valid email address'),
    code: z.string().regex(/^\d{6}$/, 'Enter the 6-digit code from your authenticator app'),
});

const fieldLabels = {
    email: 'Email',
    password: 'Password',
    remember: 'Remember me',
    code: 'Sign-in code',
};

const linkClass =
    'rounded-sm text-sm font-medium text-aq-accent transition-colors hover:text-aq-accent-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none';

function GoogleButton() {
    return (
        <a
            href="/auth/google"
            className="flex h-11 w-full items-center justify-center gap-3 rounded-xl border border-aq-line bg-aq-elevated/40 text-sm font-semibold text-aq-ink transition-colors duration-200 hover:border-aq-line-strong hover:bg-aq-overlay/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
        >
            <svg aria-hidden="true" viewBox="0 0 48 48" className="size-4.5 shrink-0">
                <path
                    className="fill-google-red"
                    d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"
                />
                <path
                    className="fill-google-blue"
                    d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"
                />
                <path
                    className="fill-google-yellow"
                    d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"
                />
                <path
                    className="fill-google-green"
                    d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"
                />
            </svg>
            Continue with Google
        </a>
    );
}

function BackToSignIn({ onClick }: { onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="-ml-1 mb-4 inline-flex items-center gap-1.5 self-start rounded-md px-1 py-0.5 text-sm font-medium text-aq-ink-muted transition-colors hover:text-aq-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
        >
            <ArrowLeft aria-hidden="true" className="size-4" />
            Back to sign in
        </button>
    );
}

interface MethodOptionProps {
    icon: LucideIcon;
    label: string;
    onClick: () => void;
}

/**
 * A row inside "Sign-in options". Rows rather than a tab strip: each one names
 * the task and keeps the whole list on one axis under the disclosure trigger.
 * The icon sits in a tinted tile so the row keeps a stable leading edge and the
 * transport is recognisable before the label is read.
 */
function MethodOption({ icon: Icon, label, onClick }: MethodOptionProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="group flex min-h-11 w-full items-center gap-3 rounded-lg py-2 pr-2.5 pl-2 text-left text-sm font-medium text-aq-ink transition-colors hover:bg-aq-overlay/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
        >
            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-aq-accent/15 text-aq-accent">
                <Icon aria-hidden="true" className="size-4" />
            </span>
            <span className="grow">{label}</span>
            <ChevronRight
                aria-hidden="true"
                className="size-4 shrink-0 text-aq-ink-muted transition-transform group-hover:translate-x-0.5 motion-reduce:transform-none"
            />
        </button>
    );
}

export default function Login() {
    const { canResetPassword, status, demoCredentials } = usePage<LoginPageProps>().props;
    const [step, setStep] = useState<AuthStep>('password');
    const [optionsOpen, setOptionsOpen] = useState(false);

    /*
     * The disclosure needs an id for `aria-controls`. `useId()` output carries
     * characters that are legal in HTML but awkward in a selector, so it is
     * normalised the same way the field kit does it.
     */
    const disclosureUid = useId();
    const optionsId = `sign-in-options-${disclosureUid.replace(/[^a-zA-Z0-9_-]/g, '')}`;

    /*
     * One address for every transport. The old tabs each owned their own copy,
     * so typing an email and then choosing "Email code" presented an empty
     * field and made the visitor type it twice. The value is pushed into all
     * three forms on every keystroke: the active form is not the only one that
     * can submit — a step switch re-mounts a form that must already be valid.
     */
    const [email, setEmail] = useState(demoCredentials?.email ?? '');

    const focusField = (field: string): void => {
        document.querySelector<HTMLElement>(`[name="${field}"]`)?.focus();
    };

    /* Password transport */
    const {
        form: loginForm,
        errors: loginErrors,
        submit: submitLogin,
        touch: touchLogin,
        clearError: clearLoginError,
    } = useZodForm({
        // Prefill from the server-provided demo credentials, exactly like the
        // Vue sign-in: the operator's credentials are the fastest correct path
        // into a demo environment, and an empty first paint reads as broken.
        initial: {
            email: demoCredentials?.email ?? '',
            password: demoCredentials?.password ?? '',
            remember: false,
        },
        schema: loginSchema,
    });

    /* Email-code transport — two steps, the address lives in the session. */
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

    const {
        form: emailCodeForm,
        errors: emailCodeErrors,
        submit: submitEmailCode,
        touch: touchEmailCode,
        clearError: clearEmailCodeError,
    } = useZodForm({
        initial: { email: demoCredentials?.email ?? '' },
        schema: emailCodeSchema,
    });

    const {
        form: codeForm,
        errors: codeErrors,
        submit: submitCode,
        touch: touchCode,
        clearError: clearCodeError,
    } = useZodForm({
        initial: { code: '' },
        schema: codeSchema,
    });

    /* Authenticator transport — passwordless TOTP from an enrolled secret. */
    const {
        form: authenticatorForm,
        errors: authenticatorErrors,
        submit: submitAuthenticator,
        touch: touchAuthenticator,
        clearError: clearAuthenticatorError,
    } = useZodForm({
        initial: { email: demoCredentials?.email ?? '', code: '' },
        schema: authenticatorSchema,
    });

    /* Passkey transport — the WebAuthn ceremony lives in the module hook. */
    const passkey = usePasskeyLogin();

    /*
     * Conditional UI: the passkeys registered for this device show up in the
     * email field's autofill list. Started on the password step only — on a
     * code step the browser would offer a credential the visitor is not
     * looking for — and cancelled on unmount so the pending WebAuthn request
     * dies with the component.
     *
     * `step` is the only dependency on purpose: the two hook functions hold a
     * ref and a state setter, so their identity changing must not restart the
     * ceremony.
     */
    useEffect(() => {
        if (step !== 'password') {
            return;
        }

        let active = true;

        void passkey.startAutofill().then((result) => {
            if (active && result !== null) {
                router.visit(result.redirect);
            }
        });

        return () => {
            active = false;
            passkey.cancelAutofill();
        };
    }, [step]);

    const onPasskeySignIn = (): void => {
        void passkey.signIn().then((result) => {
            if (result !== null) {
                router.visit(result.redirect);
            }
        });
    };

    const updateEmail = (value: string): void => {
        setEmail(value);
        loginForm.setData('email', value);
        emailCodeForm.setData('email', value);
        authenticatorForm.setData('email', value);
        clearLoginError('email');
        clearEmailCodeError('email');
        clearAuthenticatorError('email');
    };

    const onLogin = (): void => {
        // The visitor committed to the password; an ambient passkey sheet left
        // open over the result would only be in the way.
        passkey.cancelAutofill();

        submitLogin(() =>
            loginForm.post('/login', {
                preserveScroll: true,
                onFinish: () => loginForm.reset('password'),
            }),
        );
    };

    const fillDemo = (): void => {
        if (!demoCredentials) {
            return;
        }

        updateEmail(demoCredentials.email);
        loginForm.setData('password', demoCredentials.password);
    };

    const onSendEmailCode = (): void => {
        submitEmailCode(() => {
            void sendEmailCode(emailCodeForm.data.email);
        });
    };

    const onVerifyEmailCode = (): void => {
        submitCode(() => {
            void verifyEmailCode(codeForm.data.code).then((result) => {
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

    /** Leave a code transport without losing the address. */
    const goTo = (next: AuthStep): void => {
        resetEmailCode();
        codeForm.reset('code');
        passkey.clearError();
        // A returning visitor should find the card as it first looked, not the
        // disclosure they opened on the previous step.
        setOptionsOpen(false);
        setStep(next);
    };

    const onUseDifferentEmail = (): void => {
        // The email field remounts with `autoFocus`, so it takes focus without
        // a manual query.
        resetEmailCode();
        codeForm.reset('code');
    };

    const heading = (() => {
        if (step === 'authenticator') {
            return {
                title: 'Use your authenticator app',
                subtitle: 'Enter the six-digit code it shows for AquaShield',
            };
        }

        if (step === 'email-code') {
            return emailCodeStep === 'email'
                ? {
                      title: 'Sign in with an email code',
                      subtitle: 'We will send a six-digit code to your inbox',
                  }
                : {
                      title: 'Check your email',
                      subtitle: `Enter the code sent to ${emailCodeEmail || email}`,
                  };
        }

        return { title: 'Sign in', subtitle: 'Access your account' };
    })();

    return (
        <AuthLayout title={heading.title} subtitle={heading.subtitle} width="md" align="start">
            <Head title="Sign in" />

            {status ? (
                <p
                    role="status"
                    className="mb-4 rounded-md border border-aq-accent/40 bg-aq-accent/10 px-3 py-2.5 text-sm font-medium text-aq-ink"
                >
                    {status}
                </p>
            ) : null}

            {step === 'password' && demoCredentials ? (
                <div className="mb-4 rounded-xl border border-aq-line bg-aq-elevated/60 p-4">
                    <div className="mb-2 flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                        <span className="text-xs font-semibold tracking-wide text-aq-ink-muted uppercase">
                            Demo credentials
                        </span>
                        <button
                            type="button"
                            onClick={fillDemo}
                            className="rounded-lg border border-aq-line px-3 py-1 text-xs font-medium whitespace-nowrap text-aq-accent transition-colors hover:bg-aq-overlay/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
                        >
                            Fill demo credentials
                        </button>
                    </div>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                        <dt className="text-aq-ink-muted">Email</dt>
                        <dd className="font-mono text-aq-ink">{demoCredentials.email}</dd>
                        <dt className="text-aq-ink-muted">Password</dt>
                        <dd className="font-mono text-aq-ink">{demoCredentials.password}</dd>
                        <dt className="text-aq-ink-muted">Name</dt>
                        <dd className="font-mono text-aq-ink">{demoCredentials.name}</dd>
                    </dl>
                </div>
            ) : null}

            {/* Google sits on the first step only: on a code step it competes
                with the field the visitor just asked for, and the back link is
                the way out. */}
            {step === 'password' ? <GoogleButton /> : null}

            {step === 'password' ? (
                <div className="my-5 flex items-center gap-3" aria-hidden="true">
                    <span className="h-px grow bg-aq-line" />
                    <span className="text-xs tracking-wide text-aq-ink-muted">or</span>
                    <span className="h-px grow bg-aq-line" />
                </div>
            ) : null}

            {step === 'password' ? (
                <>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            onLogin();
                        }}
                        className="flex flex-col gap-4"
                        noValidate
                    >
                        <FormErrorSummary
                            errors={loginErrors}
                            labels={fieldLabels}
                            onFieldFocus={focusField}
                        />

                        <TextField
                            label="Email"
                            name="email"
                            type="email"
                            value={email}
                            onChange={updateEmail}
                            onBlur={() => touchLogin('email')}
                            error={loginErrors.email}
                            // Both tokens, not just `webauthn`: adding only the
                            // passkey token would hide this field from password
                            // managers. Chrome's guidance is the space-separated
                            // pair so both autofills coexist.
                            autocomplete="username webauthn"
                            required
                            autoFocus
                        />

                        <PasswordField
                            label="Password"
                            name="password"
                            value={loginForm.data.password}
                            onChange={(value) => {
                                loginForm.setData('password', value);
                                clearLoginError('password');
                            }}
                            onBlur={() => touchLogin('password')}
                            error={loginErrors.password}
                            autocomplete="current-password"
                            required
                        />

                        <div className="flex items-center justify-between gap-4">
                            <CheckboxField
                                label="Remember me"
                                name="remember"
                                checked={Boolean(loginForm.data.remember)}
                                onChange={(checked) => loginForm.setData('remember', checked)}
                                error={loginErrors.remember}
                            />

                            {canResetPassword ? (
                                <Link href="/forgot-password" className={linkClass}>
                                    Forgot password?
                                </Link>
                            ) : null}
                        </div>

                        <AppButton
                            type="submit"
                            shine
                            loading={loginForm.processing}
                            className="h-11 w-full"
                        >
                            Sign in
                        </AppButton>
                    </form>

                    {/* A rule, not a second form: the passkey signs into the
                        same account and opens the same session, it is just a
                        stronger credential. The button repeats the secondary
                        geometry of the Google CTA so the two alternatives read
                        as peers of each other and as subordinates of
                        "Sign in" — a bare text row made the strongest way in
                        look like a footnote. */}
                    <div className="my-5 border-t border-aq-line" aria-hidden="true" />

                    <button
                        type="button"
                        onClick={onPasskeySignIn}
                        disabled={passkey.authenticating}
                        aria-busy={passkey.authenticating || undefined}
                        className="flex h-11 w-full items-center justify-center gap-2.5 rounded-xl border border-aq-line bg-aq-elevated/40 text-sm font-semibold text-aq-ink transition-colors duration-200 hover:border-aq-line-strong hover:bg-aq-overlay/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent disabled:cursor-wait disabled:opacity-70 motion-reduce:transition-none"
                    >
                        <KeyRound aria-hidden="true" className="size-4 shrink-0" />
                        {passkey.authenticating
                            ? 'Waiting for your passkey…'
                            : 'Sign in with a passkey'}
                    </button>

                    {passkey.error ? (
                        <p role="alert" className="mt-2 text-sm font-medium text-aq-danger-strong">
                            {passkey.error}
                        </p>
                    ) : null}

                    {/* The one-time-code transports are alternatives to the
                        password, not peers of it. One quiet disclosure keeps
                        them discoverable without giving three fallbacks the
                        weight of the primary form. The trigger is a full-width
                        button with the label on the start edge and the chevron
                        on the end edge: a centred label floats away from the
                        rows it opens, and the disclosure is a button, not a
                        link. */}
                    <div className="mt-2">
                        <button
                            type="button"
                            aria-expanded={optionsOpen}
                            aria-controls={optionsId}
                            onClick={() => setOptionsOpen((open) => !open)}
                            className="flex h-11 w-full items-center justify-between gap-2 rounded-md px-1 text-sm font-medium text-aq-ink-muted transition-colors hover:text-aq-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
                        >
                            <span>Sign-in options</span>
                            <ChevronDown
                                aria-hidden="true"
                                className={cn(
                                    'size-4 shrink-0 transition-transform motion-reduce:transform-none',
                                    optionsOpen && 'rotate-180',
                                )}
                            />
                        </button>

                        {optionsOpen ? (
                            <div id={optionsId} className="mt-1 flex flex-col gap-1">
                                <MethodOption
                                    icon={Mail}
                                    label="Email me a sign-in code"
                                    onClick={() => goTo('email-code')}
                                />
                                <MethodOption
                                    icon={ShieldCheck}
                                    label="Use an authenticator app"
                                    onClick={() => goTo('authenticator')}
                                />
                            </div>
                        ) : null}
                    </div>
                </>
            ) : null}

            {step === 'email-code' && emailCodeStep === 'email' ? (
                <>
                    <BackToSignIn onClick={() => goTo('password')} />

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            onSendEmailCode();
                        }}
                        className="flex flex-col gap-4"
                        noValidate
                    >
                        <FormErrorSummary
                            errors={emailCodeErrors}
                            labels={fieldLabels}
                            onFieldFocus={focusField}
                        />

                        {emailCodeError ? (
                            <p role="alert" className="text-sm font-medium text-aq-danger-strong">
                                {emailCodeError}
                            </p>
                        ) : null}

                        <TextField
                            label="Email"
                            name="email"
                            type="email"
                            value={email}
                            onChange={updateEmail}
                            onBlur={() => touchEmailCode('email')}
                            error={emailCodeErrors.email}
                            autocomplete="username"
                            required
                            autoFocus
                        />

                        <AppButton type="submit" loading={emailCodeSending} className="w-full">
                            Send sign-in code
                        </AppButton>

                        <p className="text-xs leading-relaxed text-aq-ink-muted">
                            The code expires in two minutes. If the address is registered, it
                            arrives in a few seconds.
                        </p>
                    </form>
                </>
            ) : null}

            {step === 'email-code' && emailCodeStep === 'code' ? (
                <>
                    <BackToSignIn onClick={() => goTo('password')} />

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            onVerifyEmailCode();
                        }}
                        className="flex flex-col gap-4"
                        noValidate
                    >
                        {emailCodeError ? (
                            <p role="alert" className="text-sm font-medium text-aq-danger-strong">
                                {emailCodeError}
                            </p>
                        ) : null}

                        <FormErrorSummary
                            errors={codeErrors}
                            labels={fieldLabels}
                            onFieldFocus={focusField}
                        />

                        <OtpField
                            label="Sign-in code"
                            name="code"
                            value={codeForm.data.code}
                            onChange={(value) => {
                                codeForm.setData('code', value);
                                clearCodeError('code');
                            }}
                            onBlur={() => touchCode('code')}
                            error={codeErrors.code}
                            required
                            autoFocus
                        />

                        <AppButton type="submit" loading={emailCodeVerifying} className="w-full">
                            Verify and sign in
                        </AppButton>

                        <button type="button" onClick={onUseDifferentEmail} className={linkClass}>
                            Use a different email
                        </button>
                    </form>
                </>
            ) : null}

            {step === 'authenticator' ? (
                <>
                    <BackToSignIn onClick={() => goTo('password')} />

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            onAuthenticatorSubmit();
                        }}
                        className="flex flex-col gap-4"
                        noValidate
                    >
                        <FormErrorSummary
                            errors={authenticatorErrors}
                            labels={fieldLabels}
                            onFieldFocus={focusField}
                        />

                        <TextField
                            label="Email"
                            name="email"
                            type="email"
                            value={email}
                            onChange={updateEmail}
                            onBlur={() => touchAuthenticator('email')}
                            error={authenticatorErrors.email}
                            autocomplete="username"
                            required
                            autoFocus
                        />

                        <OtpField
                            label="Authenticator code"
                            name="code"
                            value={authenticatorForm.data.code}
                            onChange={(value) => {
                                authenticatorForm.setData('code', value);
                                clearAuthenticatorError('code');
                            }}
                            onBlur={() => touchAuthenticator('code')}
                            error={authenticatorErrors.code}
                            required
                        />

                        <AppButton
                            type="submit"
                            loading={authenticatorForm.processing}
                            className="w-full"
                        >
                            Sign in with authenticator
                        </AppButton>

                        <p className="text-xs leading-relaxed text-aq-ink-muted">
                            No authenticator yet? Sign in with your password, then scan the QR in
                            Profile &amp; security.
                        </p>
                    </form>
                </>
            ) : null}
        </AuthLayout>
    );
}
