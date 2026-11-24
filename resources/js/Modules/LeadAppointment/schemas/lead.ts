import { z } from 'zod';
import { personNameSchema } from '@/lib/names';
import { phoneField } from '@/lib/phone';

/**
 * Client mirror of StoreLeadRequest / UpdateLeadRequest.
 *
 * The server stays authoritative; these rules exist so the form kit fails fast
 * with the same contract the FormRequest enforces. Empty strings are what the
 * inputs produce and Laravel's ConvertEmptyStringsToNull turns them into null
 * before validation.
 */
const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;
const TIME = /^([01]\d|2[0-3]):[0-5]\d$/;

const optionalEmail = z
    .string()
    .trim()
    .max(255)
    .refine(
        (value) => value === '' || z.email().safeParse(value).success,
        'Enter a valid email address',
    );

const optionalDate = z
    .string()
    .max(10)
    .refine((value) => value === '' || ISO_DATE.test(value), 'Use YYYY-MM-DD');

const optionalTime = z
    .string()
    .max(5)
    .refine((value) => value === '' || TIME.test(value), 'Use HH:MM');

const leadShape = {
    first_name: personNameSchema('first name'),
    last_name: personNameSchema('last name'),
    company_name: z.string().max(255),
    email: optionalEmail,
    phone: phoneField({ max: 32 }),
    address: z.string().max(255),
    address_2: z.string().max(255),
    city: z.string().max(120),
    state: z.enum(['', 'TX', 'FL']),
    zip_code: z
        .string()
        .max(10)
        .refine(
            (value) => value === '' || /^\d{5}(-\d{4})?$/.test(value),
            'Use 77002 or 77002-1234',
        ),
    region: z.enum(['', 'houston', 'dallas', 'florida']),
    damage_description: z.string().max(5000),
    insurance_property: z.boolean(),
    intent_to_claim: z.boolean(),
    is_emergency: z.boolean(),
    follow_up_date: optionalDate,
    inspection_date: optionalDate,
    inspection_time: optionalTime,
    inspection_status: z.enum(['', 'pending', 'confirmed', 'completed', 'declined']),
    additional_note: z.string().max(5000),
    insurance_carrier: z.string().max(255),
    claim_number: z.string().max(100),
    notes: z.string().max(5000),
    owner_uuid: z.string(),
    preferred_language: z.enum(['en', 'es']),
    latitude: z.number().nullable(),
    longitude: z.number().nullable(),
};

const contactRefinement = (value: { email: string; phone: string }, ctx: z.RefinementCtx): void => {
    if (value.email === '' && value.phone === '') {
        ctx.addIssue({
            code: 'custom',
            path: ['email'],
            message: 'Add an email address or a phone number',
        });
        ctx.addIssue({
            code: 'custom',
            path: ['phone'],
            message: 'Add an email address or a phone number',
        });
    }
};

export const leadFormSchema = z.object(leadShape).superRefine(contactRefinement);

export type LeadFormValues = z.infer<typeof leadFormSchema>;

/** The blank draft shared by the create page and the dashboard modal. */
export const emptyLeadFormValues: LeadFormValues = {
    first_name: '',
    last_name: '',
    company_name: '',
    email: '',
    phone: '',
    address: '',
    address_2: '',
    city: '',
    state: '',
    zip_code: '',
    region: '',
    damage_description: '',
    insurance_property: false,
    intent_to_claim: false,
    is_emergency: false,
    follow_up_date: '',
    inspection_date: '',
    inspection_time: '',
    inspection_status: '',
    additional_note: '',
    insurance_carrier: '',
    claim_number: '',
    notes: '',
    owner_uuid: '',
    preferred_language: 'en',
    latitude: null,
    longitude: null,
};

export const logActivitySchema = z
    .object({
        type: z.enum(['call', 'email', 'sms', 'note', 'meeting']),
        outcome: z.string().max(255),
        notes: z.string().max(5000),
    })
    .refine((value) => value.outcome.trim() !== '' || value.notes.trim() !== '', {
        message: 'Add an outcome or a note',
        path: ['notes'],
    });

export type LogActivityValues = z.infer<typeof logActivitySchema>;
