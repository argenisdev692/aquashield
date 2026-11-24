/**
 * Auth/ForgotPassword — password reset request.
 */
import { Head, Link, usePage } from '@inertiajs/react';
import { useZodForm } from '@/lib/forms';
import * as z from 'zod';
import TextField from '@/Components/Forms/TextField';
import AppButton from '@/Components/Forms/AppButton';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import AuthLayout from '@/Layouts/AuthLayout';

interface ForgotPasswordPageProps {
    /** Inertia's PageProps requires an index signature. */
    [key: string]: unknown;
    status?: string | null;
}

const forgotSchema = z.object({
    email: z.email('Enter a valid email address'),
});

export default function ForgotPassword() {
    const { status } = usePage<ForgotPasswordPageProps>().props;

    const { form, errors, submit, touch, clearError } = useZodForm({
        initial: { email: '' },
        schema: forgotSchema,
    });

    const focusField = (field: string): void => {
        document.querySelector<HTMLElement>(`[name="${field}"]`)?.focus();
    };

    const fieldLabels = {
        email: 'Email',
    };

    return (
        <AuthLayout title="Reset password" subtitle="We'll email you a reset link" width="sm">
            <Head title="Reset password" />

            {status ? (
                <p
                    role="status"
                    className="mb-4 rounded-lg border border-aq-accent/40 bg-aq-accent/10 px-3 py-2.5 text-sm text-aq-ink"
                >
                    {status}
                </p>
            ) : null}

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    submit(() => form.post('/forgot-password'));
                }}
                className="flex flex-col gap-4"
                noValidate
            >
                <FormErrorSummary errors={errors} labels={fieldLabels} onFieldFocus={focusField} />

                <TextField
                    label="Email"
                    name="email"
                    type="email"
                    value={form.data.email}
                    onChange={(value) => {
                        form.setData('email', value);
                        clearError('email');
                    }}
                    onBlur={() => touch('email')}
                    error={errors.email}
                    autocomplete="username"
                    required
                    autoFocus
                />

                <AppButton type="submit" loading={form.processing} className="w-full">
                    Email password reset link
                </AppButton>
            </form>

            <p className="mt-4 text-center text-sm text-aq-ink-muted">
                <Link
                    href="/login"
                    className="text-aq-accent underline-offset-4 transition-colors duration-150 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
                >
                    Back to sign in
                </Link>
            </p>
        </AuthLayout>
    );
}
