import { z } from 'zod';
import { accessNameSchema } from './name';

/**
 * A permission has one editable field. The server also normalises to uppercase
 * and enforces uniqueness per guard.
 */
export const permissionSchema = z.object({
    name: accessNameSchema,
});

export type PermissionForm = z.infer<typeof permissionSchema>;
