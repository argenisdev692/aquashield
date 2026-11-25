import { z } from 'zod';
import { accessNameSchema } from './name';

/**
 * A permission has one editable field. The server also normalises to uppercase
 * and enforces uniqueness per guard.
 */
export const permissionSchema = z.object({
    name: accessNameSchema,
    /** The filtered index to return to after a successful write. */
    return_to: z.string().max(2048),
});

export type PermissionForm = z.infer<typeof permissionSchema>;
