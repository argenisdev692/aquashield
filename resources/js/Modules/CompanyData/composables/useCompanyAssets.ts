import { ref, type Ref } from 'vue';
import { xsrfToken } from '@/lib/http';

/**
 * Brand-asset transport for the company profile page.
 *
 * Invariant 1: this is the only place the module talks to the network. The page
 * passes a File and gets the refreshed, resolved URLs back — it never builds a
 * request or reads a cookie.
 *
 * `fetch` rather than `useHttp`: the payload is `multipart/form-data`, and the
 * XSRF cookie is the same one Inertia's client already reads. Laravel's
 * `PreventRequestForgery` decrypts `X-XSRF-TOKEN` exactly like its own header.
 */

export type CompanyAssetKind = Modules.CompanyData.Domain.Enums.CompanyAsset;
export type CompanyAssets = Modules.CompanyData.Application.Dtos.CompanyAssetsDto;

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

const isNullableString = (value: unknown): value is string | null =>
    value === null || typeof value === 'string';

const isCompanyAssets = (value: unknown): value is CompanyAssets =>
    isRecord(value) &&
    isNullableString(value.logo_url) &&
    isNullableString(value.logo_white_url) &&
    isNullableString(value.mark_url) &&
    isNullableString(value.signature_url);

function messageFrom(payload: unknown, status: number): string {
    if (isRecord(payload)) {
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

export function useCompanyAssets() {
    /** The kind currently in flight, so one upload disables the rest. */
    const pending: Ref<CompanyAssetKind | null> = ref(null);

    const request = async (
        kind: CompanyAssetKind,
        method: 'POST' | 'DELETE',
        body?: FormData,
    ): Promise<CompanyAssets> => {
        pending.value = kind;

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

            return payload;
        } finally {
            pending.value = null;
        }
    };

    const upload = (kind: CompanyAssetKind, file: File): Promise<CompanyAssets> => {
        const body = new FormData();
        body.append('file', file);

        return request(kind, 'POST', body);
    };

    const remove = (kind: CompanyAssetKind): Promise<CompanyAssets> => request(kind, 'DELETE');

    return { pending, upload, remove };
}
