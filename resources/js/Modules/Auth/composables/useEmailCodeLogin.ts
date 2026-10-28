import { ref, type Ref } from 'vue';
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
 * composable only mirrors the step for the UI and never stores the code.
 */
export function useEmailCodeLogin() {
    const step: Ref<EmailCodeStep> = ref('email');
    const email: Ref<string> = ref('');
    const error: Ref<string | null> = ref(null);
    const sending: Ref<boolean> = ref(false);
    const verifying: Ref<boolean> = ref(false);

    const send = async (address: string): Promise<boolean> => {
        sending.value = true;
        error.value = null;

        try {
            const response = await post('/login/otp', { email: address });

            if (!response.ok) {
                const payload: unknown = await response.json().catch(() => null);
                error.value = errorMessage(payload, response.status);

                return false;
            }

            email.value = address;
            step.value = 'code';

            return true;
        } catch {
            error.value = 'The network request failed. Check your connection and try again.';

            return false;
        } finally {
            sending.value = false;
        }
    };

    const verify = async (code: string): Promise<EmailCodeLoginResult | null> => {
        verifying.value = true;
        error.value = null;

        try {
            const response = await post('/login/otp/verify', { code });
            const payload: unknown = await response.json().catch(() => null);

            if (!response.ok) {
                error.value = errorMessage(payload, response.status);

                return null;
            }

            if (!isLoginResult(payload)) {
                error.value = 'The server returned an unexpected response.';

                return null;
            }

            return payload;
        } catch {
            error.value = 'The network request failed. Check your connection and try again.';

            return null;
        } finally {
            verifying.value = false;
        }
    };

    const reset = (): void => {
        step.value = 'email';
        email.value = '';
        error.value = null;
    };

    return { step, email, error, sending, verifying, send, verify, reset };
}
