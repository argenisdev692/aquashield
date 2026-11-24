/**
 * Scheduling a visit from a client-owned panel (the dashboard calendar).
 *
 * The appointment CRUD pages submit through Inertia because a scheduling write
 * is a page visit. The dashboard is already mounted and must stay on the
 * calendar, so its date click posts JSON here and the page reloads its server
 * props afterwards. This hook is the only place that request lives.
 */
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { requestJson } from '@/lib/http';

export interface ScheduleAppointmentInput {
    lead_uuid: string;
    kind: Modules.LeadAppointment.Domain.Enums.AppointmentKind;
    starts_at: string;
    duration_minutes: number | null;
    region: string | null;
    notes: string | null;
    is_emergency: boolean;
}

export interface ScheduleAppointmentController {
    schedule: (input: ScheduleAppointmentInput) => Promise<unknown>;
    isPending: boolean;
    error: Error | null;
}

export function useScheduleAppointment(): ScheduleAppointmentController {
    const queryClient = useQueryClient();

    const mutation = useMutation({
        mutationFn: (input: ScheduleAppointmentInput) =>
            requestJson('/appointments', { method: 'POST', body: input }),
        onSettled: () => {
            void queryClient.invalidateQueries({ queryKey: ['lead-appointment'] });
        },
    });

    return {
        schedule: (input) => mutation.mutateAsync(input),
        isPending: mutation.isPending,
        error: mutation.error,
    };
}
