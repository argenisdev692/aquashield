/**
 * ConfirmPasswordDialog — the short password gate Fortify requires before
 * two-factor changes.
 *
 * It posts through the security hook (JSON + XSRF), not through Inertia, so
 * the surrounding tab stays mounted and the pending action can resume as soon
 * as the session records `auth.password_confirmed_at`.
 */
import { useState } from 'react';
import * as z from 'zod';
import { useZodForm } from '@/lib/forms';
import { isWebAuthnSupported } from '@/lib/webauthn';
import PasswordField from '@/Components/Forms/PasswordField';
import AppButton from '@/Components/Forms/AppButton';
import { Button } from '@/Components/ui/button';
import { ScanFace } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

const passwordGateSchema = z.object({
    password: z.string().min(1, 'Enter your current password'),
});

interface ConfirmPasswordDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: (password: string) => Promise<void>;
    /** When present and the browser supports WebAuthn, offer the passkey path. */
    onConfirmWithPasskey?: () => Promise<void>;
    onConfirmed: () => void;
}

export default function ConfirmPasswordDialog({
    open,
    onOpenChange,
    onConfirm,
    onConfirmWithPasskey,
    onConfirmed,
}: ConfirmPasswordDialogProps) {
    const [busy, setBusy] = useState(false);
    const [passkeyBusy, setPasskeyBusy] = useState(false);
    const [formError, setFormError] = useState<string | null>(null);
    const { form, errors, submit, touch, clearError } = useZodForm({
        initial: { password: '' },
        schema: passwordGateSchema,
    });

    const locked = busy || passkeyBusy;

    const close = (next: boolean): void => {
        if (locked) {
            return;
        }

        if (!next) {
            form.reset('password');
            setFormError(null);
        }

        onOpenChange(next);
    };

    const onSubmit = (): void => {
        submit(() => {
            void (async () => {
                setBusy(true);
                setFormError(null);

                try {
                    await onConfirm(form.data.password);
                    form.reset('password');
                    /*
                     * Resume before closing: the parent's `onOpenChange(false)`
                     * clears the parked action, so calling it first would drop
                     * the action the confirmation was meant to unlock.
                     */
                    onConfirmed();
                    onOpenChange(false);
                } catch (caught) {
                    setFormError(
                        caught instanceof Error && caught.message !== ''
                            ? caught.message
                            : 'The password could not be confirmed. Try again.',
                    );
                } finally {
                    setBusy(false);
                }
            })();
        });
    };

    const onUsePasskey = (): void => {
        if (onConfirmWithPasskey === undefined) {
            return;
        }

        void (async () => {
            setPasskeyBusy(true);
            setFormError(null);

            try {
                await onConfirmWithPasskey();
                form.reset('password');
                onConfirmed();
                onOpenChange(false);
            } catch (caught) {
                setFormError(
                    caught instanceof Error && caught.message !== ''
                        ? caught.message
                        : 'The passkey could not be confirmed. Use your password instead.',
                );
            } finally {
                setPasskeyBusy(false);
            }
        })();
    };

    const canUsePasskey = onConfirmWithPasskey !== undefined && isWebAuthnSupported();

    return (
        <Dialog open={open} onOpenChange={close}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirm it is you</DialogTitle>
                    <DialogDescription>
                        Security changes need a fresh confirmation. This stays valid for a short
                        time, then you will be asked again.
                    </DialogDescription>
                </DialogHeader>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        onSubmit();
                    }}
                    className="flex flex-col gap-4"
                    noValidate
                >
                    {canUsePasskey && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onUsePasskey}
                            disabled={locked}
                            className="h-11 w-full gap-2"
                        >
                            <ScanFace className="size-4" aria-hidden="true" />
                            {passkeyBusy ? 'Waiting for your passkey…' : 'Use a passkey instead'}
                        </Button>
                    )}

                    {canUsePasskey && (
                        <div className="flex items-center gap-3" aria-hidden="true">
                            <span className="h-px grow bg-aq-shell-line" />
                            <span className="text-xs text-surface-500 dark:text-surface-400">
                                or
                            </span>
                            <span className="h-px grow bg-aq-shell-line" />
                        </div>
                    )}

                    <PasswordField
                        label="Current password"
                        name="password"
                        value={form.data.password}
                        onChange={(value) => {
                            form.setData('password', value);
                            clearError('password');
                            setFormError(null);
                        }}
                        onBlur={() => touch('password')}
                        error={errors.password ?? formError ?? undefined}
                        autocomplete="current-password"
                        autoFocus={!canUsePasskey}
                        disabled={locked}
                        required
                    />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => close(false)}
                            disabled={locked}
                        >
                            Cancel
                        </Button>
                        <AppButton type="submit" loading={busy} disabled={passkeyBusy}>
                            Confirm password
                        </AppButton>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
