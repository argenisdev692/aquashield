/**
 * Browser transport helpers shared by module hooks.
 *
 * Laravel's `PreventRequestForgery` accepts the same `X-XSRF-TOKEN` header that
 * Inertia's own client sends, so plain `fetch` calls (multipart uploads and
 * JSON token flows) do not need a second CSRF mechanism.
 */
export function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match?.[1] === undefined ? '' : decodeURIComponent(match[1]);
}

/**
 * The session's `auth.password_confirmed_at` is missing or stale.
 *
 * `password.confirm` answers a JSON request with 423, and an HTML request with a
 * redirect to `/user/confirm-password`. Either shape means "ask the user for the
 * password (or a passkey) and retry".
 */
export class PasswordConfirmationRequiredError extends Error {
    constructor(message = 'Confirm your password to continue.') {
        super(message);
        this.name = 'PasswordConfirmationRequiredError';
    }
}

/** A non-2xx JSON response, with Laravel's 422 field bag when present. */
export class HttpRequestError extends Error {
    readonly status: number;
    readonly fieldErrors: Readonly<Record<string, string>>;

    constructor(message: string, status = 0, fieldErrors: Readonly<Record<string, string>> = {}) {
        super(message);
        this.name = 'HttpRequestError';
        this.status = status;
        this.fieldErrors = fieldErrors;
    }
}

export interface JsonRequestOptions {
    method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
    body?: unknown;
    signal?: AbortSignal;
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const fieldErrorsFrom = (payload: unknown): Record<string, string> => {
    if (!isRecord(payload) || !isRecord(payload.errors)) {
        return {};
    }

    const errors: Record<string, string> = {};

    for (const [field, value] of Object.entries(payload.errors)) {
        const message = Array.isArray(value) ? value[0] : value;

        if (typeof message === 'string' && message !== '') {
            errors[field] = message;
        }
    }

    return errors;
};

const messageFrom = (payload: unknown): string | null => {
    if (isRecord(payload) && typeof payload.message === 'string' && payload.message !== '') {
        return payload.message;
    }

    return null;
};

/**
 * JSON in, JSON out, with the three framework shapes a security panel must tell
 * apart: password confirmation, validation errors, and an expired session.
 *
 * The caller owns the user-facing copy and the error class; this function owns
 * the transport.
 */
export async function requestJson(
    path: string,
    options: JsonRequestOptions = {},
): Promise<unknown> {
    const method = options.method ?? 'GET';
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    if (method !== 'GET') {
        headers['X-XSRF-TOKEN'] = xsrfToken();
    }

    if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(path, {
        method,
        credentials: 'same-origin',
        headers,
        body: options.body === undefined ? undefined : JSON.stringify(options.body),
        signal: options.signal,
    });

    // A JSON request hits `RequirePassword`'s 423 branch. An HTML request is
    // redirected to the confirmation screen and `fetch` follows it, so `ok` can
    // be true while the body is a page.
    if (response.status === 423) {
        throw new PasswordConfirmationRequiredError();
    }

    if (response.redirected) {
        const redirectPath = new URL(response.url, window.location.origin).pathname;

        if (redirectPath === '/user/confirm-password') {
            throw new PasswordConfirmationRequiredError();
        }

        throw new HttpRequestError('Your session expired. Reload the page and sign in again.');
    }

    if (!response.ok) {
        const payload: unknown = await response.json().catch(() => null);

        /*
         * Laravel puts the exception message in `message` when APP_DEBUG is on.
         * A 5xx is never a message the user can act on, and printing it would
         * leak internals into an alert region (OWASP A10 / frontend SKILL §14).
         * Every 4xx here is app-authored copy (validation, throttle, password
         * confirmation), so those keep the server's wording.
         */
        const serverMessage = response.status >= 500 ? null : messageFrom(payload);

        throw new HttpRequestError(
            serverMessage ?? 'The request could not be completed. Try again.',
            response.status,
            fieldErrorsFrom(payload),
        );
    }

    const text = await response.text();

    if (text.trim() === '') {
        return null;
    }

    try {
        return JSON.parse(text) as unknown;
    } catch {
        return null;
    }
}

export interface DownloadedFile {
    blob: Blob;
    filename: string | null;
}

const filenameFrom = (header: string | null): string | null => {
    if (header === null || header === '') {
        return null;
    }

    const utf8 = /filename\*=UTF-8''([^;]+)/i.exec(header);

    if (utf8?.[1] !== undefined) {
        try {
            return decodeURIComponent(utf8[1].replace(/^"|"$/g, ''));
        } catch {
            return null;
        }
    }

    return /filename="?([^";]+)"?/i.exec(header)?.[1] ?? null;
};

/** A streamed export that never answers must not leave the toolbar spinning. */
const DOWNLOAD_TIMEOUT_MS = 60_000;

/**
 * Download a streamed file without leaving the SPA.
 *
 * The export routes answer `Content-Disposition: attachment`, but a plain
 * anchor turns every refusal (422 row cap, 429 throttle, expired session) into
 * a top-level error page. Fetching the bytes keeps the table mounted; the
 * caller renders the message in its own alert region.
 */
export async function downloadFile(path: string): Promise<DownloadedFile> {
    const response = await fetch(path, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { Accept: 'application/json, application/octet-stream' },
        signal: AbortSignal.timeout(DOWNLOAD_TIMEOUT_MS),
    });

    if (response.redirected) {
        throw new HttpRequestError('Your session expired. Reload the page and sign in again.', 401);
    }

    if (!response.ok) {
        const payload: unknown = await response.json().catch(() => null);
        // Same rule as `requestJson`: never surface a 5xx exception message.
        const serverMessage = response.status >= 500 ? null : messageFrom(payload);

        throw new HttpRequestError(
            serverMessage ??
                (response.status === 429
                    ? 'Too many downloads. Wait a minute and try again.'
                    : 'The file could not be generated. Try again.'),
            response.status,
            fieldErrorsFrom(payload),
        );
    }

    return {
        blob: await response.blob(),
        filename: filenameFrom(response.headers.get('Content-Disposition')),
    };
}
