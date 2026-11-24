import { useState } from 'react';
import { xsrfToken } from '@/lib/http';

export type EmailCodeStep = 'email' | 'code';

export interface EmailCodeLoginResult {
    redirect: string;
    two_factor: boolean;
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const errorMessage = (payload: unknown, status: number): string => {
    if (isRecord(payload)) {
        const errors = payload.errors;

        if (isRecord(errors)) {
            for (const value of Object.values(errors)) {
                if (Array.isArray(value) && typeof value[0] === 'string') {
                    return value[0];
                }
            }
        }

        if (typeof payload.message === 'string' && payload.message !== '') {
            return payload.message;
        }
    }

    return status === 429
        ? 'Too many attempts. Wait a minute and try again.'
        : 'The sign-in code could not be processed. Try again.';
};

const isLoginResult = (value: unknown): value is EmailCodeLoginResult =>
    isRecord(value) && typeof value.redirect === 'string' && typeof value.two_factor === 'boolean';

async function post(url: string, body: Record<string, string>): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });
}

/**
 * Email-code login for the login page tabs and the dedicated OTP page.
 *
 * The server keeps the address in the session between send and verify; this
 * hook only mirrors the step for the UI and never stores the code.
 */
export function useEmailCodeLogin() {
    const [step, setStep] = useState<EmailCodeStep>('email');
    const [email, setEmail] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [sending, setSending] = useState(false);
    const [verifying, setVerifying] = useState(false);

    const send = async (address: string): Promise<boolean> => {
        setSending(true);
        setError(null);

        try {
            const response = await post('/login/otp', { email: address });

            if (!response.ok) {
                const payload: unknown = await response.json().catch(() => null);
                setError(errorMessage(payload, response.status));

                return false;
            }

            setEmail(address);
            setStep('code');

            return true;
        } catch {
            setError('The network request failed. Check your connection and try again.');

            return false;
        } finally {
            setSending(false);
        }
    };

    const verify = async (code: string): Promise<EmailCodeLoginResult | null> => {
        setVerifying(true);
        setError(null);

        try {
            const response = await post('/login/otp/verify', { code });
            const payload: unknown = await response.json().catch(() => null);

            if (!response.ok) {
                setError(errorMessage(payload, response.status));

                return null;
            }

            if (!isLoginResult(payload)) {
                setError('The server returned an unexpected response.');

                return null;
            }

            return payload;
        } catch {
            setError('The network request failed. Check your connection and try again.');

            return null;
        } finally {
            setVerifying(false);
        }
    };

    const reset = (): void => {
        setStep('email');
        setEmail('');
        setError(null);
    };

    return { step, email, error, sending, verifying, send, verify, reset };
}
