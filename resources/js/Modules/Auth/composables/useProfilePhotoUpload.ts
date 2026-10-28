import { ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { xsrfToken } from '@/lib/http';

/**
 * Profile photo transport for the account screen.
 *
 * The page selects a File; this composable owns the multipart request and the
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
    if (isRecord(payload)) {
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
    const uploading: Ref<boolean> = ref(false);
    const error: Ref<string | null> = ref(null);

    const upload = async (file: File): Promise<void> => {
        uploading.value = true;
        error.value = null;

        try {
            const body = new FormData();
            body.append('photo', file);

            await send('POST', body);
            router.reload({ only: ['user'] });
        } catch (exception) {
            error.value =
                exception instanceof Error
                    ? exception.message
                    : 'The profile photo could not be saved. Try again.';
        } finally {
            uploading.value = false;
        }
    };

    const remove = async (): Promise<void> => {
        uploading.value = true;
        error.value = null;

        try {
            await send('DELETE');
            router.reload({ only: ['user'] });
        } catch (exception) {
            error.value =
                exception instanceof Error
                    ? exception.message
                    : 'The profile photo could not be removed. Try again.';
        } finally {
            uploading.value = false;
        }
    };

    return { uploading, error, upload, remove };
}
