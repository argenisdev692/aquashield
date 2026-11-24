/**
 * WebAuthn primitives shared by the login and account ceremonies.
 *
 * `laravel/passkeys` serialises every binary member as base64url without
 * padding, and the browser only accepts `ArrayBuffer`s. This module is the one
 * place that translates between the two, so the sign-in hook and the settings
 * hook cannot drift.
 */

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

/** `PublicKeyCredential` only exists in a secure context (HTTPS or localhost). */
export const isWebAuthnSupported = (): boolean =>
    typeof window !== 'undefined' && typeof window.PublicKeyCredential !== 'undefined';

export const decodeBase64Url = (value: string): Uint8Array<ArrayBuffer> => {
    const normalised = value.replace(/-/g, '+').replace(/_/g, '/');
    const padded = normalised.padEnd(Math.ceil(normalised.length / 4) * 4, '=');
    const binary = window.atob(padded);
    // The explicit ArrayBuffer keeps TypeScript 7's typed-array generic on
    // `ArrayBuffer` rather than the `ArrayBufferLike` union, which `BufferSource`
    // rejects (a SharedArrayBuffer is not a valid WebAuthn challenge).
    const bytes = new Uint8Array(new ArrayBuffer(binary.length));

    for (let index = 0; index < binary.length; index += 1) {
        bytes[index] = binary.charCodeAt(index);
    }

    return bytes;
};

export const encodeBase64Url = (value: ArrayBuffer): string => {
    const bytes = new Uint8Array(value);
    let binary = '';

    for (let index = 0; index < bytes.length; index += 1) {
        binary += String.fromCharCode(bytes[index] ?? 0);
    }

    return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
};

const decodeCredentialDescriptors = (value: unknown): PublicKeyCredentialDescriptor[] => {
    if (!Array.isArray(value)) {
        return [];
    }

    const descriptors: PublicKeyCredentialDescriptor[] = [];

    for (const entry of value) {
        if (isRecord(entry) && typeof entry.id === 'string' && entry.id !== '') {
            descriptors.push({ type: 'public-key', id: decodeBase64Url(entry.id) });
        }
    }

    return descriptors;
};

const readUserVerification = (
    value: unknown,
): AuthenticatorSelectionCriteria['userVerification'] => {
    if (value === 'required' || value === 'preferred' || value === 'discouraged') {
        return value;
    }

    return undefined;
};

const readResidentKey = (value: unknown): AuthenticatorSelectionCriteria['residentKey'] => {
    if (value === 'required' || value === 'preferred' || value === 'discouraged') {
        return value;
    }

    return undefined;
};

const readAttestation = (value: unknown): AttestationConveyancePreference | undefined => {
    if (value === 'none' || value === 'indirect' || value === 'direct' || value === 'enterprise') {
        return value;
    }

    return undefined;
};

/**
 * `{ options: { challenge, rpId, … } }` from `GET /passkeys/login/options`
 * or `GET /passkeys/confirm/options`.
 */
export const parseVerificationOptions = (
    payload: unknown,
): PublicKeyCredentialRequestOptions | null => {
    if (!isRecord(payload) || !isRecord(payload.options)) {
        return null;
    }

    const options = payload.options;

    if (typeof options.challenge !== 'string' || options.challenge === '') {
        return null;
    }

    if (typeof options.rpId !== 'string' || options.rpId === '') {
        return null;
    }

    const allowCredentials = decodeCredentialDescriptors(options.allowCredentials);

    return {
        challenge: decodeBase64Url(options.challenge),
        rpId: options.rpId,
        timeout: typeof options.timeout === 'number' ? options.timeout : undefined,
        userVerification: readUserVerification(options.userVerification),
        allowCredentials: allowCredentials.length > 0 ? allowCredentials : undefined,
    };
};

/**
 * `{ options: { challenge, rp, user, pubKeyCredParams, … } }` from
 * `GET /user/passkeys/options`.
 */
export const parseRegistrationOptions = (
    payload: unknown,
): PublicKeyCredentialCreationOptions | null => {
    if (!isRecord(payload) || !isRecord(payload.options)) {
        return null;
    }

    const options = payload.options;
    const rp = options.rp;
    const user = options.user;

    if (typeof options.challenge !== 'string' || options.challenge === '') {
        return null;
    }

    if (!isRecord(rp) || typeof rp.id !== 'string' || typeof rp.name !== 'string') {
        return null;
    }

    if (!isRecord(user) || typeof user.id !== 'string' || typeof user.name !== 'string') {
        return null;
    }

    if (!Array.isArray(options.pubKeyCredParams)) {
        return null;
    }

    const params: PublicKeyCredentialParameters[] = [];

    for (const entry of options.pubKeyCredParams) {
        if (isRecord(entry) && typeof entry.alg === 'number') {
            params.push({ type: 'public-key', alg: entry.alg });
        }
    }

    if (params.length === 0) {
        return null;
    }

    const selection = isRecord(options.authenticatorSelection)
        ? options.authenticatorSelection
        : null;

    const authenticatorSelection: AuthenticatorSelectionCriteria | undefined =
        selection === null
            ? undefined
            : {
                  residentKey: readResidentKey(selection.residentKey),
                  requireResidentKey:
                      typeof selection.requireResidentKey === 'boolean'
                          ? selection.requireResidentKey
                          : undefined,
                  userVerification: readUserVerification(selection.userVerification),
              };

    const excludeCredentials = decodeCredentialDescriptors(options.excludeCredentials);

    return {
        challenge: decodeBase64Url(options.challenge),
        rp: { id: rp.id, name: rp.name },
        user: {
            id: decodeBase64Url(user.id),
            name: user.name,
            displayName:
                typeof user.displayName === 'string' && user.displayName !== ''
                    ? user.displayName
                    : user.name,
        },
        pubKeyCredParams: params,
        timeout: typeof options.timeout === 'number' ? options.timeout : undefined,
        excludeCredentials: excludeCredentials.length > 0 ? excludeCredentials : undefined,
        authenticatorSelection,
        attestation: readAttestation(options.attestation),
    };
};

/**
 * The package deserialises the credential with the web-auth serialiser, which
 * expects base64url strings for every binary member — not arrays.
 */
export const serialiseAssertion = (credential: PublicKeyCredential): Record<string, unknown> => {
    const response = credential.response;

    if (!(response instanceof AuthenticatorAssertionResponse)) {
        throw new Error('The authenticator returned an unexpected response.');
    }

    const assertion: Record<string, unknown> = {
        clientDataJSON: encodeBase64Url(response.clientDataJSON),
        authenticatorData: encodeBase64Url(response.authenticatorData),
        signature: encodeBase64Url(response.signature),
    };

    if (response.userHandle !== null) {
        assertion.userHandle = encodeBase64Url(response.userHandle);
    }

    return {
        id: credential.id,
        rawId: encodeBase64Url(credential.rawId),
        type: credential.type,
        response: assertion,
    };
};

export const serialiseAttestation = (credential: PublicKeyCredential): Record<string, unknown> => {
    const response = credential.response;

    if (!(response instanceof AuthenticatorAttestationResponse)) {
        throw new Error('The authenticator returned an unexpected response.');
    }

    return {
        id: credential.id,
        rawId: encodeBase64Url(credential.rawId),
        type: credential.type,
        response: {
            clientDataJSON: encodeBase64Url(response.clientDataJSON),
            attestationObject: encodeBase64Url(response.attestationObject),
            transports: response.getTransports(),
        },
        clientExtensionResults: credential.getClientExtensionResults(),
    };
};
