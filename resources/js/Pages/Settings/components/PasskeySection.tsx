/**
 * PasskeySection — enrolment and removal on the account screen.
 *
 * The server owns the credential; this panel runs the package's ceremonies and
 * parks any action that hits `password.confirm` until the gate succeeds. Adding
 * a passkey is never blocked: the password remains, so a second device is a
 * convenience rather than a lockout risk.
 */
import { useRef, useState } from 'react';
import * as z from 'zod';
import { useZodForm } from '@/lib/forms';
import { PasskeyError, toPasskeyError } from '@/Modules/Auth/hooks/usePasskeys';
import SettingsCard from './SettingsCard';
import ConfirmPasswordDialog from './ConfirmPasswordDialog';
import TextField from '@/Components/Forms/TextField';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import AppButton from '@/Components/Forms/AppButton';
import { Button } from '@/Components/ui/button';
import { Tag } from '@/Components/ui/tag';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { AlertTriangle, CheckCircle2, Plus, ScanFace, Trash2 } from 'lucide-react';

interface PasskeySectionProps {
    passkeys: App.Data.Auth.PasskeyData[];
    supported: boolean;
    adding: boolean;
    removingId: number | null;
    onAdd: (name: string) => Promise<void>;
    onRemove: (id: number) => Promise<void>;
    onConfirmPassword: (password: string) => Promise<void>;
    onConfirmWithPasskey: () => Promise<void>;
}

type PendingAction = () => Promise<void>;

const passkeyNameSchema = z.object({
    name: z
        .string()
        .trim()
        .min(2, 'Give this passkey a name')
        .max(255, 'Keep the name under 255 characters'),
});

const formatDate = (value: string | null): string | null => {
    if (value === null) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
};

