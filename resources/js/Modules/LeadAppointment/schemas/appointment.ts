import { z } from 'zod';

/**
 * Client mirror of StoreAppointmentRequest / RescheduleAppointmentRequest /
 * CancelAppointmentRequest. The server remains authoritative: availability,
 * the single-crew daily cap, the travel buffer and the future-date rule are
 * domain decisions that cannot be reproduced in a browser.
 *
 * The wire value is a wall-clock `YYYY-MM-DD HH:mm` interpreted in the
 * appointment's region timezone by the server, never a browser `Date`.
 */
const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;
const TIME = /^([01]\d|2[0-3]):[0-5]\d$/;

export const APPOINTMENT_KINDS = [
    'inspection',
    'estimate',
    'adjuster_meeting',
    'job_visit',
    'follow_up',
] as const;

export const appointmentFormSchema = z
    .object({
        lead_uuid: z.string().trim(),
        kind: z.enum(APPOINTMENT_KINDS),
        date: z.string().regex(ISO_DATE, 'Choose a date'),
        time: z.string().regex(TIME, 'Choose a time'),
        duration_minutes: z
            .number()
            .int('Use whole minutes')
            .min(15, 'At least 15 minutes')
            .max(480, 'At most 480 minutes'),
        region: z.enum(['', 'houston', 'dallas', 'florida']),
        is_emergency: z.boolean(),
        notes: z.string().max(5000),
    })
    .superRefine((value, ctx) => {
        if (value.lead_uuid === '') {
            ctx.addIssue({
                code: 'custom',
                path: ['lead_uuid'],
                message: 'Choose the lead this visit belongs to',
            });
        }
    });

export type AppointmentFormValues = z.infer<typeof appointmentFormSchema>;

export const rescheduleSchema = z.object({
    date: z.string().regex(ISO_DATE, 'Choose a date'),
    time: z.string().regex(TIME, 'Choose a time'),
    duration_minutes: z
        .number()
        .int('Use whole minutes')
        .min(15, 'At least 15 minutes')
        .max(480, 'At most 480 minutes'),
    reason: z
        .string()
        .trim()
        .min(3, 'Say why the visit moves')
        .max(500, 'Keep the reason under 500 characters'),
});

export type RescheduleFormValues = z.infer<typeof rescheduleSchema>;

export const cancelAppointmentSchema = z.object({
    reason: z
        .string()
        .trim()
        .min(3, 'Say why the visit is cancelled')
        .max(500, 'Keep the reason under 500 characters'),
});

export type CancelAppointmentValues = z.infer<typeof cancelAppointmentSchema>;

/** The string the server parses in the region timezone. */
export const startsAtFrom = (date: string, time: string): string => `${date} ${time}`;

/** Split a local ISO instant (`2026-11-24T10:00:00-06:00`) into date/time parts. */
export const localParts = (value: string): { date: string; time: string } => ({
    date: value.slice(0, 10),
    time: value.slice(11, 16),
});

/** Whole minutes between two ISO instants, or null when either is invalid. */
export const durationBetween = (startsAt: string, endsAt: string): number | null => {
    const start = new Date(startsAt).getTime();
    const end = new Date(endsAt).getTime();

    if (Number.isNaN(start) || Number.isNaN(end) || end <= start) {
        return null;
    }

    return Math.round((end - start) / 60_000);
};
