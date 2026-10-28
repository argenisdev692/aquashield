import { z } from 'zod';

/**
 * The two access writes are separate forms on purpose: the server has separate
 * endpoints, permissions, audit events and failure semantics for a role grant
 * and a direct-permission grant.
 */

export const userRolesSchema = z.object({
    roles: z.array(z.string()),
});

export const userPermissionsSchema = z.object({
    permissions: z.array(z.string()),
});

export type UserRolesForm = z.infer<typeof userRolesSchema>;
export type UserPermissionsForm = z.infer<typeof userPermissionsSchema>;
