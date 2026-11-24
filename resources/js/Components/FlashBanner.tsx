/**
 * FlashBanner — the one place a redirect-flashed status or error is shown.
 *
 * It is an inline region, not a toast stack: the repo deliberately has no
 * second notification system. A message is dismissible for the current page
 * and disappears on the next visit because the session flash is one-shot.
 */
import { useState } from 'react';
import { usePage } from '@inertiajs/react';
import { CheckCircle2, X, XCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

interface FlashBannerProps {
    className?: string;
}

interface FlashPageProps {
    [key: string]: unknown;
    flash?: {
        status?: string | null;
        error?: string | null;
    };
}

export default function FlashBanner({ className }: FlashBannerProps) {
    const { flash } = usePage<FlashPageProps>().props;
    const status = flash?.status ?? null;
    const error = flash?.error ?? null;
    const [dismissed, setDismissed] = useState<string | null>(null);

    const message = error ?? status;

    if (message === null || message === '' || dismissed === message) {
        return null;
    }

    const isError = error !== null;

    return (
        <div
            role={isError ? 'alert' : 'status'}
            className={cn(
                'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm',
                isError
                    ? 'border-aq-danger/30 bg-aq-danger/5 text-aq-danger-strong'
                    : 'border-aq-success/30 bg-aq-success/5 text-aq-success-strong',
                className,
            )}
        >
            {isError ? (
                <XCircle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            ) : (
                <CheckCircle2 className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            )}

            <p className="min-w-0 flex-1 font-medium">{message}</p>

            <button
                type="button"
                onClick={() => setDismissed(message)}
                aria-label="Dismiss message"
                className="grid size-6 shrink-0 place-items-center rounded-md text-current opacity-70 transition-opacity duration-150 hover:opacity-100 motion-reduce:transition-none"
            >
                <X className="size-3.5" aria-hidden="true" />
            </button>
        </div>
    );
}
