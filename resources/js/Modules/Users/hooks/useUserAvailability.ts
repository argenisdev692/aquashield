/**
 * Debounced uniqueness probe for the staff forms.
 *
 * This is the ONLY network boundary for the availability check: pages and
 * components never call `fetch` directly. The server remains authoritative via
 * `Rule::unique(...)`; the hook exists to turn a submit-time 422 into an
 * immediate inline signal.
 *
 * The probe is a TanStack Query, not an effect: the key includes the checked
 * value, so re-typing a previously checked name is served from cache, and the
 * abort signal is the query's own. The debounce sits on the value BEFORE the
 * key so a paused word produces one request, not one per keystroke.
 */
import { useEffect, useState } from 'react';
import { queryOptions, useQuery } from '@tanstack/react-query';
import { emailSchema } from '@/lib/email';
import { requestJson } from '@/lib/http';
import { isUsernameFormat, isUsernameReserved } from '@/lib/username';

const DEBOUNCE_MS = 375;
const STALE_TIME_MS = 30_000;
const GC_TIME_MS = 300_000;

export type AvailabilityState = 'idle' | 'checking' | 'available' | 'unavailable' | 'error';

export type AvailabilityField = 'username' | 'email';

export interface FieldAvailability {
    state: AvailabilityState;
    message: string;
}

/** Generated from `Modules\Users\Data\UserAvailabilityData` — never hand-written. */
type AvailabilityResponse = Modules.Users.Data.UserAvailabilityData;

const IDLE: FieldAvailability = { state: 'idle', message: '' };
const CHECKING: FieldAvailability = { state: 'checking', message: 'Checking…' };
const FAILED: FieldAvailability = {
    state: 'error',
    message: 'Could not verify availability. Try again.',
};
const RESERVED: FieldAvailability = {
    state: 'unavailable',
    message: 'That username is reserved.',
};

const isResponse = (value: unknown): value is AvailabilityResponse =>
    typeof value === 'object' &&
    value !== null &&
    'available' in value &&
    typeof value.available === 'boolean' &&
    'message' in value &&
    typeof value.message === 'string';

const fetchAvailability = async (
    field: AvailabilityField,
    value: string,
    ignoreUserUuid: string | null,
    signal: AbortSignal,
): Promise<AvailabilityResponse> => {
    const params = new URLSearchParams({ field, value });

    if (ignoreUserUuid !== null && ignoreUserUuid !== '') {
        params.set('ignore_user_id', ignoreUserUuid);
    }

    const payload = await requestJson(`/users/availability?${params.toString()}`, {
        signal,
    });

    if (!isResponse(payload)) {
        throw new Error('Unexpected availability response.');
    }

    return payload;
};

/**
 * The one definition of the probe. Reused by `useQuery` here and available to a
 * future prefetch; `enabled: false` is how an invalid draft is kept out of the
 * cache without dropping the `staleTime` contract.
 */
export const userAvailabilityQuery = (
    field: AvailabilityField,
    value: string,
    ignoreUserUuid: string | null,
) =>
    queryOptions({
        queryKey: ['users', 'availability', field, value, ignoreUserUuid] as const,
        queryFn: ({ signal }) => fetchAvailability(field, value, ignoreUserUuid, signal),
        enabled: value !== '',
        staleTime: STALE_TIME_MS,
        gcTime: GC_TIME_MS,
        // A uniqueness probe is not a live panel: refetching because the window
        // regained focus burns a throttled request for no new information.
        refetchOnWindowFocus: false,
        // A 403/422 is not transient. The global default retries once; a probe
        // that is refused should say so immediately.
        retry: false,
    });

/** Debounce the value, not the request: the key stays stable while typing. */
function useDebouncedValue(value: string, delay: number): string {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        if (value === '') {
            setDebounced('');

            return;
        }

        const timer = window.setTimeout(() => setDebounced(value), delay);

        return () => window.clearTimeout(timer);
    }, [value, delay]);

    return debounced;
}

export function useUserAvailability(
    field: AvailabilityField,
    value: string,
    ignoreUserUuid: string | null = null,
    enabled = true,
): FieldAvailability {
    const normalized = value.trim().toLowerCase();
    const debounced = useDebouncedValue(normalized, DEBOUNCE_MS);

    const hasValue = normalized !== '';
    const formatValid =
        field === 'username'
            ? isUsernameFormat(normalized)
            : emailSchema.safeParse(normalized).success;
    const reserved =
        field === 'username' && hasValue && formatValid && isUsernameReserved(normalized);
    const probeable = enabled && formatValid && !reserved;

    const query = useQuery(
        userAvailabilityQuery(field, probeable ? debounced : '', ignoreUserUuid),
    );

    if (!enabled || !hasValue || !formatValid) {
        return IDLE;
    }

    if (reserved) {
        return RESERVED;
    }

    // The timer has not paused yet: never answer for the previous word.
    if (debounced !== normalized) {
        return IDLE;
    }

    if (query.data !== undefined) {
        return {
            state: query.data.available ? 'available' : 'unavailable',
            message: query.data.message,
        };
    }

    return query.isError ? FAILED : CHECKING;
}
