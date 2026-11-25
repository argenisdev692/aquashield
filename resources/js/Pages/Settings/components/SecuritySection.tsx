/**
 * SecuritySection — Fortify two-factor management on the account screen.
 *
 * The server owns the secret, confirmation and recovery codes. This panel only
 * orchestrates the Fortify endpoints and keeps the destructive paths behind a
 * Dialog. Both the QR and recovery codes sit behind `password.confirm`; when
 * that gate fires, the pending action is parked and resumed after the
 * confirmation dialog succeeds.
 */
import { useRef, useState } from 'react';
import * as z from 'zod';
import { useZodForm } from '@/lib/forms';
import { toTwoFactorError, useTwoFactorSettings } from '@/Modules/Auth/hooks/useTwoFactorSettings';
import SettingsCard from './SettingsCard';
import ConfirmPasswordDialog from './ConfirmPasswordDialog';
import OtpField from '@/Components/Forms/OtpField';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import AppButton from '@/Components/Forms/AppButton';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { Tag } from '@/Components/ui/tag';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    AlertTriangle,
    CheckCircle2,
    Copy,
    Download,
    KeyRound,
    RefreshCw,
    ScanFace,
    ShieldCheck,
    ShieldOff,
    Smartphone,
} from 'lucide-react';

interface SecuritySectionProps {
    enabled: boolean;
    confirmed: boolean;
    passkeysCount: number;
    /** Present when the account has a passkey and the browser can assert it. */
    onConfirmWithPasskey?: () => Promise<void>;
}

type PendingAction = () => Promise<void>;

const setupCodeSchema = z.object({
    code: z.string().regex(/^\d{6}$/, 'Enter the six-digit code from your authenticator app'),
});

