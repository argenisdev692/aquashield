/**
 * Auth/Register — sign up page.
 */
import { useZodForm } from '@/lib/forms';
import * as z from 'zod';
import TextField from '@/Components/Forms/TextField';
import PasswordField from '@/Components/Forms/PasswordField';
import FormActions from '@/Components/Forms/FormActions';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import { Link } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';

const schema = z
    .object({
        name: z.string().min(2, 'Name must be at least 2 characters'),
        email: z.string().email('Invalid email address'),
        password: z.string().min(8, 'Password must be at least 8 characters'),
        password_confirmation: z.string().min(8, 'Password must be at least 8 characters'),
    })
    .refine((data) => data.password === data.password_confirmation, {
        message: "Passwords don't match",
        path: ['password_confirmation'],
    });

export default function Register() {
    const { form, errors, submit, touch } = useZodForm({
        initial: {
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
        },
        schema,
    });

    return (
        <AuthLayout title="Create account" subtitle="Join AquaShield today" width="md">
            <FormErrorSummary errors={errors} onFieldFocus={() => {}} />

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    submit(() => {
                        form.post('/register', {
                            preserveScroll: true,
                        });
                    });
                }}
                className="space-y-4"
            >
                <TextField
                    label="Full name"
                    name="name"
                    value={form.data.name}
                    onChange={(v) => form.setData('name', v)}
                    onBlur={() => touch('name')}
                    error={errors.name}
                    required
                    autoFocus
                />

                <TextField
                    label="Email address"
                    name="email"
                    type="email"
                    value={form.data.email}
                    onChange={(v) => form.setData('email', v)}
                    onBlur={() => touch('email')}
                    error={errors.email}
                    placeholder="you@example.com"
                    required
                />

                <PasswordField
                    label="Password"
                    name="password"
                    value={form.data.password}
                    onChange={(v) => form.setData('password', v)}
                    onBlur={() => touch('password')}
                    error={errors.password}
                    placeholder="••••••••"
                    required
                />

                <PasswordField
                    label="Confirm password"
                    name="password_confirmation"
                    value={form.data.password_confirmation}
                    onChange={(v) => form.setData('password_confirmation', v)}
                    onBlur={() => touch('password_confirmation')}
                    error={errors.password_confirmation}
                    placeholder="••••••••"
                    required
                />

                <FormActions
                    submitLabel="Create account"
                    loading={form.processing}
                    disabled={form.processing}
                />
            </form>

            <p className="mt-6 text-center text-sm text-surface-600 dark:text-surface-400">
                Already have an account?{' '}
                <Link
                    href="/login"
                    className="font-medium text-primary-600 dark:text-primary-400 hover:underline transition-colors duration-150"
                >
                    Sign in
                </Link>
            </p>
        </AuthLayout>
    );
}
