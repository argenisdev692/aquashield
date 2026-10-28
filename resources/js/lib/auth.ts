import { z } from 'zod';

/**
 * Client mirror of the server password rule in
 * `App\Actions\Fortify\PasswordValidationRules`.
 *
 * The server is authoritative; this copy exists only so the browser can fail
 * fast and show the same hint. Keep the two numbers in lockstep — a mismatch
 * is a UX bug, not a security hole, because Fortify validates every write.
 */
export const PASSWORD_MIN_LENGTH = 6;
export const PASSWORD_MAX_LENGTH = 10;

export const PASSWORD_HINT = `Use ${PASSWORD_MIN_LENGTH} to ${PASSWORD_MAX_LENGTH} characters.`;

export const passwordSchema = z
    .string()
    .min(PASSWORD_MIN_LENGTH, `Use at least ${PASSWORD_MIN_LENGTH} characters`)
    .max(PASSWORD_MAX_LENGTH, `Use at most ${PASSWORD_MAX_LENGTH} characters`);

/**
 * Status keys Fortify flashes after its redirect responses. The server owns
 * the keys; this map is presentation only, so a new Fortify status degrades to
 * a neutral "Saved." instead of rendering a raw slug.
 */
const FORTIFY_STATUS_MESSAGES: Readonly<Record<string, string>> = {
    'profile-information-updated': 'Profile updated.',
    'password-updated': 'Password updated.',
    'two-factor-authentication-enabled':
        'Two-factor authentication enabled. Scan the QR code, then confirm the six-digit code.',
    'two-factor-authentication-confirmed': 'Two-factor authentication confirmed.',
    'two-factor-authentication-disabled': 'Two-factor authentication disabled.',
    'recovery-codes-generated': 'New recovery codes generated. Store them somewhere safe.',
    'avatar-updated': 'Profile photo updated.',
    'avatar-removed': 'Profile photo removed.',
};

export const statusMessage = (status: string | null): string | null => {
    if (status === null || status === '') {
        return null;
    }

    return FORTIFY_STATUS_MESSAGES[status] ?? 'Saved.';
};
