/**
 * Auth/TwoFactorChallenge — the second factor after a password login.
 *
 * Fortify sends every account with a confirmed TOTP factor here
 * (`Fortify::twoFactorChallengeView`), and it posts back to
 * `/two-factor-challenge` with either `code` or `recovery_code`. This screen
 * used to be a placeholder with no input at all: the visitor was told to enter
 * a code, given no field, and left unable to sign in. Both transports are
 * implemented here — the six-digit TOTP and a single-use recovery code — with
 * the authenticator as the default because it is what nearly everyone uses.
 */
import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import * as z from 'zod';
import { useZodForm } from '@/lib/forms';
import AuthLayout from '@/Layouts/AuthLayout';
import OtpField from '@/Components/Forms/OtpField';
import TextField from '@/Components/Forms/TextField';
import AppButton from '@/Components/Forms/AppButton';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';

type ChallengeMode = 'code' | 'recovery';

const fieldLabels = {
    code: 'Authentication code',
    recovery_code: 'Recovery code',
};

const linkClass =
    'rounded-sm text-sm font-medium text-aq-accent transition-colors hover:text-aq-accent-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none';

export default function TwoFactorChallenge() {
    const [mode, setMode] = useState<ChallengeMode>('code');

    const focusField = (field: string): void => {
        document.querySelector<HTMLElement>(`[name="${field}"]`)?.focus();
    };

    /*
     * One hook per transport instead of one schema with a mode branch: the two
     * payloads have different field names and different rules, and Inertia
     * posts exactly what the active form holds, so the unused key never leaves
     * the browser.
     */
    const codeForm = useZodForm({
        initial: { code: '' },
        schema: z.object({
            code: z
                .string()
                .regex(/^\d{6}$/, 'Enter the six-digit code from your authenticator app'),
        }),
    });

    const recoveryForm = useZodForm({
        initial: { recovery_code: '' },
        schema: z.object({
            recovery_code: z.string().trim().min(1, 'Enter one of your recovery codes'),
        }),
    });

    const onCodeSubmit = (): void => {
        codeForm.submit(() =>
            codeForm.form.post('/two-factor-challenge', {
                preserveScroll: true,
            }),
        );
    };

    const onRecoverySubmit = (): void => {
        recoveryForm.submit(() =>
            recoveryForm.form.post('/two-factor-challenge', {
                preserveScroll: true,
            }),
        );
    };

    const switchMode = (next: ChallengeMode): void => {
        // The panel that replaces this one mounts its field with `autoFocus`.
        codeForm.form.clearErrors();
        recoveryForm.form.clearErrors();
        setMode(next);
    };

    return (
        <AuthLayout
            title="Two-factor authentication"
            subtitle={
                mode === 'code'
                    ? 'Enter the six-digit code from your authenticator app'
                    : 'Enter one of the recovery codes you saved when you enrolled'
            }
            width="md"
            align="start"
        >
            <Head title="Two-factor authentication" />

            {mode === 'code' ? (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        onCodeSubmit();
                    }}
                    className="flex flex-col gap-4"
                    noValidate
                >
                    <FormErrorSummary
                        errors={codeForm.errors}
                        labels={fieldLabels}
                        onFieldFocus={focusField}
                    />

                    <OtpField
                        label="Authentication code"
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
                </form>
            ) : (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        onRecoverySubmit();
                    }}
                    className="flex flex-col gap-4"
                    noValidate
                >
                    <FormErrorSummary
                        errors={recoveryForm.errors}
                        labels={fieldLabels}
                        onFieldFocus={focusField}
                    />

                    <TextField
                        label="Recovery code"
                        name="recovery_code"
                        value={recoveryForm.form.data.recovery_code}
                        onChange={(value) => {
                            recoveryForm.form.setData('recovery_code', value);
                            recoveryForm.clearError('recovery_code');
                        }}
                        onBlur={() => recoveryForm.touch('recovery_code')}
                        error={recoveryForm.errors.recovery_code}
                        hint="Each code works once. Enter it exactly as you saved it, dash included."
                        autocomplete="off"
                        required
                        autoFocus
                    />

                    <AppButton
                        type="submit"
                        loading={recoveryForm.form.processing}
                        className="w-full"
                    >
                        Verify and sign in
                    </AppButton>
                </form>
            )}

            <div className="mt-5 flex flex-col items-center gap-2 border-t border-aq-line pt-4 text-sm">
                <button
                    type="button"
                    onClick={() => switchMode(mode === 'code' ? 'recovery' : 'code')}
                    className={linkClass}
                >
                    {mode === 'code' ? 'Use a recovery code' : 'Use your authenticator app'}
                </button>

                <Link
                    href="/login"
                    className="text-sm text-aq-ink-muted transition-colors hover:text-aq-ink"
                >
                    Back to sign in
                </Link>
            </div>
        </AuthLayout>
    );
}
