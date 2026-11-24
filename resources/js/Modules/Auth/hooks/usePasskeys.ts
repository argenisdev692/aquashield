import { useState } from 'react';
import { router } from '@inertiajs/react';
import { HttpRequestError, PasswordConfirmationRequiredError, requestJson } from '@/lib/http';
import {
    isWebAuthnSupported,
    parseRegistrationOptions,
    parseVerificationOptions,
    serialiseAssertion,
    serialiseAttestation,
} from '@/lib/webauthn';

/**
 * Passkey management for the account screen.
 *
 * Registration, deletion and the step-up confirmation are three different
 * package endpoints, but one class of failure matters to the screen: a stale
 * `password.confirm`. That becomes `password-required`, which the caller turns
 * into the confirmation dialog instead of a dead end.
 *
 * The list itself is an Inertia prop; after a mutation this hook reloads only
 * the three props that change, so the page never keeps a second copy.
 */

export type PasskeyErrorCode =
    'password-required' | 'unsupported' | 'cancelled' | 'validation' | 'network';

export class PasskeyError extends Error {
    readonly code: PasskeyErrorCode;
    readonly fieldErrors: Readonly<Record<string, string>>;

    constructor(
        message: string,
        code: PasskeyErrorCode = 'network',
        fieldErrors: Record<string, string> = {},
    ) {
        super(message);
        this.name = 'PasskeyError';
        this.code = code;
        this.fieldErrors = fieldErrors;
    }
}

const UNSUPPORTED =
    'This browser or address does not support passkeys. They need HTTPS or localhost.';

const describe = (error: unknown): PasskeyError => {
    if (error instanceof DOMException) {
        switch (error.name) {
            case 'NotAllowedError':
            case 'AbortError':
                return new PasskeyError(
                    'The passkey prompt was cancelled or timed out.',
                    'cancelled',
                );
            case 'InvalidStateError':
                return new PasskeyError(
                    'This device already has a passkey for your account.',
                    'validation',
                );
            case 'SecurityError':
                return new PasskeyError(UNSUPPORTED, 'unsupported');
        }
    }

    if (error instanceof Error && error.message !== '') {
        return new PasskeyError(error.message);
    }

    return new PasskeyError('The passkey request could not be completed. Try again.');
};

export const toPasskeyError = (caught: unknown): PasskeyError => {
    if (caught instanceof PasskeyError) {
        return caught;
    }

    if (caught instanceof PasswordConfirmationRequiredError) {
        return new PasskeyError(caught.message, 'password-required');
    }

    if (caught instanceof HttpRequestError) {
        return new PasskeyError(
            caught.fieldErrors.name ?? caught.fieldErrors.credential ?? caught.message,
            Object.keys(caught.fieldErrors).length > 0 ? 'validation' : 'network',
            caught.fieldErrors,
        );
    }

    return describe(caught);
};

const reloadSecurityProps = (): void => {
    router.reload({ only: ['passkeys', 'passkeysCount', 'passkeyPrompt'] });
};

export function usePasskeys() {
    const [supported] = useState(isWebAuthnSupported);
    const [adding, setAdding] = useState(false);
    const [removingId, setRemovingId] = useState<number | null>(null);
    const [confirming, setConfirming] = useState(false);

    const add = async (name: string): Promise<void> => {
        if (!supported) {
            throw new PasskeyError(UNSUPPORTED, 'unsupported');
        }

        setAdding(true);

        try {
            const options = parseRegistrationOptions(await requestJson('/user/passkeys/options'));

            if (options === null) {
                throw new PasskeyError('The server returned an unexpected passkey challenge.');
            }

            let credential: Credential | null;

            try {
                credential = await navigator.credentials.create({ publicKey: options });
            } catch (caught) {
                throw describe(caught);
            }

            if (!(credential instanceof PublicKeyCredential)) {
                throw new PasskeyError('The authenticator did not return a passkey.');
            }

            let serialised: Record<string, unknown>;

            try {
                serialised = serialiseAttestation(credential);
            } catch (caught) {
                throw describe(caught);
            }

            await requestJson('/user/passkeys', {
                method: 'POST',
                body: { name, credential: serialised },
            });

            reloadSecurityProps();
        } catch (caught) {
            throw toPasskeyError(caught);
        } finally {
            setAdding(false);
        }
    };

    const remove = async (id: number): Promise<void> => {
        setRemovingId(id);

        try {
            await requestJson(`/user/passkeys/${id}`, { method: 'DELETE' });
            reloadSecurityProps();
        } catch (caught) {
            throw toPasskeyError(caught);
        } finally {
            setRemovingId(null);
        }
    };

    /** Satisfy `password.confirm` with the password typed into the gate. */
    const confirmPassword = async (password: string): Promise<void> => {
        await requestJson('/user/confirm-password', {
            method: 'POST',
            body: { password },
        });
    };

    /**
     * Satisfy the same gate with a passkey. `laravel/passkeys` calls
     * `$session->passwordConfirmed()` after a valid assertion, so the parked
     * action resumes exactly as it does after a password.
     */
    const confirmWithPasskey = async (): Promise<void> => {
        if (!supported) {
            throw new PasskeyError(UNSUPPORTED, 'unsupported');
        }

        setConfirming(true);

        try {
            const options = parseVerificationOptions(
                await requestJson('/passkeys/confirm/options'),
            );

            if (options === null) {
                throw new PasskeyError('The server returned an unexpected passkey challenge.');
            }

            let credential: Credential | null;

            try {
                credential = await navigator.credentials.get({ publicKey: options });
            } catch (caught) {
                throw describe(caught);
            }

            if (credential === null) {
                throw new PasskeyError('The passkey prompt was dismissed.', 'cancelled');
            }

            if (!(credential instanceof PublicKeyCredential)) {
                throw new PasskeyError('The authenticator did not return a passkey.');
            }

            let serialised: Record<string, unknown>;

            try {
                serialised = serialiseAssertion(credential);
            } catch (caught) {
                throw describe(caught);
            }

            await requestJson('/passkeys/confirm', {
                method: 'POST',
                body: { credential: serialised },
            });
        } catch (caught) {
            throw toPasskeyError(caught);
        } finally {
            setConfirming(false);
        }
    };

    return {
        supported,
        adding,
        removingId,
        confirming,
        add,
        remove,
        confirmPassword,
        confirmWithPasskey,
    };
}
