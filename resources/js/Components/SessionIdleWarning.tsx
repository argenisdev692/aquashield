/**
 * SessionIdleWarning — idle-session dialog.
 *
 * Mounted once by AppLayout for every authenticated page. The timer and the
 * transport live in `useIdleSession` (Modules/Auth/hooks); this file only
 * renders the decision. Closing is intentionally disabled: the two exits are
 * "keep" and "sign out", and doing nothing ends in the second one.
 */
import { AlertTriangle } from 'lucide-react';
import { useIdleSession } from '@/Modules/Auth/hooks/useIdleSession';
import { Button } from '@/Components/ui/button';

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

    if (!visible) return null;

    return (
        <div
            className="fixed inset-0 z-[60] flex items-center justify-center bg-aq-overlay/50 backdrop-blur-sm"
            role="alertdialog"
            aria-modal="true"
        >
            <div className="bg-surface-0 rounded-xl p-6 shadow-2xl dark:bg-surface-900 border border-aq-shell-line">
                <div className="flex items-start gap-3">
                    <AlertTriangle className="h-5 w-5 text-destructive mt-0.5" aria-hidden="true" />
                    <div>
                        <h3 className="text-sm font-semibold text-surface-900 dark:text-surface-0">
                            Your session will expire soon
                        </h3>
                        <p className="text-xs text-surface-600 dark:text-surface-400 mt-1">
                            You&apos;ll be logged out automatically due to inactivity.
                        </p>
                    </div>
                </div>
                <div className="mt-6 flex items-center justify-between">
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
            </div>
        </div>
    );
}
