/**
 * Appointment detail for the dashboard drawer.
 *
 * The month grid only receives the slim event projection. Opening an event
 * asks the appointment file for the rest on demand, so a page load never ships
 * a month of notes and contact details that nobody opened.
 */
import { queryOptions, useQuery } from '@tanstack/react-query';
import { requestJson } from '@/lib/http';

export type AppointmentDetail = Modules.LeadAppointment.Application.Dtos.AppointmentData;

const isRecord = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null;

const nullableString = (value: unknown): boolean => value === null || typeof value === 'string';

const isAppointmentDetail = (value: unknown): value is AppointmentDetail =>
    isRecord(value) &&
    typeof value.uuid === 'string' &&
    typeof value.lead_uuid === 'string' &&
    typeof value.customer_name === 'string' &&
    nullableString(value.customer_email) &&
    nullableString(value.customer_phone) &&
    typeof value.address_line === 'string' &&
    typeof value.kind === 'string' &&
    typeof value.status === 'string' &&
    typeof value.starts_at === 'string' &&
    typeof value.ends_at === 'string' &&
    typeof value.starts_at_local === 'string' &&
    typeof value.ends_at_local === 'string' &&
    typeof value.timezone === 'string' &&
    typeof value.region === 'string' &&
    nullableString(value.previous_starts_at) &&
    nullableString(value.google_event_id) &&
    nullableString(value.notes) &&
    nullableString(value.cancel_reason) &&
    nullableString(value.created_at) &&
    nullableString(value.deleted_at);

export const appointmentDetailQuery = (uuid: string | null, enabled = true) =>
    queryOptions({
        queryKey: ['lead-appointment', 'appointment', uuid] as const,
        enabled: enabled && uuid !== null,
        queryFn: async ({ signal }): Promise<AppointmentDetail> => {
            const payload = await requestJson(`/appointments/${uuid ?? ''}`, { signal });

            if (!isAppointmentDetail(payload)) {
                throw new Error('The appointment returned a payload this client cannot read.');
            }

            return payload;
        },
        staleTime: 30_000,
        gcTime: 300_000,
    });

export interface AppointmentDetailController {
    appointment: AppointmentDetail | null;
    isPending: boolean;
    isError: boolean;
    retry: () => void;
}

export function useAppointmentDetail(
    uuid: string | null,
    enabled = true,
): AppointmentDetailController {
    const query = useQuery(appointmentDetailQuery(uuid, enabled));

    return {
        appointment: query.data ?? null,
        isPending: query.isPending,
        isError: query.isError,
        retry: () => {
            void query.refetch();
        },
    };
}
