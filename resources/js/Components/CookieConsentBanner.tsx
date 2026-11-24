/**
 * CookieConsentBanner — GDPR-style cookie notice at the bottom.
 *
 * Ported from the Vue CookieConsentBanner. The server owns the decision:
 * `cookieConsent` is `null` unless this visitor still owes an answer, so
 * "is the banner showing" is a fact the backend already sent rather than a
 * guess made here. The accept call goes through Inertia's `useHttp` — not a
 * page visit, and not the raw network client the architecture suite bars from
 * components.
 */
import { useState } from 'react';
import { useHttp, usePage } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';

interface CookieConsentData {
    message: string;
    acceptLabel: string;
    endpoint: string;
}

export default function CookieConsentBanner() {
    const page = usePage<{
        cookieConsent?: CookieConsentData | null;
    }>();

    const consent = page.props.cookieConsent ?? null;
    const [settled, setSettled] = useState(false);
    const [failed, setFailed] = useState(false);

    const http = useHttp<Record<string, never>, { consented: boolean }>({});

    const visible = consent !== null && !settled;

    const accept = (): void => {
        const pending = consent;

        if (pending === null || http.processing) {
            return;
        }

        setFailed(false);

        http.post(pending.endpoint, {
            onSuccess: () => {
                setSettled(true);
            },
            onHttpException: () => {
                setFailed(true);
            },
            onNetworkError: () => {
                setFailed(true);
            },
        }).catch(() => {
            // Both handlers above already put the failure in front of the user.
            // This catch exists only so the rejection does not surface as an
            // unhandled promise rejection — `useHttp` rethrows on every non-2xx.
        });
    };

    if (!visible || consent === null) {
        return null;
    }

    return (
        <div
            data-cookie-consent
            className="fixed inset-x-0 bottom-0 z-50 px-4 pb-4 sm:px-6 sm:pb-6"
        >
            <div className="mx-auto flex max-w-3xl flex-col gap-3 rounded-xl border border-aq-line bg-aq-base/95 p-4 shadow-lg backdrop-blur-md sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 className="text-sm font-medium text-aq-ink">We use cookies</h3>
                    <p className="mt-1 text-xs text-aq-ink-soft">{consent.message}</p>
                    {failed && (
                        <p role="status" className="mt-1 text-xs text-aq-ink-muted">
                            We could not save your choice. Please try again.
                        </p>
                    )}
                </div>

                <div className="flex shrink-0 justify-end">
                    <Button
                        size="sm"
                        disabled={http.processing}
                        onClick={accept}
                        className="bg-aq-accent text-aq-void transition-colors duration-150 hover:bg-aq-accent-soft motion-reduce:transition-none"
                    >
                        {consent.acceptLabel}
                    </Button>
                </div>
            </div>
        </div>
    );
}
