/**
 * Availability writes that are not page visits: a weekly rule, a closure
 * range, the holiday sync and the restore of a rule. Each one invalidates the
 * calendar prefix on settle, because a rule, a closure or a new holiday
 * changes every month view that is already cached.
 */
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { requestJson } from '@/lib/http';
import type { AvailabilityRuleFormValues, BulkClosureFormValues } from '../schemas/availability';

const CALENDAR_PREFIX = ['lead-appointment', 'availability-calendar'] as const;

interface SaveRuleInput {
    /** Null creates; a uuid updates the existing window. */
    uuid: string | null;
    values: AvailabilityRuleFormValues;
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

/**
 * The sync endpoint answers a generated DTO; a proxy, an error page or a
 * future rename must not be rendered as if it were a result. Same rule the
 * calendar feed applies to its own payload.
 *
 * `SyncHolidaysAction::execute()` with no year runs the resync branch, which
 * answers `HolidayResyncResultData` (`purged` + `created`), not the per-year
 * `HolidaySyncResultData`.
 */
const isHolidayResyncResult = (
    value: unknown,
): value is Modules.LeadAppointment.Application.Dtos.HolidayResyncResultData =>
    isRecord(value) && typeof value.purged === 'number' && typeof value.created === 'number';

const rulePayload = (values: AvailabilityRuleFormValues) => ({
    day_of_week: Number(values.day_of_week),
    start_time: values.start_time,
    end_time: values.end_time,
    is_available: true,
});

export function useAvailabilityMutations() {
    const queryClient = useQueryClient();

    const invalidateCalendar = (): void => {
        void queryClient.invalidateQueries({ queryKey: CALENDAR_PREFIX });
    };

    const saveRule = useMutation({
        mutationFn: async ({ uuid, values }: SaveRuleInput): Promise<void> => {
            await requestJson(
                uuid === null ? '/availability-rules' : `/availability-rules/${uuid}`,
                {
                    method: uuid === null ? 'POST' : 'PUT',
                    body: rulePayload(values),
                },
            );
        },
        onSettled: invalidateCalendar,
    });

    const deleteRule = useMutation({
        mutationFn: async (uuid: string): Promise<void> => {
            await requestJson(`/availability-rules/${uuid}`, { method: 'DELETE' });
        },
        onSettled: invalidateCalendar,
    });

    /**
     * The rules panel keeps a deleted window recoverable: the row is read back
     * through `trashedRules` and restored here. Same prefix invalidation — a
     * restored window changes every month that already resolved the day.
     */
    const restoreRule = useMutation({
        mutationFn: async (uuid: string): Promise<void> => {
            await requestJson(`/availability-rules/${uuid}/restore`, { method: 'PATCH' });
        },
        onSettled: invalidateCalendar,
    });

    const closeRange = useMutation({
        mutationFn: async (values: BulkClosureFormValues): Promise<void> => {
            const reason = values.reason.trim();

            await requestJson('/availability-exceptions/bulk-range', {
                method: 'POST',
                body: {
                    from: values.from,
                    to: values.to,
                    is_available: false,
                    reason: reason === '' ? null : reason,
                },
            });
        },
        onSettled: invalidateCalendar,
    });

    return { saveRule, deleteRule, restoreRule, closeRange };
}

/**
 * Materialise the federal calendar as company exceptions.
 *
 * The write is a JSON call, not a visit, so the page owns the follow-up: it
 * reloads `rows`/`total` and the calendar prefix is invalidated here. The
 * endpoint rebuilds the current and next year from today forward, purging the
 * holiday-sourced rows it owns first — which is why a manual edit to a holiday
 * row is not durable.
 *
 * The route is throttled (10/min) and re-checks CREATE_AVAILABILITY_EXCEPTION,
 * so the button that calls this is a convenience, never the control.
 */
export function useSyncHolidays() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn:
            async (): Promise<Modules.LeadAppointment.Application.Dtos.HolidayResyncResultData> => {
                const payload = await requestJson('/availability/sync-holidays', {
                    method: 'POST',
                });

                if (!isHolidayResyncResult(payload)) {
                    throw new Error('The holiday sync answered an unexpected payload.');
                }

                return payload;
            },
        onSettled: () => {
            void queryClient.invalidateQueries({ queryKey: CALENDAR_PREFIX });
        },
    });
}
