import { z } from 'zod';
import { passwordSchema } from '@/lib/auth';
import { emailSchema } from '@/lib/email';
import { phoneField } from '@/lib/phone';
import { usernameSchema } from '@/lib/username';

/**
 * Client mirrors for the three forms on `/settings/profile`.
 *
 * The server remains authoritative: profile and address writes go through
 * Fortify's `UpdateUserProfileInformation`, and the password write through
 * `UpdateUserPassword`. These schemas exist so the kit can fail fast with the
 * same contract the server enforces.
 */

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

const today = (): string => {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');

    return `${now.getFullYear()}-${month}-${day}`;
};

const dateOfBirthSchema = z
    .string()
    .max(10)
    .refine(
        (value) => value === '' || (ISO_DATE.test(value) && value < today()),
        'Enter a date before today',
    );

const genderSchema = z.enum(['male', 'female', 'other']).nullable();

export const profileDetailsSchema = z.object({
    first_name: z.string().trim().min(1, 'Enter your first name').max(255),
    last_name: z.string().max(255),
    username: usernameSchema,
    email: emailSchema,
    // Accepts any country: the field stores an international value, so the
    // rule reads the calling code instead of assuming the US.
    phone: phoneField({ max: 32 }),
    date_of_birth: dateOfBirthSchema,
    gender: genderSchema,
});

export const profileAddressSchema = z.object({
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
});

export const updatePasswordSchema = z
    .object({
        current_password: z.string().min(1, 'Enter your current password'),
        password: passwordSchema,
        password_confirmation: z.string().min(1, 'Confirm the new password'),
    })
    .refine((values) => values.password === values.password_confirmation, {
        path: ['password_confirmation'],
        message: 'The passwords do not match',
    })
    .refine((values) => values.password !== values.current_password, {
        path: ['password'],
        message: 'Choose a password different from your current one',
    });

export type ProfileDetailsForm = z.infer<typeof profileDetailsSchema>;
export type ProfileAddressForm = z.infer<typeof profileAddressSchema>;
export type UpdatePasswordForm = z.infer<typeof updatePasswordSchema>;

export type AddressField = Exclude<
    keyof ProfileAddressForm,
    'address' | 'address_2' | 'latitude' | 'longitude'
>;

const genderFrom = (value: string | null): ProfileDetailsForm['gender'] =>
    value === 'male' || value === 'female' || value === 'other' ? value : null;

/** Map the generated DTO onto the string-only field contract. */
export function toProfileDetailsForm(
    user: App.Data.Auth.UserData | null = null,
): ProfileDetailsForm {
    return {
        first_name: user?.first_name ?? '',
        last_name: user?.last_name ?? '',
        username: user?.username ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        date_of_birth: user?.date_of_birth ?? '',
        gender: genderFrom(user?.gender ?? null),
    };
}

export function toProfileAddressForm(
    user: App.Data.Auth.UserData | null = null,
): ProfileAddressForm {
    return {
        address: user?.address ?? '',
        address_2: user?.address_2 ?? '',
        zip_code: user?.zip_code ?? '',
        city: user?.city ?? '',
        state: user?.state ?? '',
        country: user?.country ?? '',
        latitude: user?.latitude ?? null,
        longitude: user?.longitude ?? null,
    };
}
