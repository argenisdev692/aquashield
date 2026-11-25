import { z } from 'zod';
import { passwordSchema, PASSWORD_HINT } from '@/lib/auth';
import { isGender, profileAddressShape, profileIdentityShape } from './profileFields';

/**
 * Client mirror of StoreUserRequest / UpdateUserRequest.
 *
 * The server remains authoritative. These schemas exist so the form kit can
 * fail fast with the same contract the FormRequest enforces: the trim/lowercase
 * normalisation still happens server-side in App\Support\UserProfileInput.
 */

const profileShape = {
    ...profileIdentityShape,
    ...profileAddressShape,
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
        /*
         * A new account starts unset: the server accepts a nullable gender and
         * a silent "male" default invented personal data the administrator
         * never entered. An out-of-contract stored value also reads as unset.
         */
        gender: user === null ? null : isGender(user.gender) ? user.gender : null,
        latitude: user?.latitude ?? null,
        longitude: user?.longitude ?? null,
    };
}
