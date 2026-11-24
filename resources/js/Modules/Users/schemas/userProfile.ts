import { z } from 'zod';
import { emailSchema } from '@/lib/email';
import { phoneField } from '@/lib/phone';
import { usernameSchema } from '@/lib/username';
import { passwordSchema, PASSWORD_HINT } from '@/lib/auth';

/**
 * Client mirror of StoreUserRequest / UpdateUserRequest.
 *
 * The server remains authoritative. These schemas exist so the form kit can
 * fail fast with the same contract the FormRequest enforces: the trim/lowercase
 * normalisation still happens server-side in App\Support\UserProfileInput.
 */

const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

/** Narrow the generated `string | null` onto the form's gender enum. */
const isGender = (value: string | null): value is 'male' | 'female' | 'other' =>
    value === 'male' || value === 'female' || value === 'other';

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
    email: emailSchema,
    // The server accepts any string up to 32 chars; the client uses the same
    // libphonenumber-js rule as PhoneField, so an incomplete draft cannot be
    // saved as a "phone number". Any country is accepted: the value carries
    // its own calling code.
    phone: phoneField({ max: 32 }),
    date_of_birth: dateOfBirthSchema,
    address: z.string().max(255),
    address_2: z.string().max(255),
    zip_code: z.string().max(32),
    city: z.string().max(120),
    state: z.string().max(120),
    country: z.string().max(120),
    gender: z.enum(['male', 'female', 'other']).nullable(),
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

/**
 * The create form adds two independent decisions:
 *  - who may act (roles + direct permission overrides);
 *  - how the account is handed over (signed invitation vs temporary password).
 *
 * The temporary password is validated only on the manual path, mirroring
 * StoreUserRequest's `required_if`.
 */
export const createUserSchema = updateUserSchema
    .extend({
        roles: z.array(z.string()),
        permissions: z.array(z.string()),
        send_invitation: z.boolean(),
        temporary_password: z.string(),
        temporary_password_confirmation: z.string(),
    })
    .superRefine((values, ctx) => {
        if (values.send_invitation) {
            return;
        }

        if (!passwordSchema.safeParse(values.temporary_password).success) {
            ctx.addIssue({
                code: 'custom',
                path: ['temporary_password'],
                message: PASSWORD_HINT,
            });
        }

        if (values.temporary_password !== values.temporary_password_confirmation) {
            ctx.addIssue({
                code: 'custom',
                path: ['temporary_password_confirmation'],
                message: 'Passwords do not match.',
            });
        }
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
    | 'longitude'
    | 'gender';

/** Map the generated DTO onto the form's string-only field contract. */
export function toUserProfileForm(
    user: Modules.Users.Data.ManagedUserData | null = null,
): UpdateUserForm {
    /*
     * New accounts start on a valid default; an out-of-contract value already
     * in the database becomes "Not specified" instead of being asserted onto
     * the enum and submitted back to the server.
     */
    const gender = user === null ? 'male' : isGender(user.gender) ? user.gender : null;

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
        gender,
        latitude: user?.latitude ?? null,
        longitude: user?.longitude ?? null,
    };
}
