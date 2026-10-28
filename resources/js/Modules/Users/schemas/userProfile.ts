import { z } from 'zod';
import { isValidPhone } from '@/lib/phone';
import { usernameSchema } from '@/lib/username';

/**
 * Client mirror of StoreUserRequest / UpdateUserRequest.
 *
 * The server remains authoritative. These schemas exist so the form kit can
 * fail fast with the same contract the FormRequest enforces: the trim/lowercase
 * normalisation still happens server-side in App\Support\UserProfileInput.
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

const profileShape = {
    first_name: z.string().trim().min(1, 'Enter a first name').max(255),
    last_name: z.string().max(255),
    username: usernameSchema,
    email: z.email('Enter a valid email address').max(255),
    // The server accepts any string up to 32 chars; the client uses the same
    // libphonenumber-js rule as PhoneField, so an incomplete draft cannot be
    // saved as a "phone number".
    phone: z
        .string()
        .max(32)
        .refine(
            (value) => value === '' || isValidPhone(value),
            'Enter a valid phone number with its country code',
        ),
    date_of_birth: dateOfBirthSchema,
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

export const updateUserSchema = z.object(profileShape);

export const createUserSchema = updateUserSchema.extend({
    roles: z.array(z.string()),
    permissions: z.array(z.string()),
});

export type UpdateUserForm = z.infer<typeof updateUserSchema>;
export type CreateUserForm = z.infer<typeof createUserSchema>;

export type UserProfileField =
    | 'first_name'
    | 'last_name'
    | 'username'
    | 'email'
    | 'phone'
    | 'date_of_birth'
    | 'address'
    | 'address_2'
    | 'zip_code'
    | 'city'
    | 'state'
    | 'country'
    | 'latitude'
    | 'longitude';

/** Map the generated DTO onto the form's string-only field contract. */
export function toUserProfileForm(
    user: Modules.Users.Data.ManagedUserData | null = null,
): UpdateUserForm {
    return {
        first_name: user?.first_name ?? '',
        last_name: user?.last_name ?? '',
        username: user?.username ?? '',
        email: user?.email ?? '',
        phone: user?.phone ?? '',
        date_of_birth: user?.date_of_birth ?? '',
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
