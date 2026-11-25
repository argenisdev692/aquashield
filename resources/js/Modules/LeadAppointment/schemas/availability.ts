import { z } from 'zod';

/**
 * Client mirror of SaveAvailabilityExceptionRequest / BulkRangeExceptionRequest
 * / SaveAvailabilityRuleRequest. The server remains authoritative for the
 * unique-date constraint and for turning a synced holiday into a manual row;
 * the browser only catches the mistakes a calendar can catch immediately.
 */
const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;
const TIME = /^([01]\d|2[0-3]):[0-5]\d$/;

export const DAY_OPTIONS = [
    { value: '0', label: 'Sunday' },
    { value: '1', label: 'Monday' },
    { value: '2', label: 'Tuesday' },
    { value: '3', label: 'Wednesday' },
    { value: '4', label: 'Thursday' },
    { value: '5', label: 'Friday' },
    { value: '6', label: 'Saturday' },
] as const;

const windowFields = (
    value: { start_time: string; end_time: string },
    ctx: z.core.$RefinementCtx,
): void => {
    if (!TIME.test(value.start_time)) {
        ctx.addIssue({
            code: 'custom',
            path: ['start_time'],
            message: 'Choose the time the crew can start',
        });
    }

    if (!TIME.test(value.end_time)) {
        ctx.addIssue({
            code: 'custom',
            path: ['end_time'],
            message: 'Choose the time the crew stops',
        });
    }

    if (
        TIME.test(value.start_time) &&
        TIME.test(value.end_time) &&
        value.end_time <= value.start_time
    ) {
        ctx.addIssue({
            code: 'custom',
            path: ['end_time'],
            message: 'The end time must be later than the start time',
        });
    }
};

export const availabilityExceptionSchema = z
    .object({
        date: z.string().regex(ISO_DATE, 'Choose the day this applies to'),
        is_available: z.boolean(),
        start_time: z.string(),
        end_time: z.string(),
        reason: z.string().max(255, 'Keep the reason under 255 characters'),
    })
    .superRefine((value, ctx) => {
        if (value.is_available) {
            windowFields(value, ctx);
        }
    });

export type AvailabilityExceptionFormValues = z.infer<typeof availabilityExceptionSchema>;

export const bulkClosureSchema = z
    .object({
        from: z.string().regex(ISO_DATE, 'Choose the first closed day'),
        to: z.string().regex(ISO_DATE, 'Choose the last closed day'),
        reason: z.string().max(255, 'Keep the reason under 255 characters'),
    })
    .superRefine((value, ctx) => {
        if (ISO_DATE.test(value.from) && ISO_DATE.test(value.to) && value.to < value.from) {
            ctx.addIssue({
                code: 'custom',
                path: ['to'],
                message: 'The last day cannot precede the first one',
            });
        }
    });

export type BulkClosureFormValues = z.infer<typeof bulkClosureSchema>;

export const availabilityRuleSchema = z
    .object({
        day_of_week: z.enum(['0', '1', '2', '3', '4', '5', '6']),
        start_time: z.string(),
        end_time: z.string(),
        is_available: z.boolean(),
    })
    .superRefine((value, ctx) => windowFields(value, ctx));

export type AvailabilityRuleFormValues = z.infer<typeof availabilityRuleSchema>;

/**
 * The wire payload for a weekly rule. Exported so the closed-state contract can
 * be tested without mounting the dialog: editing a closed rule must not turn it
 * into an open one.
 */
export const availabilityRulePayload = (
    values: AvailabilityRuleFormValues,
): {
    day_of_week: number;
    start_time: string;
    end_time: string;
    is_available: boolean;
} => ({
    day_of_week: Number(values.day_of_week),
    start_time: values.start_time,
    end_time: values.end_time,
    is_available: values.is_available,
});

/** `HH:MM` for the wire, or null when the day is fully closed. */
export const wireWindow = (
    values: AvailabilityExceptionFormValues,
): { start_time: string | null; end_time: string | null } =>
    values.is_available && TIME.test(values.start_time) && TIME.test(values.end_time)
        ? { start_time: values.start_time, end_time: values.end_time }
        : { start_time: null, end_time: null };
