import { useState } from 'react';
import { requestJson } from '@/lib/http';

/**
 * Client side of the enrolment nudge.
 *
 * The server decides whether the prompt is due (`passkeyPrompt`); this hook only
 * performs the dismissal. A failed write leaves the banner in place on purpose:
 * silently hiding it would tell the user the decision was recorded when it was
 * not.
 */
export function usePasskeyPrompt() {
    const [visible, setVisible] = useState(true);
    const [pending, setPending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const dismiss = async (): Promise<void> => {
        setPending(true);
        setError(null);

        try {
            await requestJson('/settings/passkey-prompt/dismiss', { method: 'POST' });
            setVisible(false);
        } catch {
            setError('The prompt could not be dismissed. Try again.');
        } finally {
            setPending(false);
        }
    };

    return { visible, pending, error, dismiss };
}
