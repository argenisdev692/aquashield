/**
 * Dashboard nudge for a user who has no passkey.
 *
 * A prompt, not a gate: the FIDO rubric disqualifies a modal that forces passkey
 * creation before access. The dashboard is an operations screen, so the nudge
 * is a single quiet strip — the action stays, the wall of copy does not.
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
            className="flex flex-wrap items-center gap-x-3 gap-y-2 rounded-lg border border-aq-content-line bg-transparent px-3 py-2"
        >
            <span className="grid size-7 shrink-0 place-items-center rounded-md bg-aq-accent/10 text-aq-accent">
                <ScanFace className="size-4" aria-hidden="true" />
            </span>

            <div className="min-w-0 flex-1">
                <p
                    id="passkey-prompt-title"
                    className="text-xs leading-relaxed text-surface-600 dark:text-surface-300"
                >
                    <span className="font-semibold text-surface-900 dark:text-surface-0">
                        Sign in without a password.
                    </span>{' '}
                    A passkey is phishing-resistant and never leaves your device.
                </p>

                {error !== null && (
                    <p role="alert" className="mt-1 text-xs font-medium text-aq-danger-strong">
                        {error}
                    </p>
                )}
            </div>

            <div className="flex w-full shrink-0 items-center justify-end gap-1 sm:w-auto">
                <Button
                    render={<Link href="/settings/profile#security" />}
                    nativeButton={false}
                    variant="ghost"
                    size="xs"
                    className="gap-1.5 text-aq-accent-text transition-colors duration-150 hover:bg-aq-accent/10 dark:text-aq-accent"
                >
                    <ScanFace className="size-3.5" aria-hidden="true" />
                    Add a passkey
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    size="icon-xs"
                    onClick={onDismiss}
                    disabled={pending}
                    aria-label="Dismiss passkey suggestion"
                >
                    <X className="size-3.5" aria-hidden="true" />
                </Button>
            </div>
        </aside>
    );
}
