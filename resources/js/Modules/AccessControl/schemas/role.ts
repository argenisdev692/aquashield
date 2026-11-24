import { z } from 'zod';
import { ACCESS_PERMISSION_LIMIT, accessNameSchema } from './name';

/**
 * Client mirror of the role FormRequest. The server remains authoritative; this
 * only keeps an obvious typo out of the round-trip.
 *
 * `permissions` is a set of names, not ids: the same vocabulary the route
 * middleware uses, so no id → name mapping can drift. Elements are validated
 * too, because a name is the identifier the server resolves.
 */
export const roleSchema = z.object({
    name: accessNameSchema,
    permissions: z
        .array(accessNameSchema)
        .max(
            ACCESS_PERMISSION_LIMIT,
            `A role cannot hold more than ${ACCESS_PERMISSION_LIMIT} permissions.`,
        ),
});

export type RoleForm = z.infer<typeof roleSchema>;
