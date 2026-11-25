/**
 * Users/Activate — the public, signed first-time activation screen.
 *
 * The user only chooses a password; name and e-mail are read-only because they
 * are the administrator's record and the signature already proves the link was
 * issued for this account. An invalid, expired or superseded link renders the
 * same shell with a clear explanation instead of a bare 403.
 */
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Circle, ShieldCheck } from 'lucide-react';
import * as z from 'zod';
import AppButton from '@/Components/Forms/AppButton';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import PasswordField from '@/Components/Forms/PasswordField';
import AuthLayout from '@/Layouts/AuthLayout';
import {
    PASSWORD_HINT,
    PASSWORD_MAX_LENGTH,
    PASSWORD_MIN_LENGTH,
    passwordSchema,
} from '@/lib/auth';
import { useZodForm } from '@/lib/forms';
import { cn } from '@/lib/utils';

/** Generated from `Modules\Users\Data\ActivationUserData` — never hand-written. */
type ActivationUser = Modules.Users.Data.ActivationUserData;

interface ActivatePageProps {
    /** Null until the controller has validated the signature and lifecycle. */
    user: ActivationUser | null;
    state: 'ready' | 'expired' | 'invalid' | 'used' | 'superseded';
    message: string;
    actionUrl: string | null;
    expiresInHours: number;
}

const activateSchema = z
    .object({
        password: passwordSchema,
        password_confirmation: z.string(),
    })
    .refine((values) => values.password === values.password_confirmation, {
        path: ['password_confirmation'],
        message: 'Passwords do not match.',
    });

const strengthOf = (value: string): { score: number; label: string } => {
    if (value === '') {
        return { score: 0, label: 'Not set' };
    }

    let score = 0;

    if (value.length >= PASSWORD_MIN_LENGTH) score++;
    if (value.length >= PASSWORD_MIN_LENGTH + 4) score++;
    if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;

    const bounded = Math.min(4, score);

    return {
        score: bounded,
        label: ['Very weak', 'Weak', 'Fair', 'Good', 'Strong'][bounded] ?? 'Weak',
    };
};

const Rule = ({ met, children }: { met: boolean; children: React.ReactNode }) => (
    <li
        className={cn(
            'flex items-center gap-1.5 text-xs',
            met ? 'text-aq-success' : 'text-surface-500 dark:text-surface-300',
        )}
    >
        {met ? (
            <CheckCircle2 className="size-3.5" aria-hidden="true" />
        ) : (
            <Circle className="size-3.5" aria-hidden="true" />
        )}
        {children}
    </li>
);

export default function Activate({
    user,
    state,
    message,
    actionUrl,
    expiresInHours,
}: ActivatePageProps) {
    const { form, errors, submit, touch, clearError } = useZodForm({
        initial: { password: '', password_confirmation: '' },
        schema: activateSchema,
    });

    const strength = strengthOf(form.data.password);
    const matches =
        form.data.password !== '' && form.data.password === form.data.password_confirmation;
    const longEnough =
        form.data.password.length >= PASSWORD_MIN_LENGTH &&
        form.data.password.length <= PASSWORD_MAX_LENGTH;

    if (state !== 'ready' || actionUrl === null || user === null) {
        return (
            <AuthLayout
                title="Invitation unavailable"
                subtitle={user?.email ?? 'Invitation link'}
                width="sm"
            >
                <Head title="Activation link" />

                <div className="flex flex-col gap-4 text-sm text-aq-ink-soft">
                    <p
                        role="alert"
                        className="rounded-lg border border-aq-warning/40 bg-aq-warning/10 px-3 py-2.5 text-aq-warning"
                    >
                        {message}
                    </p>
                    <p>
                        Ask the administrator who created your account to send a new invitation. You
                        can also sign in if you already set a password.
                    </p>
                    <Link
                        href="/login"
                        className="font-medium text-aq-accent underline underline-offset-2 transition-colors duration-150 hover:text-aq-accent-strong"
                    >
                        Go to sign in
                    </Link>
                </div>
            </AuthLayout>
        );
    }

    return (
        <AuthLayout
            title="Activate your account"
            subtitle="Choose a password to finish setting up"
            width="sm"
        >
            <Head title="Activate account" />

            <div className="mb-5 rounded-lg border border-aq-line/70 bg-aq-void/40 px-3 py-2.5 text-sm">
                <p className="font-medium text-aq-ink">{user.name}</p>
                <p className="text-aq-ink-muted">{user.email}</p>
            </div>

            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    submit(() => {
                        form.post(actionUrl, {
                            onFinish: () => form.reset('password', 'password_confirmation'),
                        });
                    });
                }}
                className="flex flex-col gap-4"
                noValidate
            >
                <FormErrorSummary
                    errors={errors}
                    labels={{
                        password: 'Password',
                        password_confirmation: 'Confirm password',
                        invitation: 'Invitation',
                    }}
                />

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
                    hint={PASSWORD_HINT}
                    maxLength={PASSWORD_MAX_LENGTH}
                    autocomplete="new-password"
                    required
                    autoFocus
                />

                <div className="flex flex-col gap-2" aria-live="polite">
                    <div className="flex items-center gap-1.5" aria-hidden="true">
                        {[1, 2, 3, 4].map((step) => (
                            <span
                                key={step}
                                className={cn(
                                    'h-1.5 flex-1 rounded-full transition-colors duration-150 motion-reduce:transition-none',
                                    step <= strength.score
                                        ? strength.score <= 1
                                            ? 'bg-aq-danger'
                                            : strength.score === 2
                                              ? 'bg-aq-warning'
                                              : 'bg-aq-success'
                                        : 'bg-surface-200 dark:bg-surface-700',
                                )}
                            />
                        ))}
                    </div>
                    <span className="text-xs text-surface-500 dark:text-surface-300">
                        Strength: {strength.label}
                    </span>

                    <ul className="flex flex-col gap-1">
                        <Rule met={longEnough}>{PASSWORD_HINT}</Rule>
                        <Rule met={matches}>Passwords match</Rule>
                    </ul>
                </div>

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

                <p className="flex items-center gap-1.5 text-xs text-aq-ink-muted">
                    <ShieldCheck className="size-3.5 shrink-0" aria-hidden="true" />
                    This link is single-use and expires {expiresInHours} hours after it was sent.
                </p>

                <AppButton
                    type="submit"
                    loading={form.processing}
                    disabled={form.processing || !longEnough || !matches}
                    className="w-full"
                >
                    Activate account
                </AppButton>
            </form>
        </AuthLayout>
    );
}
