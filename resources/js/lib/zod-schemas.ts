import { z } from 'zod';

export const userSchema = z.object({
    id: z.number(),
    name: z.string().min(1),
    email: z.string().email(),
    role: z.enum(['admin', 'editor', 'viewer']),
    createdAt: z.string().datetime(),
});

export type User = z.infer<typeof userSchema>;

export const userListSchema = z.object({
    data: z.array(userSchema),
    meta: z.object({
        total: z.number(),
        perPage: z.number(),
        currentPage: z.number(),
    }),
});
