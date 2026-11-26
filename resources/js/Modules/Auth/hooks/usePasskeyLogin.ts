import { useEffect, useRef, useState } from 'react';
import { xsrfToken } from '@/lib/http';
import { isWebAuthnSupported, parseVerificationOptions, serialiseAssertion } from '@/lib/webauthn';

/**
 * Passkey sign-in for the login card.
 *
 * Two entry points, one ceremony:
 *
 * - `signIn()` backs the explicit "Sign in with a passkey" control. It first
 *   aborts any conditional request still open — a browser rejects a second
 *   `credentials.get()` while one is pending.
 * - `startAutofill()` is WebAuthn conditional UI: the browser offers this
 *   account's discoverable passkeys inside the email field's autofill list.
 *   The FIDO Alliance's testing puts autofill ahead of a dedicated button
 *   (people sign in without hunting for a control), so the page starts it as
 *   soon as the form mounts and cancels it on unmount.
 *
 * Fortify 13 delegates WebAuthn to `laravel/passkeys`: `GET
 * /passkeys/login/options` returns an empty `allowCredentials` (discoverable
 * credentials), which is exactly what conditional mediation requires. The
 * same endpoint sets the challenge the POST must echo, so autofill and the
 * button share it.
 *
 * Everything browser-specific stays here. The page only knows the result and
 * the error string, which is the module-hook boundary the architecture tests
 * enforce (no network I/O in a .tsx file).
 */

export interface PasskeyLoginResult {
    redirect: string;
}

/** The challenge endpoint either yields options or a message worth showing. */
type OptionsResult =
    { ok: true; publicKey: PublicKeyCredentialRequestOptions } | { ok: false; message: string };

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const NETWORK_ERROR = 'The network request failed. Check your connection and try again.';

