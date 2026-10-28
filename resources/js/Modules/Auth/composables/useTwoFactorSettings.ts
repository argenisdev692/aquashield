import { ref, type Ref } from 'vue';

/**
 * Server state for the two-factor setup screen.
 *
 * Fetch lives here, not in the page: the boundary test treats a page as a
 * declaration of what is shown, and a composable as the thing that knows how
 * to get it. Fortify owns both endpoints; this file only turns their JSON into
 * renderable state.
 */

interface QrPayload {
    svg: string;
}

export interface TwoFactorSettings {
    qrDataUrl: Ref<string | null>;
    qrLoading: Ref<boolean>;
    qrError: Ref<string | null>;
    recoveryCodes: Ref<string[]>;
    recoveryLoading: Ref<boolean>;
    recoveryError: Ref<string | null>;
    loadQr: (signal: AbortSignal) => Promise<void>;
    loadRecoveryCodes: () => Promise<void>;
    reset: () => void;
}

const isJsonResponse = (response: Response): boolean =>
    response.ok &&
    !response.redirected &&
    (response.headers.get('content-type') ?? '').includes('application/json');

const isQrPayload = (payload: unknown): payload is QrPayload =>
    typeof payload === 'object' &&
    payload !== null &&
    'svg' in payload &&
    typeof payload.svg === 'string';

const decodeRecoveryCodes = (payload: unknown): string[] => {
    if (!Array.isArray(payload)) {
        return [];
    }

    const codes = payload.filter((code): code is string => typeof code === 'string');

    return codes.length === payload.length ? codes : [];
};

const toDataUrl = (svg: string): string => {
    const bytes = new TextEncoder().encode(svg);
    let binary = '';

    for (const byte of bytes) {
        binary += String.fromCharCode(byte);
    }

    return `data:image/svg+xml;base64,${btoa(binary)}`;
};

export function useTwoFactorSettings(): TwoFactorSettings {
    const qrDataUrl = ref<string | null>(null);
    const qrLoading = ref<boolean>(false);
    const qrError = ref<string | null>(null);

    const recoveryCodes = ref<string[]>([]);
    const recoveryLoading = ref<boolean>(false);
    const recoveryError = ref<string | null>(null);

    const loadQr = async (signal: AbortSignal): Promise<void> => {
        qrLoading.value = true;
        qrError.value = null;

        try {
            const response = await fetch('/user/two-factor-qr-code', {
                headers: { Accept: 'application/json' },
                signal,
            });

            if (!isJsonResponse(response)) {
                throw new Error('The QR code needs a fresh password confirmation.');
            }

            const payload: unknown = await response.json();

            if (!isQrPayload(payload)) {
                throw new Error('The QR code response was not valid.');
            }

            qrDataUrl.value = toDataUrl(payload.svg);
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                return;
            }

            qrDataUrl.value = null;
            qrError.value =
                error instanceof Error
                    ? error.message
                    : 'The QR code could not be loaded. Reload the page and try again.';
        } finally {
            qrLoading.value = false;
        }
    };

    const loadRecoveryCodes = async (): Promise<void> => {
        recoveryLoading.value = true;
        recoveryError.value = null;

        try {
            const response = await fetch('/user/two-factor-recovery-codes', {
                headers: { Accept: 'application/json' },
            });

            if (!isJsonResponse(response)) {
                throw new Error('Confirm your password again to view recovery codes.');
            }

            const codes = decodeRecoveryCodes(await response.json());

            if (codes.length === 0) {
                throw new Error('No recovery codes were returned.');
            }

            recoveryCodes.value = codes;
        } catch (error) {
            recoveryError.value =
                error instanceof Error
                    ? error.message
                    : 'Recovery codes could not be loaded. Reload the page and try again.';
        } finally {
            recoveryLoading.value = false;
        }
    };

    const reset = (): void => {
        qrDataUrl.value = null;
        qrError.value = null;
        recoveryCodes.value = [];
        recoveryError.value = null;
    };

    return {
        qrDataUrl,
        qrLoading,
        qrError,
        recoveryCodes,
        recoveryLoading,
        recoveryError,
        loadQr,
        loadRecoveryCodes,
        reset,
    };
}