export default function PasskeySection({
    passkeys,
    supported,
    adding,
    removingId,
    onAdd,
    onRemove,
    onConfirmPassword,
    onConfirmWithPasskey,
}: PasskeySectionProps) {
    const pendingAction = useRef<PendingAction | null>(null);

    const [addOpen, setAddOpen] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<App.Data.Auth.PasskeyData | null>(null);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [actionError, setActionError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    const nameForm = useZodForm({
        initial: { name: '' },
        schema: passkeyNameSchema,
    });

    const run = async (action: PendingAction): Promise<void> => {
        setActionError(null);
        setNotice(null);

        try {
            await action();
        } catch (caught) {
            const error = caught instanceof PasskeyError ? caught : toPasskeyError(caught);

            if (error.code === 'cancelled') {
                // Dismissing the authenticator prompt is a routine "not now",
                // not a failure worth a red alert.
                return;
            }

            if (error.code === 'password-required') {
                // Close the mutation dialog first: two stacked dialogs leave
                // focus on a panel the gate is about to cover.
                setAddOpen(false);
                setDeleteTarget(null);
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

    const onSubmitName = (): void => {
        // Capture the name before `submit` runs: the parked action can execute
        // after the dialog closed and reset the form.
        const name = nameForm.form.data.name;

        nameForm.submit(() => {
            void run(async () => {
                await onAdd(name);
                nameForm.form.reset('name');
                setAddOpen(false);
                setNotice('Passkey added. You can now use it to sign in.');
            });
        });
    };

    const busy = adding || removingId !== null;

    return (
        <div className="flex flex-col gap-4">
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

            <SettingsCard
                title="Passkeys"
                description="A passkey signs you in with your fingerprint, face or device PIN. It cannot be phished."
                icon={<ScanFace className="size-4" aria-hidden="true" />}
                action={
                    <Tag variant={passkeys.length > 0 ? 'success' : 'outline'}>
                        {passkeys.length === 0
                            ? 'None'
                            : `${passkeys.length} passkey${passkeys.length === 1 ? '' : 's'}`}
                    </Tag>
                }
            >
                <div className="flex flex-col gap-4">
                    {!supported ? (
                        <p className="text-sm text-surface-700 dark:text-surface-200">
                            This browser cannot create or use passkeys. Open AquaShield over HTTPS
                            in a current browser, or keep signing in with your password.
                        </p>
                    ) : (
                        <>
                            {passkeys.length === 0 ? (
                                <p className="text-sm text-surface-700 dark:text-surface-200">
                                    No passkey yet. Add one to skip the password on this device and
                                    on any device where you sign in with the same account.
                                </p>
                            ) : (
                                <ul className="flex flex-col gap-2">
                                    {passkeys.map((passkey) => {
                                        const created = formatDate(passkey.created_at);
                                        const used = formatDate(passkey.last_used_at);

                                        return (
                                            <li
                                                key={passkey.id}
                                                className="flex flex-col gap-3 rounded-xl border border-aq-shell-line p-3 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium text-surface-900 dark:text-surface-0">
                                                        {passkey.name}
                                                    </p>
                                                    <p className="mt-0.5 text-xs text-surface-500 dark:text-surface-300">
                                                        {passkey.authenticator ?? 'Passkey'}
                                                        {created !== null
                                                            ? ` · Added ${created}`
                                                            : ''}
                                                        {used !== null
                                                            ? ` · Last used ${used}`
                                                            : ' · Never used'}
                                                    </p>
                                                </div>

                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={busy}
                                                    onClick={() => setDeleteTarget(passkey)}
                                                >
                                                    <Trash2
                                                        className="mr-1.5 size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    Remove
                                                </Button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}

                            <div>
                                <AppButton
                                    type="button"
                                    loading={adding}
                                    onClick={() => setAddOpen(true)}
                                >
                                    <Plus className="mr-2 size-4" aria-hidden="true" />
                                    Add a passkey
                                </AppButton>
                            </div>
                        </>
                    )}
                </div>
            </SettingsCard>

            <Dialog
                open={addOpen}
                onOpenChange={(open) => {
                    if (!adding) {
                        setAddOpen(open);

                        if (!open) {
                            nameForm.form.reset('name');
                            setActionError(null);
                        }
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Add a passkey</DialogTitle>
                        <DialogDescription>
                            Your browser asks for your fingerprint, face or device PIN. Only a
                            public key is stored on the server.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            onSubmitName();
                        }}
                        className="flex flex-col gap-4"
                        noValidate
                    >
                        <FormErrorSummary
                            errors={nameForm.errors}
                            labels={{ name: 'Passkey name' }}
                        />

                        <TextField
                            label="Passkey name"
                            name="name"
                            value={nameForm.form.data.name}
                            onChange={(value) => {
                                nameForm.form.setData('name', value);
                                nameForm.clearError('name');
                            }}
                            onBlur={() => nameForm.touch('name')}
                            error={nameForm.errors.name}
                            hint="Something you will recognise later, like “Work laptop”."
                            autoFocus
                            required
                        />

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setAddOpen(false)}
                                disabled={adding}
                            >
                                Cancel
                            </Button>
                            <AppButton type="submit" loading={adding}>
                                Create passkey
                            </AppButton>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open && removingId === null) {
                        setDeleteTarget(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Remove “{deleteTarget?.name}”?</DialogTitle>
                        <DialogDescription>
                            This passkey can no longer sign you in. Your password
                            {passkeys.length > 1 ? ' and your other passkeys' : ''} still work, and
                            two-factor settings are not affected.
                        </DialogDescription>
                    </DialogHeader>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteTarget(null)}
                            disabled={removingId !== null}
                        >
                            Keep it
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={removingId !== null}
                            onClick={() => {
                                const target = deleteTarget;

                                if (target === null) {
                                    return;
                                }

                                void run(async () => {
                                    await onRemove(target.id);
                                    setDeleteTarget(null);
                                    setNotice('Passkey removed.');
                                });
                            }}
                        >
                            <Trash2 className="mr-2 size-4" aria-hidden="true" />
                            Remove passkey
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
                onConfirm={onConfirmPassword}
                onConfirmWithPasskey={onConfirmWithPasskey}
                onConfirmed={onPasswordConfirmed}
            />
        </div>
    );
}
