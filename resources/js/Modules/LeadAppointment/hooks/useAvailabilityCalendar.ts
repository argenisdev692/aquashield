/**
 * The availability month panel's one read.
 *
 * `/availability/calendar` is a resolved feed (weekly rule → exception →
 * holiday), not a paginated list: the server has already decided every day in
 * the window. The page owns the visible month; this hook owns the key, the
 * fetcher and the cache policy, so the calendar and the appointment form's
 * day notice share one cache entry per window.
 */
import { queryOptions, useQuery, useQueryClient, useSuspenseQuery } from '@tanstack/react-query';
import type { AvailabilityDay } from '@/lib/availability';
import { requestJson } from '@/lib/http';

/**
 * `/availability/calendar` answers `AvailabilityDayData`, not `CalendarDayData`
 * (the appointment feed, which also carries `appointments`). The alias lives in
 * `lib/availability.ts` so the hook, the grid and the day notice cannot drift.
 */
type CalendarDay = AvailabilityDay;
type CalendarWindow = AvailabilityDay['windows'][number];

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const isWindow = (value: unknown): value is CalendarWindow =>
    isRecord(value) && typeof value.start === 'string' && typeof value.end === 'string';

const isCalendarDay = (value: unknown): value is CalendarDay =>
    isRecord(value) &&
    typeof value.date === 'string' &&
    typeof value.is_open === 'boolean' &&
    typeof value.source === 'string' &&
    (value.reason === null || typeof value.reason === 'string') &&
    Array.isArray(value.windows) &&
    value.windows.every(isWindow);

const fetchCalendarDays = async (
    from: string,
    to: string,
    region: string | null,
    signal: AbortSignal,
): Promise<CalendarDay[]> => {
    const params = new URLSearchParams({ from, to });

    if (region !== null) {
        params.set('region', region);
    }

    const payload = await requestJson(`/availability/calendar?${params.toString()}`, { signal });

    if (!isRecord(payload) || !Array.isArray(payload.data)) {
        throw new Error('The availability feed returned an unexpected payload.');
    }

    /*
     * A day this client cannot read used to be filtered out — and a missing
     * day in the month grid reads as "closed", which is a silent business
     * change, not a display glitch. Fail instead: the panel's error boundary
     * offers a retry, and a contract drift is visible.
     */
    const days: CalendarDay[] = [];

    for (const day of payload.data) {
        if (!isCalendarDay(day)) {
            throw new Error('The availability feed returned a day this client cannot read.');
        }

        days.push(day);
    }

    return days;
};

const CALENDAR_PREFIX = ['lead-appointment', 'availability-calendar'] as const;

const calendarKey = (from: string, to: string, region: string | null) =>
    [...CALENDAR_PREFIX, from, to, region] as const;

/** Suspense-safe: no `enabled` and no placeholder, so the panel either has data or throws. */
export const availabilityCalendarQuery = (from: string, to: string, region: string | null = null) =>
    queryOptions({
        queryKey: calendarKey(from, to, region),
        queryFn: ({ signal }) => fetchCalendarDays(from, to, region, signal),
        staleTime: 30_000,
        gcTime: 300_000,
    });

/** Same key and fetcher, for a form that may not have a date yet. */
export const availabilityWindowQuery = (
    from: string,
    to: string,
    region: string | null = null,
    enabled = true,
) =>
    queryOptions({
        queryKey: calendarKey(from, to, region),
        enabled: enabled && from !== '' && to !== '',
        queryFn: ({ signal }) => fetchCalendarDays(from, to, region, signal),
        staleTime: 30_000,
        gcTime: 300_000,
    });

export interface AvailabilityCalendarController {
    days: CalendarDay[];
    isFetching: boolean;
}

export function useAvailabilityCalendar(
    from: string,
    to: string,
    region: string | null = null,
): AvailabilityCalendarController {
    const query = useSuspenseQuery(availabilityCalendarQuery(from, to, region));

    return { days: query.data, isFetching: query.isFetching };
}

export interface AvailabilityWindowController {
    days: CalendarDay[];
    isPending: boolean;
    isFetching: boolean;
    isError: boolean;
}

export function useAvailabilityWindow(
    from: string,
    to: string,
    region: string | null = null,
    enabled = true,
): AvailabilityWindowController {
    const query = useQuery(availabilityWindowQuery(from, to, region, enabled));

    return {
        days: query.data ?? [],
        isPending: query.isPending,
        isFetching: query.isFetching,
        isError: query.isError,
    };
}

/**
 * Invalidate every cached month after an Inertia write.
 *
 * Create/Edit/Delete are page visits, not TanStack mutations, so the page owns
 * the call — but the key stays in this module, and the page never imports the
 * query client itself.
 */
export function useAvailabilityCalendarInvalidator(): () => void {
    const queryClient = useQueryClient();

    return () => {
        void queryClient.invalidateQueries({ queryKey: CALENDAR_PREFIX });
    };
}
