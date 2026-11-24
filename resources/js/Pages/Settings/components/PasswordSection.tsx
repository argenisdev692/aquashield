/**
 * PasswordSection — the fields and strength meter for `/user/password`.
 *
 * The form element stays in the page so the page owns the submit action; this
 * component owns the password-specific presentation (requirements, meter and
 * the three controlled fields).
 */
import { useId } from 'react';
import type { InertiaForm } from '@inertiajs/react';
import PasswordField from '@/Components/Forms/PasswordField';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import { cn } from '@/lib/utils';
import { PASSWORD_HINT, PASSWORD_MAX_LENGTH, PASSWORD_MIN_LENGTH } from '@/lib/auth';
import type { UpdatePasswordForm } from '@/Modules/Users/schemas/profileSettings';

interface PasswordSectionProps {
    form: InertiaForm<UpdatePasswordForm>;
    errors: Partial<Record<string, string>>;
    onBlur: (field: keyof UpdatePasswordForm) => void;
}

const scorePassword = (value: string): number => {
    if (value === '') {
        return 0;
    }

    let score = value.length >= PASSWORD_MIN_LENGTH ? 1 : 0;

    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) {
        score += 1;
    }

    if (/\d/.test(value)) {
        score += 1;
    }

    if (/[^A-Za-z0-9]/.test(value)) {
        score += 1;
    }

    return Math.min(score, 4);
};

const STRENGTH_LABELS = ['Too short', 'Weak', 'Fair', 'Good', 'Strong'] as const;

export default function PasswordSection({ form, errors, onBlur }: PasswordSectionProps) {
    const meterId = useId();
    const score = scorePassword(form.data.password);
    const label = STRENGTH_LABELS[score];

    return (
        <div className="flex flex-col gap-4">
            <FormErrorSummary
                errors={errors}
                labels={{
                    current_password: 'Current password',
                    password: 'New password',
                    password_confirmation: 'Confirm new password',
                }}
                onFieldFocus={(field) =>
                    document.querySelector<HTMLElement>(`[name="${field}"]`)?.focus()
                }
            />

            <PasswordField
                label="Current password"
                name="current_password"
                value={form.data.current_password}
                onChange={(value) => form.setData('current_password', value)}
                onBlur={() => onBlur('current_password')}
                error={errors.current_password}
                autocomplete="current-password"
                required
            />

            <PasswordField
                label="New password"
                name="password"
                value={form.data.password}
                onChange={(value) => form.setData('password', value)}
                onBlur={() => onBlur('password')}
                error={errors.password}
                hint={PASSWORD_HINT}
                autocomplete="new-password"
                minLength={PASSWORD_MIN_LENGTH}
                maxLength={PASSWORD_MAX_LENGTH}
                required
            />

            <div className="flex flex-col gap-1.5" aria-live="polite">
                <div id={meterId} className="flex items-center gap-2">
                    {[0, 1, 2, 3].map((step) => (
                        <span
                            key={step}
                            aria-hidden="true"
                            className={cn(
                                'h-1.5 flex-1 rounded-full transition-colors duration-200 motion-reduce:transition-none',
                                step < score
                                    ? score >= 3
                                        ? 'bg-aq-success'
                                        : score === 2
                                          ? 'bg-aq-warning'
                                          : 'bg-aq-danger'
                                    : 'bg-surface-200 dark:bg-surface-700',
                            )}
                        />
                    ))}
                </div>

                <p className="text-xs text-surface-600 dark:text-surface-300">
                    Password strength: <span className="font-medium">{label}</span>
                </p>
            </div>

            <PasswordField
                label="Confirm new password"
                name="password_confirmation"
                value={form.data.password_confirmation}
                onChange={(value) => form.setData('password_confirmation', value)}
                onBlur={() => onBlur('password_confirmation')}
                error={errors.password_confirmation}
                autocomplete="new-password"
                minLength={PASSWORD_MIN_LENGTH}
                maxLength={PASSWORD_MAX_LENGTH}
                required
            />
        </div>
    );
}
