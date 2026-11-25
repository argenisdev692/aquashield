/**
 * Auth/ResetPassword — password reset form.
 */
import { Head, usePage } from '@inertiajs/react';
import { useZodForm } from '@/lib/forms';
import * as z from 'zod';
import TextField from '@/Components/Forms/TextField';
import PasswordField from '@/Components/Forms/PasswordField';
import AppButton from '@/Components/Forms/AppButton';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import AuthLayout from '@/Layouts/AuthLayout';
import { PASSWORD_HINT, PASSWORD_MAX_LENGTH, passwordSchema } from '@/lib/auth';

interface ResetPasswordPageProps {
    /** Inertia's PageProps requires an index signature. */
    [key: string]: unknown;
    email: string | null;
    token: string;
}

const resetSchema = z
    .object({
        token: z.string().min(1),
        email: z.email('Enter a valid email address'),
        password: passwordSchema,
        password_confirmation: z.string(),
    })
    .refine((values) => values.password === values.password_confirmation, {
        path: ['password_confirmation'],
        message: 'Passwords do not match',
    });

export default function ResetPassword() {
    const { email, token } = usePage<ResetPasswordPageProps>().props;

    const { form, errors, submit, touch, clearError } = useZodForm({
        initial: {
            token: token ?? '',
            email: email ?? '',
            password: '',
            password_confirmation: '',
        },
        schema: resetSchema,
    });

    const fieldLabels = {
        token: 'Token',
        email: 'Email',
        password: 'New password',
        password_confirmation: 'Confirm password',
    };

    return (
        <AuthLayout title="Reset password" subtitle="Choose a new password" width="sm">
            <Head title="Reset password" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    submit(() =>
                        form.post('/reset-password', {
                            onFinish: () => form.reset('password', 'password_confirmation'),
                        }),
                    );
                }}
                className="flex flex-col gap-4"
                noValidate
            >
                <FormErrorSummary errors={errors} labels={fieldLabels} />

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
                />

                <PasswordField
                    label="New password"
                    name="password"
                    value={form.data.password}
                    onChange={(value) => {
                        form.setData('password', value);
                        clearError('password');
                    }}
                    onBlur={() => touch('password')}
                    error={errors.password}
                    hint={PASSWORD_HINT}
                    maxLength={PASSWORD_MAX_LENGTH}
                    autocomplete="new-password"
                    required
                />

                <PasswordField
                    label="Confirm password"
                    name="password_confirmation"
                    value={form.data.password_confirmation}
                    onChange={(value) => {
                        form.setData('password_confirmation', value);
                        clearError('password_confirmation');
                    }}
                    onBlur={() => touch('password_confirmation')}
                    error={errors.password_confirmation}
                    maxLength={PASSWORD_MAX_LENGTH}
                    autocomplete="new-password"
                    required
                />

                <AppButton type="submit" loading={form.processing} className="w-full">
                    Reset password
                </AppButton>
            </form>
        </AuthLayout>
    );
}
