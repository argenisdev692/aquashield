import { useState } from 'react';
import { router } from '@inertiajs/react';
import { xsrfToken } from '@/lib/http';

/**
 * Profile photo transport for the account screen.
 *
 * The page selects a File; this hook owns the multipart request and the
 * follow-up Inertia reload that refreshes both the page prop and the shell
 * avatar. The server re-encodes to WebP and stores through StoragePort (R2).
 */
export class ProfilePhotoUploadError extends Error {
    constructor(message: string) {
        super(message);
        this.name = 'ProfilePhotoUploadError';
    }
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

function messageFrom(payload: unknown, status: number): string {
    // A 5xx body may carry an exception message; never surface it (same rule
    // as lib/http.ts). Field errors and 4xx copy are app-authored.
    if (status < 500 && isRecord(payload)) {
        const errors = payload.errors;

        if (
            isRecord(errors) &&
            Array.isArray(errors.photo) &&
            typeof errors.photo[0] === 'string'
        ) {
            return errors.photo[0];
        }

        if (typeof payload.message === 'string' && payload.message !== '') {
            return payload.message;
        }
    }

    return status === 419
        ? 'Your session expired. Reload the page and try again.'
        : 'The profile photo could not be saved. Try again.';
}

async function send(method: 'POST' | 'DELETE', body?: FormData): Promise<void> {
    const response = await fetch('/settings/profile/photo', {
        method,
        body,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
    });

    if (!response.ok) {
        const payload: unknown = await response.json().catch(() => null);
        throw new ProfilePhotoUploadError(messageFrom(payload, response.status));
    }
}

export function useProfilePhotoUpload() {
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const upload = async (file: File): Promise<void> => {
        setUploading(true);
        setError(null);

        try {
            const body = new FormData();
            body.append('photo', file);

            await send('POST', body);
            router.reload({ only: ['user'] });
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'The profile photo could not be saved. Try again.',
            );
        } finally {
            setUploading(false);
        }
    };

    const remove = async (): Promise<void> => {
        setUploading(true);
        setError(null);

        try {
            await send('DELETE');
            router.reload({ only: ['user'] });
        } catch (exception) {
            setError(
                exception instanceof Error
                    ? exception.message
                    : 'The profile photo could not be removed. Try again.',
            );
        } finally {
            setUploading(false);
        }
    };

    return { uploading, error, upload, remove };
}
