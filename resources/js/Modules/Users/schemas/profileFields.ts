import { z } from 'zod';
import { emailSchema } from '@/lib/email';
import { todayLocalIso } from '@/lib/date';
import { phoneField } from '@/lib/phone';
import { usernameSchema } from '@/lib/username';

/**
 * The profile contract every user form shares.
 *
 * `UpdateUserRequest` (admin edit), Fortify's profile update (self-service) and
 * the create screen all validate the same identity + address fields. Each
 * screen used to keep its own copy, which is how one of them drifted to a
 * different gender default and a different "before today" rule.
 *
 * The server remains authoritative; this is the one client mirror.
 */

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

/**
 * Today in the viewer's zone, evaluated per validation (not at module load),
 * so a long-lived tab does not accept yesterday's boundary forever.
 */
const dateOfBirthSchema = z
    .string()
    .max(10)
    .refine(
        (value) => value === '' || (ISO_DATE.test(value) && value < todayLocalIso()),
        'Enter a date before today',
    );

export const genderSchema = z.enum(['male', 'female', 'other']).nullable();

/** Identity + contact fields, shared by create, admin edit and self-profile. */
export const profileIdentityShape = {
    first_name: z.string().trim().min(1, 'Enter a first name').max(255),
    last_name: z.string().max(255),
    username: usernameSchema,
    email: emailSchema,
    phone: phoneField({ max: 32 }),
    date_of_birth: dateOfBirthSchema,
    gender: genderSchema,
};

/** Postal address and coordinates. */
export const profileAddressShape = {
    address: z.string().max(255),
    address_2: z.string().max(255),
    zip_code: z.string().max(32),
    city: z.string().max(120),
    state: z.string().max(120),
    country: z.string().max(120),
    latitude: z
        .number()
        .nullable()
        .refine(
            (value) => value === null || (value >= -90 && value <= 90),
            'Latitude must be between -90 and 90',
        ),
    longitude: z
        .number()
        .nullable()
        .refine(
            (value) => value === null || (value >= -180 && value <= 180),
            'Longitude must be between -180 and 180',
        ),
};

/** The narrow `gender` input accepted by the server. */
export const isGender = (value: string | null): value is 'male' | 'female' | 'other' =>
    value === 'male' || value === 'female' || value === 'other';
