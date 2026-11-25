/**
 * Availability writes that are not page visits: a weekly rule, a closure
 * range, and the restore of a rule. Each one invalidates the calendar prefix
 * on settle, because a rule or a closure changes every month view that is
 * already cached.
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

    return { saveRule, deleteRule, closeRange };
}
