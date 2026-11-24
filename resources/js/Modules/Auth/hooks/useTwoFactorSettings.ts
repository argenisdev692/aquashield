import { queryOptions, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from '@inertiajs/react';
import type { JsonRequestOptions } from '@/lib/http';
import {
    HttpRequestError,
    PasswordConfirmationRequiredError,
    requestJson as browserRequestJson,
} from '@/lib/http';

/**
 * Two-factor server state for the account screen.
 *
 * Fortify owns every write. The QR code and recovery codes are reads that need
 * a recently confirmed password (`password.confirm`); when that gate redirects,
 * `fetch` follows to an HTML page and sets `response.redirected`. We translate
 * that into `password-required`, which the screen turns into a password dialog
 * instead of a dead end.
 *
 * TanStack Query owns the two read panels; Inertia props own the
 * enabled/confirmed flags. The two never fetch the same endpoint.
 */

export type TwoFactorErrorCode = 'password-required' | 'validation' | 'network';

export class TwoFactorError extends Error {
    readonly code: TwoFactorErrorCode;
    readonly fieldErrors: Readonly<Record<string, string>>;

    constructor(
        message: string,
        code: TwoFactorErrorCode = 'network',
        fieldErrors: Record<string, string> = {},
    ) {
        super(message);
        this.name = 'TwoFactorError';
        this.code = code;
        this.fieldErrors = fieldErrors;
    }
}

export const toTwoFactorError = (error: unknown): TwoFactorError => {
    if (error instanceof TwoFactorError) {
        return error;
    }

    if (error instanceof Error && error.message !== '') {
        return new TwoFactorError(error.message);
    }

    return new TwoFactorError('Something went wrong. Reload the page and try again.');
};

interface QrPayload {
    svg: string;
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const isQrPayload = (payload: unknown): payload is QrPayload =>
    isRecord(payload) && typeof payload.svg === 'string' && payload.svg !== '';

const decodeRecoveryCodes = (payload: unknown): string[] => {
    if (!Array.isArray(payload)) {
        return [];
    }

    const codes = payload.filter((code): code is string => typeof code === 'string');

    return codes.length === payload.length ? codes : [];
};

/**
 * Translate the shared transport's errors into this panel's vocabulary.
 *
 * `password-required` is the only code the screen acts on (it opens the gate);
 * everything else becomes an inline message, with Laravel's 422 bag preserved so
 * the code field can be marked invalid.
 */
const requestJson = async (path: string, options: JsonRequestOptions = {}): Promise<unknown> => {
    try {
        return await browserRequestJson(path, options);
    } catch (caught) {
        if (caught instanceof PasswordConfirmationRequiredError) {
            throw new TwoFactorError(caught.message, 'password-required');
        }

        if (caught instanceof HttpRequestError) {
            throw new TwoFactorError(
                caught.fieldErrors.password ?? caught.fieldErrors.code ?? caught.message,
                Object.keys(caught.fieldErrors).length > 0 ? 'validation' : 'network',
                caught.fieldErrors,
            );
        }

        throw toTwoFactorError(caught);
    }
};

const toDataUrl = (svg: string): string => {
    const bytes = new TextEncoder().encode(svg);
    let binary = '';

    for (const byte of bytes) {
        binary += String.fromCharCode(byte);
    }

    return `data:image/svg+xml;base64,${btoa(binary)}`;
};

/**
 * Sensitive reads get `staleTime: 0` + `gcTime: 0`: the QR code and recovery
 * codes must not sit in a long-lived cache after the panel closes.
 */
export const twoFactorQrQuery = () =>
    queryOptions({
        queryKey: ['auth', 'two-factor', 'qr'],
        queryFn: async ({ signal }) => {
            const payload = await requestJson('/user/two-factor-qr-code', { signal });

            if (!isQrPayload(payload)) {
                throw new TwoFactorError('The QR code response was not valid.');
            }

            return toDataUrl(payload.svg);
        },
        staleTime: 0,
        gcTime: 0,
        retry: false,
    });

export const twoFactorRecoveryCodesQuery = () =>
    queryOptions({
        queryKey: ['auth', 'two-factor', 'recovery-codes'],
        queryFn: async ({ signal }) => {
            const payload = await requestJson('/user/two-factor-recovery-codes', { signal });
            const codes = decodeRecoveryCodes(payload);

            if (codes.length === 0) {
                throw new TwoFactorError('No recovery codes were returned.');
            }

            return codes;
        },
        staleTime: 0,
        gcTime: 0,
        retry: false,
    });

export interface TwoFactorSettings {
    qrDataUrl: string | null;
    qrLoading: boolean;
    qrError: TwoFactorError | null;
    recoveryCodes: string[];
    recoveryLoading: boolean;
    recoveryError: TwoFactorError | null;
    recoveryLoaded: boolean;
    actionPending: boolean;
    enable: () => Promise<void>;
    disable: () => Promise<void>;
    confirm: (code: string) => Promise<void>;
    regenerateRecoveryCodes: () => Promise<void>;
    confirmPassword: (password: string) => Promise<void>;
    loadRecoveryCodes: () => Promise<void>;
    refreshQr: () => Promise<void>;
    reset: () => void;
}

interface UseTwoFactorSettingsOptions {
    enabled: boolean;
    confirmed: boolean;
}

const reloadSecurityProps = (): void => {
    // Inertia v3's reload always preserves scroll and component state; the
    // options type deliberately omits both flags.
    router.reload({ only: ['twoFactorEnabled', 'twoFactorConfirmed', 'status'] });
};

export function useTwoFactorSettings({
    enabled,
    confirmed,
}: UseTwoFactorSettingsOptions): TwoFactorSettings {
    const queryClient = useQueryClient();

    const qr = useQuery({
        ...twoFactorQrQuery(),
        enabled: enabled && !confirmed,
    });

    const recovery = useQuery({
        ...twoFactorRecoveryCodesQuery(),
        enabled: false,
    });

    const enableMutation = useMutation({
        mutationFn: () => requestJson('/user/two-factor-authentication', { method: 'POST' }),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['auth', 'two-factor'] });
            reloadSecurityProps();
        },
    });

    const disableMutation = useMutation({
        mutationFn: () => requestJson('/user/two-factor-authentication', { method: 'DELETE' }),
        onSuccess: () => {
            queryClient.removeQueries({ queryKey: ['auth', 'two-factor'] });
            reloadSecurityProps();
        },
    });

    const confirmMutation = useMutation({
        mutationFn: (code: string) =>
            requestJson('/user/confirmed-two-factor-authentication', {
                method: 'POST',
                body: { code },
            }),
        onSuccess: () => {
            void queryClient.invalidateQueries({ queryKey: ['auth', 'two-factor'] });
            reloadSecurityProps();
        },
    });

    const regenerateMutation = useMutation({
        mutationFn: () =>
            requestJson('/user/two-factor-recovery-codes', {
                method: 'POST',
            }),
        onSuccess: () => {
            void recovery.refetch();
        },
    });

    const confirmPasswordMutation = useMutation({
        mutationFn: (password: string) =>
            requestJson('/user/confirm-password', {
                method: 'POST',
                body: { password },
            }),
    });

    const loadRecoveryCodes = async (): Promise<void> => {
        const result = await recovery.refetch();

        if (result.error !== null) {
            throw toTwoFactorError(result.error);
        }
    };

    const refreshQr = async (): Promise<void> => {
        const result = await qr.refetch();

        if (result.error !== null) {
            throw toTwoFactorError(result.error);
        }
    };

    const reset = (): void => {
        queryClient.removeQueries({ queryKey: ['auth', 'two-factor'] });
    };

    return {
        qrDataUrl: qr.data ?? null,
        qrLoading: qr.isFetching,
        qrError: qr.error === null ? null : toTwoFactorError(qr.error),
        recoveryCodes: recovery.data ?? [],
        recoveryLoading: recovery.isFetching,
        recoveryError: recovery.error === null ? null : toTwoFactorError(recovery.error),
        recoveryLoaded: recovery.isSuccess,
        actionPending:
            enableMutation.isPending ||
            disableMutation.isPending ||
            confirmMutation.isPending ||
            regenerateMutation.isPending,
        enable: async () => {
            await enableMutation.mutateAsync();
        },
        disable: async () => {
            await disableMutation.mutateAsync();
        },
        confirm: async (code: string) => {
            await confirmMutation.mutateAsync(code);
        },
        regenerateRecoveryCodes: async () => {
            await regenerateMutation.mutateAsync();
        },
        confirmPassword: async (password: string) => {
            await confirmPasswordMutation.mutateAsync(password);
        },
        loadRecoveryCodes,
        refreshQr,
        reset,
    };
}
