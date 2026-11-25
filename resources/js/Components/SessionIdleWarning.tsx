/**
 * SessionIdleWarning — idle-session dialog.
 *
 * Mounted once by AppLayout for every authenticated page. The timer and the
 * transport live in `useIdleSession` (Modules/Auth/hooks); this file only
 * renders the decision.
 *
 * A native `<div role="alertdialog">` used to sit here. It looked right and was
 * unusable: no accessible name, no focus move, no trap. A keyboard user kept
 * focus on the page behind a pointer-blocking overlay and could not reach
 * "Keep working", which is the only alternative to being signed out. This is
 * now the project's own Base UI Dialog (§6): focus moves into the popup on
 * open, is trapped while it is open, and `DialogTitle`/`DialogDescription`
 * become the `aria-labelledby`/`aria-describedby` pair. `role="alertdialog"`
 * is passed through because the message is an interruption, not a task.
 *
 * Dismissal stays disabled on purpose: the two exits are "keep" and "sign out",
 * and doing nothing ends in the second one. Escape and outside presses are
 * ignored rather than closing the dialog, so an abandoned tab always signs out
 * after the grace period.
 */
import { AlertTriangle } from 'lucide-react';
import { useIdleSession } from '@/Modules/Auth/hooks/useIdleSession';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';

interface SessionIdleWarningProps {
    warnAfterSeconds?: number;
    graceSeconds?: number;
    keepAliveEndpoint?: string;
    logoutEndpoint?: string;
}

export default function SessionIdleWarning({
    warnAfterSeconds = 300,
    graceSeconds = 60,
    keepAliveEndpoint = '/auth/keep-alive',
    logoutEndpoint = '/logout',
}: SessionIdleWarningProps) {
    const { visible, remainingSeconds, keeping, keepSession, logout } = useIdleSession(
        warnAfterSeconds,
        graceSeconds,
        keepAliveEndpoint,
        logoutEndpoint,
    );

    const formattedRemaining = `${Math.floor(remainingSeconds / 60)}:${String(remainingSeconds % 60).padStart(2, '0')}`;

    return (
        <Dialog open={visible} onOpenChange={() => undefined} disablePointerDismissal>
            <DialogContent
                role="alertdialog"
                aria-modal="true"
                showCloseButton={false}
                overlayClassName="bg-aq-overlay/50 backdrop-blur-sm"
                className="z-[60] sm:max-w-md"
            >
                <DialogHeader>
                    <div className="flex items-start gap-3">
                        <AlertTriangle
                            className="mt-0.5 size-5 shrink-0 text-destructive"
                            aria-hidden="true"
                        />
                        <div className="flex flex-col gap-2">
                            <DialogTitle className="text-sm font-semibold">
                                Your session will expire soon
                            </DialogTitle>
                            <DialogDescription className="text-xs">
                                You&apos;ll be logged out automatically due to inactivity. Signing
                                out in {formattedRemaining} unless you keep the session alive.
                            </DialogDescription>
                        </div>
                    </div>
                </DialogHeader>

                <div className="flex items-center justify-between gap-2 pt-2">
                    <Button onClick={() => void keepSession()} disabled={keeping}>
                        {keeping ? 'Keeping alive…' : 'Keep working'}
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => {
                            logout();
                        }}
                    >
                        Sign out
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
