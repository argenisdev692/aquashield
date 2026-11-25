/**
 * AvailabilityCalendarBoundary — the failed-state half of the month grid.
 *
 * `useSuspenseQuery` has no `throwOnError`: a rejected fetch is thrown during
 * render and React unwinds to the nearest error boundary. Without this, a 403,
 * a 500 or a timeout on `/availability/calendar` takes the whole page down —
 * table, weekly hours and dialogs included.
 *
 * It lives in the module, not the page, because resetting the query is the
 * module's job (it owns the key) and `QueryErrorResetBoundary` may not be
 * imported by `Pages/` or `Components/` (frontend invariant 2). The page
 * supplies only the skeleton, which is a visual decision it already owns.
 */
import { Suspense, type ReactNode } from 'react';
import { QueryErrorResetBoundary } from '@tanstack/react-query';
import ErrorBoundary from '@/Components/ErrorBoundary';
import { Button } from '@/Components/ui/button';

interface AvailabilityCalendarBoundaryProps {
    /** Skeleton shown while the month query is in flight. */
    fallback: ReactNode;
    children: ReactNode;
}

export default function AvailabilityCalendarBoundary({
    fallback,
    children,
}: AvailabilityCalendarBoundaryProps) {
    return (
        <QueryErrorResetBoundary>
            {({ reset }) => (
                <ErrorBoundary
                    onReset={reset}
                    fallback={({ reset: retry }) => (
                        <div
                            role="alert"
                            className="flex flex-col items-center gap-3 rounded-lg border border-aq-danger/30 bg-aq-danger/5 px-4 py-10 text-center"
                        >
                            <p className="text-sm font-medium text-surface-900 dark:text-surface-0">
                                The company calendar could not be loaded.
                            </p>
                            <p className="max-w-md text-xs text-surface-600 dark:text-surface-300">
                                The weekly hours and the list below still work. Retrying asks the
                                server for this month again.
                            </p>
                            <Button type="button" variant="outline" size="sm" onClick={retry}>
                                Try again
                            </Button>
                        </div>
                    )}
                >
                    <Suspense fallback={fallback}>{children}</Suspense>
                </ErrorBoundary>
            )}
        </QueryErrorResetBoundary>
    );
}
