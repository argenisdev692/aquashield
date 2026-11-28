import { z } from 'zod';
import { passwordSchema } from '@/lib/auth';
import { isGender, profileAddressShape, profileIdentityShape } from './profileFields';

/**
 * Client mirrors for the three forms on `/settings/profile`.
 *
 * The server remains authoritative: profile and address writes go through
 * Fortify's `UpdateUserProfileInformation`, and the password write through
 * `UpdateUserPassword`. These schemas reuse the Users module's profile shape so
 * the admin and self-service screens cannot drift apart.
 */

/**
 * Self-service identity form. `current_password` is only required by the
 * server when the e-mail changes; it is optional here so the unchanged-email
 * save stays frictionless.
 */
export const profileDetailsSchema = z.object({
    ...profileIdentityShape,
    current_password: z.string().max(255).optional(),
});

export const profileAddressSchema = z.object(profileAddressShape);

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
    isGender(value) ? value : null;

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
        current_password: '',
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
