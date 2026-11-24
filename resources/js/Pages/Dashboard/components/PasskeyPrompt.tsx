/**
 * Dashboard nudge for a user who has no passkey.
 *
 * A prompt, not a gate: the FIDO rubric disqualifies a modal that forces passkey
 * creation before access, and the copy names the benefit instead of the
 * mechanism. "Not now" is a real choice and is persisted server-side.
 */
import { Link } from '@inertiajs/react';
import { ScanFace, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';

interface PasskeyPromptProps {
    pending: boolean;
    error: string | null;
    onDismiss: () => void;
}

export default function PasskeyPrompt({ pending, error, onDismiss }: PasskeyPromptProps) {
    return (
        <aside
            aria-labelledby="passkey-prompt-title"
            className="flex flex-col gap-4 rounded-xl border border-aq-accent/30 bg-aq-accent/5 p-4 sm:flex-row sm:items-center"
        >
            <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-aq-accent/15 text-aq-accent">
                <ScanFace className="size-5" aria-hidden="true" />
            </span>

            <div className="min-w-0 flex-1">
                <p
                    id="passkey-prompt-title"
                    className="text-sm font-semibold text-surface-900 dark:text-surface-0"
                >
                    Sign in without typing your password
                </p>
                <p className="mt-0.5 text-sm text-surface-600 dark:text-surface-300">
                    A passkey uses your fingerprint, face or device PIN. It cannot be phished
                    because it never leaves your device.
                </p>

                {error !== null && (
                    <p role="alert" className="mt-2 text-sm font-medium text-aq-danger-strong">
                        {error}
                    </p>
                )}
            </div>

            <div className="flex shrink-0 items-center gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onDismiss}
                    disabled={pending}
                >
                    <X className="mr-1.5 size-3.5" aria-hidden="true" />
                    Not now
                </Button>

                <Button
                    render={<Link href="/settings/profile#security" />}
                    nativeButton={false}
                    size="sm"
                    className="h-9 gap-2 px-3"
                >
                    <ScanFace className="size-3.5" aria-hidden="true" />
                    Add a passkey
                </Button>
            </div>
        </aside>
    );
}
