import { z } from 'zod';
import { passwordSchema } from '@/lib/auth';
import { emailSchema } from '@/lib/email';
import { usernameSchema } from '@/lib/username';

/**
 * Client contracts for the account screen.
 *
 * These mirror — never replace — the Fortify actions on the server:
 * `UpdateUserProfileInformation` validates identity and address together, and
 * `UpdateUserPassword` owns the password rule. The schemas exist so the page
 * can fail fast, and they stay split because the screen edits identity and
 * address on two different tabs with two independent saves.
 */

const dateOfBirthSchema = z
    .string()
    .nullable()
    .refine(
        (value) => value === null || value === '' || /^\d{4}-\d{2}-\d{2}$/.test(value),
        'Enter a valid date',
    )
    .refine(
        (value) => value === null || value === '' || value < new Date().toISOString().slice(0, 10),
        'Date of birth must be in the past',
    );

const latitudeSchema = z
    .number()
    .nullable()
    .refine(
        (value) => value === null || (value >= -90 && value <= 90),
        'Latitude must be between -90 and 90',
    );

const longitudeSchema = z
    .number()
    .nullable()
    .refine(
        (value) => value === null || (value >= -180 && value <= 180),
        'Longitude must be between -180 and 180',
    );

export const GENDERS = ['male', 'female', 'other'] as const;

export type Gender = (typeof GENDERS)[number];

const genderSchema = z.enum(GENDERS).nullable();

/** Identity tab. `terms_and_conditions` is not part of this write. */
export const profileDetailsSchema = z.object({
    first_name: z.string().min(1, 'Enter your first name').max(255),
    last_name: z.string().max(255),
    username: usernameSchema,
    email: emailSchema,
    phone: z.string().max(32),
    date_of_birth: dateOfBirthSchema,
    gender: genderSchema,
});

/** Address tab. Coordinates come from Google and are never typed by hand. */
export const profileAddressSchema = z.object({
    address: z.string().max(255),
    address_2: z.string().max(255),
    zip_code: z.string().max(32),
    city: z.string().max(120),
    state: z.string().max(120),
    country: z.string().max(120),
    latitude: latitudeSchema,
    longitude: longitudeSchema,
});

export const updatePasswordSchema = z
    .object({
        current_password: z.string().min(1, 'Enter your current password'),
        password: passwordSchema,
        password_confirmation: z.string(),
    })
    .refine((values) => values.password === values.password_confirmation, {
        path: ['password_confirmation'],
        message: 'Passwords do not match',
    });

export const twoFactorCodeSchema = z.object({
    code: z.string().regex(/^\d{6}$/, 'Enter the six-digit code from your authenticator app'),
});

export const passwordGateSchema = z.object({
    password: z.string().min(1, 'Enter your current password'),
});

export type ProfileDetailsForm = z.infer<typeof profileDetailsSchema>;
export type ProfileAddressForm = z.infer<typeof profileAddressSchema>;
export type UpdatePasswordForm = z.infer<typeof updatePasswordSchema>;
export type TwoFactorCodeForm = z.infer<typeof twoFactorCodeSchema>;
export type PasswordGateForm = z.infer<typeof passwordGateSchema>;

/** The four derived address fields the Google selection locks. */
export const LOCKABLE_ADDRESS_FIELDS = ['city', 'state', 'zip_code', 'country'] as const;

export type LockableAddressField = (typeof LOCKABLE_ADDRESS_FIELDS)[number];

export type AddressField = keyof ProfileAddressForm;

const asGender = (value: string | null): Gender | null =>
    value === 'male' || value === 'female' || value === 'other' ? value : null;

const orEmpty = (value: string | null): string => value ?? '';

export const toProfileDetailsForm = (user: App.Data.Auth.UserData): ProfileDetailsForm => ({
    first_name: user.first_name,
    last_name: orEmpty(user.last_name),
    username: orEmpty(user.username),
    email: user.email,
    phone: orEmpty(user.phone),
    date_of_birth: user.date_of_birth,
    gender: asGender(user.gender),
});

export const toProfileAddressForm = (user: App.Data.Auth.UserData): ProfileAddressForm => ({
    address: orEmpty(user.address),
    address_2: orEmpty(user.address_2),
    zip_code: orEmpty(user.zip_code),
    city: orEmpty(user.city),
    state: orEmpty(user.state),
    country: orEmpty(user.country),
    latitude: user.latitude,
    longitude: user.longitude,
});

/**
 * The identity half of a Fortify profile write.
 *
 * `PUT /user/profile-information` validates every identity field on each call,
 * so saving the address tab has to carry the stored identity unchanged. Sending
 * the visible details draft instead would silently save unsaved name or email
 * edits from the other tab.
 */
export const identityPayload = (user: App.Data.Auth.UserData): ProfileDetailsForm =>
    toProfileDetailsForm(user);
