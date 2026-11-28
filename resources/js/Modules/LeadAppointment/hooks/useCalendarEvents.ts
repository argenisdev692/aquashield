/**
 * The dashboard calendar's bounded read.
 *
 * FullCalendar owns the visible range; this hook owns the cache key, the
 * request and the payload validation. Keeping the month grid on a client-owned
 * query means navigating months fetches that month instead of re-reading a
 * server prop that was fixed to "now" at page load.
 */
import { keepPreviousData, queryOptions, useQuery, useQueryClient } from '@tanstack/react-query';
import { requestJson } from '@/lib/http';

export type CalendarEvent = Modules.LeadAppointment.Application.Dtos.CalendarEventData;

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const isCalendarEvent = (value: unknown): value is CalendarEvent =>
    isRecord(value) &&
    typeof value.uuid === 'string' &&
    typeof value.lead_uuid === 'string' &&
    typeof value.customer_name === 'string' &&
    typeof value.starts_at_local === 'string' &&
    typeof value.timezone === 'string' &&
    typeof value.status === 'string' &&
    typeof value.kind === 'string' &&
    typeof value.region === 'string';

const fetchCalendarEvents = async (
    from: string,
    to: string,
    region: string,
    signal: AbortSignal,
): Promise<CalendarEvent[]> => {
    const params = new URLSearchParams({ from, to });

    if (region !== '') {
        params.set('region', region);
    }

    const payload = await requestJson(`/appointments/calendar-events?${params.toString()}`, {
        signal,
    });

    if (!isRecord(payload) || !Array.isArray(payload.data)) {
        throw new Error('The calendar feed returned an unexpected payload.');
    }

    const events: CalendarEvent[] = [];

    for (const row of payload.data) {
        if (!isCalendarEvent(row)) {
            throw new Error('The calendar feed returned an event this client cannot read.');
        }

        events.push(row);
    }

    return events;
};

const CALENDAR_PREFIX = ['lead-appointment', 'calendar-events'] as const;

const calendarEventsKey = (from: string, to: string, region: string) =>
    [...CALENDAR_PREFIX, from, to, region] as const;

export const calendarEventsQuery = (from: string, to: string, region: string, enabled = true) =>
    queryOptions({
        queryKey: calendarEventsKey(from, to, region),
        enabled: enabled && from !== '' && to !== '',
        queryFn: ({ signal }) => fetchCalendarEvents(from, to, region, signal),
        staleTime: 30_000,
        gcTime: 300_000,
        placeholderData: keepPreviousData,
    });

export interface CalendarEventsController {
    events: CalendarEvent[];
    isPending: boolean;
    isFetching: boolean;
    isPlaceholderData: boolean;
    isError: boolean;
    retry: () => void;
}

export function useCalendarEvents(
    from: string,
    to: string,
    region: string,
    enabled = true,
): CalendarEventsController {
    const query = useQuery(calendarEventsQuery(from, to, region, enabled));

    return {
        events: query.data ?? [],
        isPending: query.isPending,
        isFetching: query.isFetching,
        isPlaceholderData: query.isPlaceholderData,
        isError: query.isError,
        retry: () => {
            void query.refetch();
        },
    };
}

/**
 * Invalidate every cached month after an Inertia write. The dashboard owns the
 * visible range, so it also owns the refetch trigger.
 */
export function useCalendarEventsInvalidator(): () => void {
    const queryClient = useQueryClient();

    return () => {
        void queryClient.invalidateQueries({ queryKey: CALENDAR_PREFIX });
    };
}
