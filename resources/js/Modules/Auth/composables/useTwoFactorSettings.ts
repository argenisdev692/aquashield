import { ref, type Ref } from 'vue';
import { xsrfToken } from '@/lib/http';

/**
 * Server state for the two-factor panel.
 *
 * Fortify owns every endpoint and the secret/confirmation/recovery codes live
 * on the server. This composable turns those JSON endpoints into renderable
 * state, and it is the only place in the settings screen that performs network
 * I/O (the page stays a declaration of what is shown).
 *
 * The `password.confirm` middleware answers a JSON request with `423 Locked`.
 * That is a normal branch of this UI — the panel parks the pending action and
 * resumes it after the password dialog succeeds — so it is modelled as an error
 * code rather than collapsed into a generic message.
 */

export type TwoFactorErrorCode = 'password-required' | 'failed';

export interface TwoFactorError {
    code: TwoFactorErrorCode;
    message: string;
    fieldErrors: Record<string, string>;
}

export class TwoFactorRequestError extends Error {
    public readonly code: TwoFactorErrorCode;
    public readonly fieldErrors: Record<string, string>;

    constructor(
        code: TwoFactorErrorCode,
        message: string,
        fieldErrors: Record<string, string> = {},
    ) {
        super(message);
        this.name = 'TwoFactorRequestError';
        this.code = code;
        this.fieldErrors = fieldErrors;
    }
}

export interface TwoFactorSettings {
    qrDataUrl: Ref<string | null>;
    qrLoading: Ref<boolean>;
    qrError: Ref<TwoFactorError | null>;
    recoveryCodes: Ref<string[]>;
    recoveryLoaded: Ref<boolean>;
    recoveryLoading: Ref<boolean>;
    recoveryError: Ref<TwoFactorError | null>;
    actionPending: Ref<boolean>;
    loadQr: (signal?: AbortSignal) => Promise<void>;
    refreshQr: () => Promise<void>;
    loadRecoveryCodes: () => Promise<void>;
    enable: () => Promise<void>;
    confirm: (code: string) => Promise<void>;
    disable: () => Promise<void>;
    regenerateRecoveryCodes: () => Promise<void>;
    confirmPassword: (password: string) => Promise<void>;
    reset: () => void;
}

const PASSWORD_REQUIRED_MESSAGE = 'Confirm your password to continue.';

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const readJson = async (response: Response): Promise<unknown> => {
    const text = await response.text();

    if (text.trim() === '') {
        return null;
    }

    try {
        return JSON.parse(text) as unknown;
    } catch {
        return null;
    }
};

const readFieldErrors = (payload: unknown): Record<string, string> => {
    if (!isRecord(payload) || !isRecord(payload.errors)) {
        return {};
    }

    const result: Record<string, string> = {};

    for (const [field, value] of Object.entries(payload.errors)) {
        if (typeof value === 'string') {
            result[field] = value;
            continue;
        }

        if (Array.isArray(value) && typeof value[0] === 'string') {
            result[field] = value[0];
        }
    }

    return result;
};

const readMessage = (payload: unknown, fallback: string): string => {
    if (isRecord(payload) && typeof payload.message === 'string' && payload.message !== '') {
        return payload.message;
    }

    return fallback;
};

const decodeRecoveryCodes = (payload: unknown): string[] => {
    if (!Array.isArray(payload)) {
        return [];
    }

    const codes = payload.filter((code): code is string => typeof code === 'string');

    return codes.length === payload.length ? codes : [];
};

const isQrPayload = (payload: unknown): payload is { svg: string } =>
    isRecord(payload) && typeof payload.svg === 'string';

const toDataUrl = (svg: string): string => {
    const bytes = new TextEncoder().encode(svg);
    let binary = '';

    for (const byte of bytes) {
        binary += String.fromCharCode(byte);
    }

    return `data:image/svg+xml;base64,${btoa(binary)}`;
};

const request = async (
    url: string,
    method: 'GET' | 'POST' | 'DELETE',
    body?: unknown,
): Promise<unknown> => {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (method !== 'GET') {
        headers['X-XSRF-TOKEN'] = xsrfToken();
    }

    if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
    });

    /*
     * A redirected XHR means `password.confirm` sent us to the confirmation
     * screen instead of answering JSON. Same gate, different transport.
     */
    if (response.status === 423 || response.redirected) {
        throw new TwoFactorRequestError('password-required', PASSWORD_REQUIRED_MESSAGE);
    }

    const payload = await readJson(response);

    if (!response.ok) {
        throw new TwoFactorRequestError(
            'failed',
            readMessage(payload, 'That action could not be completed. Try again.'),
            readFieldErrors(payload),
        );
    }

    return payload;
};

