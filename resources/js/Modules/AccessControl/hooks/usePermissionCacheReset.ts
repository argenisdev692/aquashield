/**
 * Flushes the Spatie permission cache.
 *
 * The endpoint answers with a redirect + flash, so this is an Inertia write, not
 * a fetch. It lives here because two screens trigger it and the page must not
 * own an endpoint (frontend architecture invariant 1).
 */
import { useState } from 'react';
import { router } from '@inertiajs/react';

export interface PermissionCacheReset {
    reset: () => void;
    resetting: boolean;
}

const CACHE_RESET_URL = '/access-control/cache-reset';

export function usePermissionCacheReset(): PermissionCacheReset {
    const [resetting, setResetting] = useState(false);

    const reset = (): void => {
        router.post(
            CACHE_RESET_URL,
            {},
            {
                preserveScroll: true,
                onStart: () => setResetting(true),
                onFinish: () => setResetting(false),
            },
        );
    };

    return { reset, resetting };
}