const downloadCodes = (codes: readonly string[]): void => {
    const blob = new Blob([`${codes.join('\n')}\n`], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');

    anchor.href = url;
    anchor.download = 'aquashield-recovery-codes.txt';
    document.body.append(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
};

export default function SecuritySection({
    enabled,
    confirmed,
    passkeysCount,
    onConfirmWithPasskey,
}: SecuritySectionProps) {
    const twoFactor = useTwoFactorSettings({ enabled, confirmed });
    const pendingAction = useRef<PendingAction | null>(null);

    const [confirmOpen, setConfirmOpen] = useState(false);
    const [disableOpen, setDisableOpen] = useState(false);
    const [regenerateOpen, setRegenerateOpen] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);

    const confirmForm = useZodForm({
        initial: { code: '' },
        schema: setupCodeSchema,
    });

    const run = async (action: PendingAction): Promise<void> => {
        setActionError(null);
        setNotice(null);

        try {
            await action();
        } catch (caught) {
            const error = toTwoFactorError(caught);

            if (error.code === 'password-required') {
                pendingAction.current = action;
                setConfirmOpen(true);

                return;
            }

            setActionError(error.message);
        }
    };

    const onPasswordConfirmed = (): void => {
        const action = pendingAction.current;
        pendingAction.current = null;
        setConfirmOpen(false);

        if (action !== null) {
            void run(action);
        }
    };

    const onConfirmCode = (): void => {
        confirmForm.submit(() => {
            void run(async () => {
                try {
                    await twoFactor.confirm(confirmForm.form.data.code);
                    confirmForm.form.reset('code');
                    setNotice('Two-factor authentication confirmed.');
                } catch (caught) {
                    const error = toTwoFactorError(caught);

                    if (error.fieldErrors.code !== undefined) {
                        confirmForm.form.setError('code', error.fieldErrors.code);
                    }

                    throw caught;
                }
            });
        });
    };

    const copyRecoveryCodes = async (codes: readonly string[]): Promise<void> => {
        try {
            await navigator.clipboard.writeText(codes.join('\n'));
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            setActionError(
                'The recovery codes could not be copied. Select them and copy manually.',
            );
        }
    };

    const recoveryError =
        twoFactor.recoveryError !== null && twoFactor.recoveryError.code !== 'password-required'
            ? twoFactor.recoveryError.message
            : null;

    return (
        <div className="flex flex-col gap-6">
            {notice !== null && (
                <p
                    role="status"
                    className="flex items-start gap-2 rounded-lg border border-aq-success/30 bg-aq-success/10 px-3 py-2 text-sm text-surface-800 dark:text-surface-100"
                >
                    <CheckCircle2
                        className="mt-0.5 size-4 shrink-0 text-aq-success"
                        aria-hidden="true"
                    />
                    {notice}
                </p>
            )}

            {actionError !== null && (
                <p
                    role="alert"
                    className="flex items-start gap-2 rounded-lg border border-aq-danger/30 bg-aq-danger/10 px-3 py-2 text-sm text-aq-danger-strong"
                >
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    {actionError}
                </p>
            )}

            {/*
             * One line for the whole sign-in posture. A boolean "2FA off" is a
             * lie for an account that signs in with a passkey, which is already
             * multi-factor.
             */}
            <div className="flex flex-wrap items-center gap-2">
                <span className="text-sm font-medium text-surface-700 dark:text-surface-200">
                    Sign-in security
                </span>

                <Tag variant={passkeysCount > 0 ? 'success' : 'outline'}>
                    <ScanFace className="mr-1 size-3" aria-hidden="true" />
                    {passkeysCount > 0 ? `Passkey ×${passkeysCount}` : 'No passkey'}
                </Tag>

                <Tag variant={!enabled ? 'outline' : confirmed ? 'success' : 'warning'}>
                    <Smartphone className="mr-1 size-3" aria-hidden="true" />
                    {!enabled ? 'TOTP off' : confirmed ? 'TOTP on' : 'TOTP setup incomplete'}
                </Tag>
            </div>

            <SettingsCard
                title="Two-factor authentication"
                description="A one-time code from an authenticator app is required after your password."
                icon={<Smartphone className="size-4" aria-hidden="true" />}
                action={
                    <Tag variant={!enabled ? 'outline' : confirmed ? 'success' : 'warning'}>
                        {!enabled ? 'Off' : confirmed ? 'On' : 'Setup incomplete'}
                    </Tag>
                }
            >
                <div className="flex flex-col gap-5">
                    {!enabled && (
                        <>
                            <p className="text-sm text-surface-700 dark:text-surface-200">
                                Two-factor authentication adds a second lock to your account. Even
                                if someone learns your password, they still need the rotating code
                                from your phone.
                            </p>

                            <ul className="grid gap-2 text-sm text-surface-600 sm:grid-cols-2 dark:text-surface-300">
                                <li className="flex items-start gap-2">
                                    <ShieldCheck
                                        className="mt-0.5 size-4 shrink-0 text-aq-success"
                                        aria-hidden="true"
                                    />
                                    Works with 1Password, Authy, Google Authenticator and similar
                                    apps.
                                </li>
                                <li className="flex items-start gap-2">
                                    <KeyRound
                                        className="mt-0.5 size-4 shrink-0 text-aq-accent"
                                        aria-hidden="true"
                                    />
                                    Recovery codes keep you in if the phone is lost.
                                </li>
                            </ul>

                            <div>
                                <AppButton
                                    type="button"
                                    shine
                                    loading={twoFactor.actionPending}
                                    onClick={() =>
                                        void run(async () => {
                                            await twoFactor.enable();
                                            setNotice(
                                                'Two-factor enabled. Scan the QR code, then confirm the code.',
                                            );
                                        })
                                    }
                                >
                                    <ShieldCheck className="mr-2 size-4" aria-hidden="true" />
                                    Enable two-factor authentication
                                </AppButton>
                            </div>
                        </>
                    )}

                    {enabled && !confirmed && (
                        <div className="grid gap-6 lg:grid-cols-[13rem_minmax(0,1fr)]">
                            <div className="flex flex-col items-center gap-3 rounded-xl border border-aq-shell-line bg-surface-50 p-4 dark:bg-surface-950/40">
                                {twoFactor.qrLoading ? (
                                    <Skeleton className="size-44 rounded-lg" />
                                ) : twoFactor.qrDataUrl !== null ? (
                                    <img
                                        src={twoFactor.qrDataUrl}
                                        alt="QR code for your authenticator app"
                                        className="size-44 rounded-lg bg-surface-0 p-2"
                                    />
                                ) : (
                                    <div className="flex size-44 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-surface-300 p-3 text-center dark:border-surface-700">
                                        <ShieldOff
                                            className="size-5 text-surface-500"
                                            aria-hidden="true"
                                        />
                                        <p className="text-xs text-surface-600 dark:text-surface-300">
                                            {twoFactor.qrError?.message ??
                                                'The QR code could not be loaded.'}
                                        </p>

                                        {twoFactor.qrError?.code === 'password-required' ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    pendingAction.current = () =>
                                                        twoFactor.refreshQr();
                                                    setConfirmOpen(true);
                                                }}
                                            >
                                                Confirm password
                                            </Button>
                                        ) : (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    void run(() => twoFactor.refreshQr())
                                                }
                                            >
                                                <RefreshCw
                                                    className="mr-1.5 size-3"
                                                    aria-hidden="true"
                                                />
                                                Retry
                                            </Button>
                                        )}
                                    </div>
                                )}

                                <p className="text-center text-xs text-surface-600 dark:text-surface-300">
                                    Scan with your authenticator app.
                                </p>
                            </div>

                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    onConfirmCode();
                                }}
                                className="flex flex-col gap-4"
                                noValidate
                            >
                                <FormErrorSummary
                                    errors={confirmForm.errors}
                                    labels={{ code: 'Authentication code' }}
                                />

                                <OtpField
                                    label="Authentication code"
                                    name="code"
                                    value={confirmForm.form.data.code}
                                    onChange={(value) => {
                                        confirmForm.form.setData('code', value);
                                        confirmForm.clearError('code');
                                    }}
                                    onBlur={() => confirmForm.touch('code')}
                                    error={confirmForm.errors.code}
                                    hint="Enter the six-digit code currently shown in the app."
                                    required
                                />

                                <div className="flex flex-wrap gap-2">
                                    <AppButton
                                        type="submit"
                                        loading={twoFactor.actionPending}
                                        disabled={twoFactor.actionPending}
                                    >
                                        Verify and turn on
                                    </AppButton>

                                    <Button
                                        type="button"
                                        variant="destructive"
                                        disabled={twoFactor.actionPending}
                                        onClick={() => setDisableOpen(true)}
                                    >
                                        Cancel setup
                                    </Button>
                                </div>
                            </form>
                        </div>
                    )}

                    {enabled && confirmed && (
                        <>
                            <p className="text-sm text-surface-700 dark:text-surface-200">
                                Two-factor authentication is active. Keep the recovery codes
                                somewhere safe: they are the only way back in if you lose the
                                authenticator.
                            </p>

                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={twoFactor.actionPending}
                                    onClick={() => setDisableOpen(true)}
                                >
                                    <ShieldOff className="mr-2 size-4" aria-hidden="true" />
                                    Disable two-factor
                                </Button>
                            </div>
                        </>
                    )}
                </div>
            </SettingsCard>

            {enabled && confirmed && (
                <SettingsCard
                    title="Recovery codes"
                    description="Each code works once. Store them offline, not in the same place as your password."
                    icon={<KeyRound className="size-4" aria-hidden="true" />}
                    action={
                        twoFactor.recoveryLoaded ? (
                            <Tag variant="outline">{twoFactor.recoveryCodes.length} codes</Tag>
                        ) : undefined
                    }
                >
                    <div className="flex flex-col gap-4">
                        {recoveryError !== null && (
                            <p role="alert" className="text-sm text-aq-danger-strong">
                                {recoveryError}
                            </p>
                        )}

                        {!twoFactor.recoveryLoaded && (
                            <div className="flex flex-col items-start gap-3">
                                <p className="text-sm text-surface-700 dark:text-surface-200">
                                    Recovery codes are hidden until you ask for them. Viewing them
                                    requires a fresh password confirmation.
                                </p>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={twoFactor.recoveryLoading}
                                        onClick={() =>
                                            void run(() => twoFactor.loadRecoveryCodes())
                                        }
                                    >
                                        <KeyRound className="mr-2 size-4" aria-hidden="true" />
                                        {twoFactor.recoveryLoading
                                            ? 'Loading codes…'
                                            : 'View recovery codes'}
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        disabled={twoFactor.actionPending}
                                        onClick={() => setRegenerateOpen(true)}
                                    >
                                        <RefreshCw className="mr-2 size-4" aria-hidden="true" />
                                        Regenerate
                                    </Button>
                                </div>
                            </div>
                        )}

                        {twoFactor.recoveryLoaded && (
                            <>
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    {twoFactor.recoveryCodes.map((code) => (
                                        <code
                                            key={code}
                                            className="rounded-lg border border-aq-shell-line bg-surface-50 px-2 py-1.5 text-center font-mono text-xs text-surface-800 dark:bg-surface-950/50 dark:text-surface-100"
                                        >
                                            {code}
                                        </code>
                                    ))}
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            void copyRecoveryCodes(twoFactor.recoveryCodes)
                                        }
                                    >
                                        <Copy className="mr-2 size-4" aria-hidden="true" />
                                        {copied ? 'Copied' : 'Copy all'}
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => downloadCodes(twoFactor.recoveryCodes)}
                                    >
                                        <Download className="mr-2 size-4" aria-hidden="true" />
                                        Download .txt
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        disabled={twoFactor.actionPending}
                                        onClick={() => setRegenerateOpen(true)}
                                    >
                                        <RefreshCw className="mr-2 size-4" aria-hidden="true" />
                                        Regenerate
                                    </Button>
                                </div>
                            </>
                        )}
                    </div>
                </SettingsCard>
            )}

            <Dialog
                open={disableOpen}
                onOpenChange={(open) => {
                    if (!twoFactor.actionPending) {
                        setDisableOpen(open);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Turn off two-factor authentication?</DialogTitle>
                        <DialogDescription>
                            Your account will rely on the password alone. Recovery codes stop
                            working immediately.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDisableOpen(false)}
                            disabled={twoFactor.actionPending}
                        >
                            Keep it on
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={twoFactor.actionPending}
                            onClick={() => {
                                setDisableOpen(false);
                                void run(async () => {
                                    await twoFactor.disable();
                                    setNotice('Two-factor authentication disabled.');
                                });
                            }}
                        >
                            <ShieldOff className="mr-2 size-4" aria-hidden="true" />
                            Turn off
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={regenerateOpen}
                onOpenChange={(open) => {
                    if (!twoFactor.actionPending) {
                        setRegenerateOpen(open);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Regenerate recovery codes?</DialogTitle>
                        <DialogDescription>
                            Every existing recovery code stops working. Any device that is signed in
                            stays signed in.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setRegenerateOpen(false)}
                            disabled={twoFactor.actionPending}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            disabled={twoFactor.actionPending}
                            onClick={() => {
                                setRegenerateOpen(false);
                                void run(async () => {
                                    await twoFactor.regenerateRecoveryCodes();
                                    setNotice('New recovery codes generated. Store them safely.');
                                });
                            }}
                        >
                            <RefreshCw className="mr-2 size-4" aria-hidden="true" />
                            Regenerate codes
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmPasswordDialog
                open={confirmOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        pendingAction.current = null;
                    }

                    setConfirmOpen(open);
                }}
                onConfirm={twoFactor.confirmPassword}
                onConfirmWithPasskey={onConfirmWithPasskey}
                onConfirmed={onPasswordConfirmed}
            />
        </div>
    );
}