export const toTwoFactorError = (caught: unknown): TwoFactorError => {
    if (caught instanceof TwoFactorRequestError) {
        return {
            code: caught.code,
            message: caught.message,
            fieldErrors: caught.fieldErrors,
        };
    }

    return {
        code: 'failed',
        message: 'Something went wrong. Reload the page and try again.',
        fieldErrors: {},
    };
};

export function useTwoFactorSettings(): TwoFactorSettings {
    const qrDataUrl = ref<string | null>(null);
    const qrLoading = ref<boolean>(false);
    const qrError = ref<TwoFactorError | null>(null);

    const recoveryCodes = ref<string[]>([]);
    const recoveryLoaded = ref<boolean>(false);
    const recoveryLoading = ref<boolean>(false);
    const recoveryError = ref<TwoFactorError | null>(null);

    const actionPending = ref<boolean>(false);

    const loadQr = async (signal?: AbortSignal): Promise<void> => {
        qrLoading.value = true;
        qrError.value = null;

        try {
            const response = await fetch('/user/two-factor-qr-code', {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal,
            });

            if (response.status === 423 || response.redirected) {
                throw new TwoFactorRequestError('password-required', PASSWORD_REQUIRED_MESSAGE);
            }

            const payload = await readJson(response);

            if (!response.ok) {
                throw new TwoFactorRequestError(
                    'failed',
                    readMessage(payload, 'The QR code could not be loaded.'),
                );
            }

            if (!isQrPayload(payload)) {
                throw new TwoFactorRequestError('failed', 'The QR code response was not valid.');
            }

            qrDataUrl.value = toDataUrl(payload.svg);
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            qrDataUrl.value = null;
            qrError.value = toTwoFactorError(error);
        } finally {
            qrLoading.value = false;
        }
    };

    const refreshQr = async (): Promise<void> => {
        await loadQr();
    };

    const loadRecoveryCodes = async (): Promise<void> => {
        recoveryLoading.value = true;
        recoveryError.value = null;

        try {
            const payload = await request('/user/two-factor-recovery-codes', 'GET');
            const codes = decodeRecoveryCodes(payload);

            if (codes.length === 0) {
                throw new TwoFactorRequestError('failed', 'No recovery codes were returned.');
            }

            recoveryCodes.value = codes;
            recoveryLoaded.value = true;
        } catch (error) {
            recoveryError.value = toTwoFactorError(error);
        } finally {
            recoveryLoading.value = false;
        }
    };

    /**
     * Every mutating action shares one pending flag so the panel cannot fire
     * two Fortify writes at once (enable while a disable is in flight, etc.).
     */
    const runAction = async (action: () => Promise<unknown>): Promise<void> => {
        actionPending.value = true;

        try {
            await action();
        } finally {
            actionPending.value = false;
        }
    };

    const enable = async (): Promise<void> => {
        await runAction(() => request('/user/two-factor-authentication', 'POST', {}));
    };

    const confirm = async (code: string): Promise<void> => {
        await runAction(() =>
            request('/user/confirmed-two-factor-authentication', 'POST', { code }),
        );
    };

    const disable = async (): Promise<void> => {
        await runAction(async () => {
            await request('/user/two-factor-authentication', 'DELETE');
            reset();
        });
    };

    const regenerateRecoveryCodes = async (): Promise<void> => {
        await runAction(async () => {
            /*
             * Fortify's regenerating POST answers with an empty 200; the new
             * codes are read back through the GET endpoint, which also proves
             * the fresh password confirmation still holds.
             */
            await request('/user/two-factor-recovery-codes', 'POST', {});

            const payload = await request('/user/two-factor-recovery-codes', 'GET');
            const codes = decodeRecoveryCodes(payload);

            if (codes.length === 0) {
                throw new TwoFactorRequestError('failed', 'No recovery codes were returned.');
            }

            recoveryCodes.value = codes;
            recoveryLoaded.value = true;
        });
    };

    const confirmPassword = async (password: string): Promise<void> => {
        await request('/user/confirm-password', 'POST', { password });
    };

    const reset = (): void => {
        qrDataUrl.value = null;
        qrError.value = null;
        recoveryCodes.value = [];
        recoveryLoaded.value = false;
        recoveryError.value = null;
    };

    return {
        qrDataUrl,
        qrLoading,
        qrError,
        recoveryCodes,
        recoveryLoaded,
        recoveryLoading,
        recoveryError,
        actionPending,
        loadQr,
        refreshQr,
        loadRecoveryCodes,
        enable,
        confirm,
        disable,
        regenerateRecoveryCodes,
        confirmPassword,
        reset,
    };
}
