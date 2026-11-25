/**
 * Auth/OtpLogin — passwordless sign-in with an emailed code.
 *
 * This is the *server-driven* half of the email-code transport: the page posts
 * to `/login/otp`, comes back with the address in the session, then posts the
 * code to `/login/otp/verify`. It has to work without the JSON hook the sign-in
 * card uses, because `OneTimePasswordLoginController::send` redirects here when
 * the request is not an XHR.
 *
 * It was a placeholder until now — a paragraph and two dead buttons — so every
 * non-JS send and every direct visit to `/login/otp` ended on a page that could
 * not submit anything.
 */
import { Head, Link, usePage } from '@inertiajs/react';
import * as z from 'zod';
import { useZodForm } from '@/lib/forms';
import { ArrowLeft } from 'lucide-react';
import AuthLayout from '@/Layouts/AuthLayout';
import TextField from '@/Components/Forms/TextField';
import OtpField from '@/Components/Forms/OtpField';
import AppButton from '@/Components/Forms/AppButton';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';

interface OtpLoginPageProps {
    /** Inertia's PageProps requires an index signature. */
    [key: string]: unknown;
    /** Set by the controller once a code has been requested. */
    email: string | null;
    status: string | null;
}

const emailSchema = z.object({
    email: z.email('Enter a valid email address'),
});

const codeSchema = z.object({
    code: z.string().regex(/^\d{6}$/, 'Enter the six-digit code from the email'),
});

const fieldLabels = {
    email: 'Email',
    code: 'Sign-in code',
};

function BackToSignIn() {
    return (
        <Link
            href="/login"
            className="-ml-1 mb-4 inline-flex items-center gap-1.5 self-start rounded-md px-1 py-0.5 text-sm font-medium text-aq-ink-muted transition-colors hover:text-aq-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
        >
            <ArrowLeft aria-hidden="true" className="size-4" />
            Back to sign in
        </Link>
    );
}

export default function OtpLogin() {
    const { email, status } = usePage<OtpLoginPageProps>().props;
    const onCodeStep = typeof email === 'string' && email !== '';

    const emailForm = useZodForm({
        initial: { email: '' },
        schema: emailSchema,
    });

    const codeForm = useZodForm({
        initial: { code: '' },
        schema: codeSchema,
    });

    const onSend = (): void => {
        emailForm.submit(() =>
            emailForm.form.post('/login/otp', {
                preserveScroll: true,
            }),
        );
    };

    const onVerify = (): void => {
        codeForm.submit(() =>
            codeForm.form.post('/login/otp/verify', {
                preserveScroll: true,
            }),
        );
    };

    return (
        <AuthLayout
            title={onCodeStep ? 'Check your email' : 'Sign in with an email code'}
            subtitle={
                onCodeStep
                    ? `Enter the code sent to ${email}`
                    : 'We will send a six-digit code to your inbox'
            }
            width="md"
            align="start"
        >
            <Head title="Sign in with an email code" />

            {status ? (
                <p
                    role="status"
                    className="mb-4 rounded-md border border-aq-accent/40 bg-aq-accent/10 px-3 py-2.5 text-sm font-medium text-aq-ink"
                >
                    {status}
                </p>
            ) : null}

            <BackToSignIn />

            {onCodeStep ? (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        onVerify();
                    }}
                    className="flex flex-col gap-4"
                    noValidate
                >
                    <FormErrorSummary errors={codeForm.errors} labels={fieldLabels} />

                    <OtpField
                        label="Sign-in code"
                        name="code"
                        value={codeForm.form.data.code}
                        onChange={(value) => {
                            codeForm.form.setData('code', value);
                            codeForm.clearError('code');
                        }}
                        onBlur={() => codeForm.touch('code')}
                        error={codeForm.errors.code}
                        required
                        autoFocus
                    />

                    <AppButton
                        type="submit"
                        shine
                        loading={codeForm.form.processing}
                        className="h-11 w-full"
                    >
                        Verify and sign in
                    </AppButton>

                    {/* `reset` clears the address the controller keeps in the
                        session, so the page falls back to its first step
                        instead of trapping the visitor on this one. */}
                    <Link
                        href="/login/otp?reset=1"
                        className="self-center rounded-sm text-sm font-medium text-aq-accent transition-colors hover:text-aq-accent-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
                    >
                        Use a different email
                    </Link>
                </form>
            ) : (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        onSend();
                    }}
                    className="flex flex-col gap-4"
                    noValidate
                >
                    <FormErrorSummary errors={emailForm.errors} labels={fieldLabels} />

                    <TextField
                        label="Email"
                        name="email"
                        type="email"
                        value={emailForm.form.data.email}
                        onChange={(value) => {
                            emailForm.form.setData('email', value);
                            emailForm.clearError('email');
                        }}
                        onBlur={() => emailForm.touch('email')}
                        error={emailForm.errors.email}
                        autocomplete="username"
                        required
                        autoFocus
                    />

                    <AppButton type="submit" loading={emailForm.form.processing} className="w-full">
                        Send sign-in code
                    </AppButton>

                    <p className="text-xs leading-relaxed text-aq-ink-muted">
                        The code expires in two minutes. If the address is registered, it arrives in
                        a few seconds.
                    </p>
                </form>
            )}
        </AuthLayout>
    );
}
