/**
 * Auth/ConfirmPassword — Fortify's password-confirmation gate.
 *
 * Fortify parks a signed-in user here whenever a sensitive action needs a fresh
 * credential: enabling two-factor authentication, enrolling a passkey, changing
 * the password or the profile. `POST /user/confirm-password` verifies it and
 * records `auth.password_confirmed_at`; success redirects to the intended URL.
 *
 * This page used to be a placeholder whose "Confirm" button was not inside a
 * form — so the gate could not be passed, and every 2FA enrolment dead-ended
 * here. It now posts the credential and offers a sign-out for someone who is
 * signed into the wrong account or cannot remember the password (an
 * authenticated visitor cannot reach the forgot-password screen).
 */
import { Head } from '@inertiajs/react';
import * as z from 'zod';
import { useLogout } from '@/lib/auth';
import { useZodForm } from '@/lib/forms';
import AuthLayout from '@/Layouts/AuthLayout';
import PasswordField from '@/Components/Forms/PasswordField';
import AppButton from '@/Components/Forms/AppButton';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';

const schema = z.object({
    password: z.string().min(1, 'Enter your password'),
});

const fieldLabels = {
    password: 'Password',
};

export default function ConfirmPassword() {
    const { form, errors, submit, touch, clearError } = useZodForm({
        initial: { password: '' },
        schema,
    });
    const logout = useLogout();

    const onConfirm = (): void => {
        submit(() =>
            form.post('/user/confirm-password', {
                preserveScroll: true,
                // Never leave the credential in the DOM after an attempt.
                onFinish: () => form.reset('password'),
            }),
        );
    };

    return (
        <AuthLayout
            title="Confirm your password"
            subtitle="For your security, confirm it to continue"
            width="md"
        >
            <Head title="Confirm password" />

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    onConfirm();
                }}
                className="flex flex-col gap-4"
                noValidate
            >
                <FormErrorSummary errors={errors} labels={fieldLabels} />

                <PasswordField
                    label="Password"
                    name="password"
                    value={form.data.password}
                    onChange={(value) => {
                        form.setData('password', value);
                        clearError('password');
                    }}
                    onBlur={() => touch('password')}
                    error={errors.password}
                    autocomplete="current-password"
                    required
                    autoFocus
                />

                <AppButton type="submit" shine loading={form.processing} className="h-11 w-full">
                    Confirm
                </AppButton>
            </form>

            <div className="mt-5 border-t border-aq-line pt-4 text-center">
                <button
                    type="button"
                    onClick={logout}
                    className="rounded-sm text-sm text-aq-ink-muted transition-colors hover:text-aq-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
                >
                    Sign out
                </button>
            </div>
        </AuthLayout>
    );
}
