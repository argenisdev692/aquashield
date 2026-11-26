import { useEffect, useState } from 'react';
import { xsrfToken } from '@/lib/http';

/**
 * Brand-asset transport for the company profile page.
 *
 * Invariant 1: this is the only place the module talks to the network. The page
 * passes a File and gets the refreshed, resolved URLs back — it never builds a
 * request or reads a cookie.
 *
 * `fetch` rather than a mutation: the payload is `multipart/form-data`, and the
 * XSRF cookie is the same one Inertia's client already reads. Laravel's
 * `PreventRequestForgery` decrypts `X-XSRF-TOKEN` exactly like its own header.
 */

export type CompanyAssetKind = Modules.CompanyData.Enums.CompanyAsset;
export type CompanyAssets = Modules.CompanyData.Data.CompanyAssetsDto;

/**
 * The asset controller shape, so the page can own the hook and both the header
 * preview and the branding panel read the same state after an upload.
 */
export interface CompanyAssetsController {
    assets: CompanyAssets;
    pending: CompanyAssetKind | null;
    error: string | null;
    upload: (kind: CompanyAssetKind, file: File) => Promise<boolean>;
    remove: (kind: CompanyAssetKind) => Promise<boolean>;
}

const ENDPOINT = '/company-data/assets';

/** Error carrying the message the server wants the user to read. */
export class CompanyAssetRequestError extends Error {
    constructor(message: string) {
        super(message);
        this.name = 'CompanyAssetRequestError';
    }
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

/**
 * Turn a canvas data URL into the PNG file the asset endpoint expects.
 *
 * The conversion belongs to the module transport, not to a component: the
 * drawing happens in the browser and the upload is this module's job.
 * `atob` keeps it synchronous and offline — no fetch, no object URL.
 */
export function signatureDataUrlToFile(dataUrl: string, filename = 'signature.png'): File {
    const [header, payload] = dataUrl.split(',');
    const mime = /^data:(image\/[a-z0-9.+-]+)/i.exec(header ?? '')?.[1] ?? 'image/png';
    const binary = atob(payload ?? '');
    const bytes = new Uint8Array(binary.length);

    for (let index = 0; index < binary.length; index += 1) {
        bytes[index] = binary.charCodeAt(index);
    }

    return new File([bytes], filename, { type: mime });
}

const isNullableString = (value: unknown): value is string | null =>
    value === null || typeof value === 'string';

const isCompanyAssets = (value: unknown): value is CompanyAssets =>
    isRecord(value) &&
    isNullableString(value.logo_url) &&
    isNullableString(value.logo_white_url) &&
    isNullableString(value.mark_url) &&
    isNullableString(value.signature_url);

function messageFrom(payload: unknown, status: number): string {
    // A 5xx body may carry an exception message; never surface it (same rule
    // as lib/http.ts). Field errors and 4xx copy are app-authored.
    if (status < 500 && isRecord(payload)) {
        const errors = payload.errors;

        if (isRecord(errors) && Array.isArray(errors.file) && typeof errors.file[0] === 'string') {
            return errors.file[0];
        }

        if (typeof payload.message === 'string' && payload.message !== '') {
            return payload.message;
        }
    }

    return status === 419
        ? 'Your session expired. Reload the page and try again.'
        : 'The upload failed. Try again.';
}

export function useCompanyAssets(initial: CompanyAssets): CompanyAssetsController {
    /**
     * The upload response is the freshest server state: it carries every
     * resolved URL after the write, so the panel swaps previews without a page
     * visit. `initial` seeds the first render, and a later prop (a page visit
     * that re-signs the URLs) re-seeds it instead of leaving an expired URL.
     */
    const [assets, setAssets] = useState<CompanyAssets>(initial);

    useEffect(() => {
        setAssets(initial);
    }, [initial]);

    /** The kind currently in flight, so one upload disables the rest. */
    const [pending, setPending] = useState<CompanyAssetKind | null>(null);

    /** The last failure, in the words the server chose. One panel, one message. */
    const [error, setError] = useState<string | null>(null);

    const request = async (
        kind: CompanyAssetKind,
        method: 'POST' | 'DELETE',
        body?: FormData,
    ): Promise<boolean> => {
        setPending(kind);
        setError(null);

        try {
            const response = await fetch(`${ENDPOINT}/${kind}`, {
                method,
                body,
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
            });

            const payload: unknown = await response.json().catch(() => null);

            if (!response.ok) {
                throw new CompanyAssetRequestError(messageFrom(payload, response.status));
            }

            if (!isCompanyAssets(payload)) {
                throw new CompanyAssetRequestError('The server returned an unexpected response.');
            }

            setAssets(payload);

            return true;
        } catch (caught) {
            setError(
                caught instanceof CompanyAssetRequestError
                    ? caught.message
                    : 'The upload failed. Try again.',
            );

            return false;
        } finally {
            setPending(null);
        }
    };

    const upload = (kind: CompanyAssetKind, file: File): Promise<boolean> => {
        const body = new FormData();
        body.append('file', file);

        return request(kind, 'POST', body);
    };

    const remove = (kind: CompanyAssetKind): Promise<boolean> => request(kind, 'DELETE');

    return { assets, pending, error, upload, remove };
}
