/**
 * Invalidate every client-owned LeadAppointment read after an Inertia write.
 *
 * A stage change, a lead edit and an appointment lifecycle action are page
 * visits, not TanStack mutations. The page therefore owns the invalidation
 * call (frontend skill §7). A single prefix keeps the dashboard panel, the
 * calendar feed and the appointment drawer from drifting apart.
 */
import { useQueryClient } from '@tanstack/react-query';

export const LEAD_APPOINTMENT_QUERY_PREFIX = ['lead-appointment'] as const;

export function useLeadAppointmentInvalidator(): () => void {
    const queryClient = useQueryClient();

    return () => {
        void queryClient.invalidateQueries({ queryKey: LEAD_APPOINTMENT_QUERY_PREFIX });
    };
}
