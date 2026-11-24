/**
 * Page-level result of the last bulk action.
 *
 * It lives above the table, not inside the bulk bar, because a successful
 * action reloads the rows and clears the selection: anything rendered inside
 * the bar unmounts before the message can be read. A success announces
 * politely; a failure is an alert and stays until the user dismisses it or a
 * later action replaces it.
 */
import { CheckCircle2, X, XCircle } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { BulkFeedback } from '@/Modules/Users/hooks/useUserBulkActions';

interface ActionFeedbackProps {
    feedback: BulkFeedback | null;
    onDismiss: () => void;
}

export default function ActionFeedback({ feedback, onDismiss }: ActionFeedbackProps) {
    if (feedback === null) {
        return null;
    }

    const isError = feedback.tone === 'error';

    return (
        <div
            role={isError ? 'alert' : 'status'}
            className={cn(
                'flex items-start gap-3 rounded-xl border px-4 py-3 text-sm',
                isError
                    ? 'border-aq-danger/30 bg-aq-danger/5 text-aq-danger-strong'
                    : 'border-aq-success/30 bg-aq-success/5 text-aq-success-strong',
            )}
        >
            {isError ? (
                <XCircle className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            ) : (
                <CheckCircle2 className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            )}

            <p className="min-w-0 flex-1 font-medium">{feedback.message}</p>

            <button
                type="button"
                onClick={onDismiss}
                aria-label="Dismiss message"
                className="grid size-6 shrink-0 place-items-center rounded-md text-current opacity-70 transition-opacity duration-150 hover:opacity-100 focus-visible:outline-2 focus-visible:outline-offset-2 motion-reduce:transition-none"
            >
                <X className="size-3.5" aria-hidden="true" />
            </button>
        </div>
    );
}
