import { z } from 'zod';

/**
 * The one name contract for the two Spatie catalogues.
 *
 * Roles and permissions are both uppercase identifiers, and the identifier is
 * the API: route middleware spells `VIEW_ANY_ROLE`, the enum spells it, and the
 * matrix submits it. The server is authoritative (`PermissionName::RULE` and
 * `prepareForValidation()` normalise the same way); this mirror only keeps an
 * obvious typo out of the round-trip.
 */
export const accessNameSchema = z
    .string()
    .min(3, 'Use at least 3 characters.')
    .max(64, 'Use at most 64 characters.')
    .regex(
        /^[A-Z][A-Z0-9_]{2,63}$/,
        'Use uppercase letters, digits and underscores, starting with a letter.',
    );

/** Hard ceiling shared with the role requests (`max:1000` on the server). */
export const ACCESS_PERMISSION_LIMIT = 1000;