const errorMessage = (payload: unknown, status: number): string => {
    /*
     * A 5xx body can carry an exception message (and, with APP_DEBUG, paths or
     * SQL). `lib/http.ts` never surfaces it; this transport must not either.
     */
    if (status < 500 && isRecord(payload)) {
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
        : 'The passkey could not be verified. Try again.';
};

/** A DOMException name is the only reliable signal a browser gives us here. */
const describe = (error: unknown): string => {
    if (error instanceof DOMException) {
        switch (error.name) {
            case 'NotAllowedError':
                return 'No passkey was used. Try again, or sign in with your password.';
            case 'SecurityError':
                return 'Passkeys are not available on this address. Open the app over HTTPS or on localhost.';
            case 'AbortError':
                return 'The passkey prompt was dismissed.';
            case 'InvalidStateError':
                return 'That authenticator cannot be used for this passkey. Try another one.';
        }
    }

    if (error instanceof Error && error.message !== '') {
        return error.message;
    }

    return 'The passkey could not be verified. Try again.';
};

/**
 * Conditional mediation is off in older browsers and in some privacy modes.
 * Probe the static method instead of assuming, and treat a probe that throws
 * as "unavailable" rather than failing the page.
 */
const supportsConditionalMediation = async (): Promise<boolean> => {
    if (typeof window === 'undefined' || typeof window.PublicKeyCredential === 'undefined') {
        return false;
    }

    const probe = window.PublicKeyCredential.isConditionalMediationAvailable;

    if (typeof probe !== 'function') {
        return false;
    }

    try {
        return await probe.call(window.PublicKeyCredential);
    } catch {
        return false;
    }
};

export function usePasskeyLogin() {
    const [authenticating, setAuthenticating] = useState(false);
    const [error, setError] = useState<string | null>(null);

    /**
     * The in-flight conditional ceremony. Kept in a ref because nothing about
     * it is rendered: the browser owns the UI. Cleared synchronously on cancel
     * so a StrictMode remount can start a fresh one instead of finding a
     * corpse.
     */
    const ceremony = useRef<AbortController | null>(null);

    /** The explicit button's ceremony; aborted when the card unmounts. */
    const signInController = useRef<AbortController | null>(null);

    useEffect(
        () => () => {
            signInController.current?.abort();
            signInController.current = null;
        },
        [],
    );

    const clearError = (): void => setError(null);

    const cancelAutofill = (): void => {
        const controller = ceremony.current;
        ceremony.current = null;
        controller?.abort();
    };

    const loadOptions = async (signal?: AbortSignal): Promise<OptionsResult> => {
        let response: Response;

        try {
            response = await fetch('/passkeys/login/options', {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal,
            });
        } catch {
            // The caller decides whether a failed challenge is worth showing:
            // an explicit button deserves copy, an ambient autofill attempt
            // does not.
            return { ok: false, message: NETWORK_ERROR };
        }

        if (!response.ok) {
            return { ok: false, message: errorMessage(null, response.status) };
        }

        const publicKey = parseVerificationOptions(await response.json().catch(() => null));

        if (publicKey === null) {
            return { ok: false, message: 'The server returned an unexpected passkey challenge.' };
        }

        return { ok: true, publicKey };
    };

    /** Post the assertion and let the controller decide where the visitor lands. */
    const complete = async (
        credential: Credential | null,
        signal?: AbortSignal,
    ): Promise<PasskeyLoginResult | null> => {
        if (signal?.aborted) {
            return null;
        }

        if (!(credential instanceof PublicKeyCredential)) {
            setError('The authenticator did not return a passkey.');

            return null;
        }

        let serialised: Record<string, unknown>;

        try {
            serialised = serialiseAssertion(credential);
        } catch (caught) {
            setError(describe(caught));

            return null;
        }

        let response: Response;

        try {
            response = await fetch('/passkeys/login', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    credential: serialised,
                    remember: false,
                }),
                /*
                 * The POST is part of the ceremony: unmounting or stepping
                 * back from the card must not leave an authentication request
                 * in flight that the page will ignore.
                 */
                signal,
            });
        } catch {
            if (signal?.aborted) {
                return null;
            }

            setError(NETWORK_ERROR);

            return null;
        }

        if (signal?.aborted) {
            return null;
        }

        const payload: unknown = await response.json().catch(() => null);

        if (!response.ok || !isRecord(payload) || typeof payload.redirect !== 'string') {
            setError(errorMessage(payload, response.status));

            return null;
        }

        return { redirect: payload.redirect };
    };

    const signIn = async (): Promise<PasskeyLoginResult | null> => {
        // The visitor picked the button; the ambient ceremony has served its
        // purpose and would block a second `credentials.get()`.
        cancelAutofill();
        setError(null);

        // A previous click's ceremony (or the unmount) must not leave a login
        // POST running after this card has moved on.
        const controller = new AbortController();
        signInController.current?.abort();
        signInController.current = controller;

        // `PublicKeyCredential` is only exposed in a secure context, so a
        // plain-HTTP host hides it. Say so instead of failing silently.
        if (!isWebAuthnSupported()) {
            setError(
                'This browser or address does not support passkeys. They need HTTPS or localhost.',
            );

            return null;
        }

        setAuthenticating(true);

        try {
            const options = await loadOptions(controller.signal);

            if (controller.signal.aborted) {
                return null;
            }

            if (!options.ok) {
                setError(options.message);

                return null;
            }

            let credential: Credential | null;

            try {
                credential = await navigator.credentials.get({
                    publicKey: options.publicKey,
                    signal: controller.signal,
                });
            } catch (caught) {
                if (controller.signal.aborted) {
                    return null;
                }

                setError(describe(caught));

                return null;
            }

            if (controller.signal.aborted) {
                return null;
            }

            return await complete(credential, controller.signal);
        } catch (caught) {
            if (controller.signal.aborted) {
                return null;
            }

            setError(describe(caught));

            return null;
        } finally {
            if (signInController.current === controller) {
                signInController.current = null;
                setAuthenticating(false);
            }
        }
    };

    /**
     * Start conditional UI. Resolves with a result only when the visitor
     * actually picks a passkey from autofill; every other outcome — unsupported
     * browser, cancelled prompt, ignored suggestion — resolves to null and
     * leaves no trace on screen.
     */
    const startAutofill = async (): Promise<PasskeyLoginResult | null> => {
        if (ceremony.current !== null) {
            return null;
        }

        if (!isWebAuthnSupported()) {
            return null;
        }

        const controller = new AbortController();
        ceremony.current = controller;

        try {
            if (!(await supportsConditionalMediation()) || controller.signal.aborted) {
                return null;
            }

            const options = await loadOptions(controller.signal);

            if (!options.ok || controller.signal.aborted) {
                return null;
            }

            const credential = await navigator.credentials.get({
                publicKey: options.publicKey,
                mediation: 'conditional',
                signal: controller.signal,
            });

            if (controller.signal.aborted) {
                return null;
            }

            return await complete(credential, controller.signal);
        } catch (caught) {
            if (controller.signal.aborted) {
                return null;
            }

            // A prompt the visitor never answered is not an error. Only a real
            // rejection (server refusal, unsupported authenticator) deserves copy.
            const name = caught instanceof DOMException ? caught.name : '';

            if (name === 'NotAllowedError' || name === 'AbortError') {
                return null;
            }

            setError(describe(caught));

            return null;
        } finally {
            if (ceremony.current === controller) {
                ceremony.current = null;
            }
        }
    };

    return { authenticating, error, clearError, signIn, startAutofill, cancelAutofill };
}
