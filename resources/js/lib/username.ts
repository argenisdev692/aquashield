import { z } from 'zod';

/**
 * Client mirror of App\Rules\Username.
 *
 * The server is authoritative; this copy exists so the registration and
 * profile screens can fail fast and show the same contract. Keep the pattern,
 * bounds and reserved list in lockstep with the PHP rule.
 */
export const USERNAME_MIN = 3;
export const USERNAME_MAX = 20;

export const USERNAME_PATTERN = /^(?!.*[._-]{2})[a-z0-9](?:[a-z0-9._-]*[a-z0-9])?$/;

export const USERNAME_RESERVED: readonly string[] = [
    'admin',
    'administrator',
    'abuse',
    'api',
    'aquashield',
    'billing',
    'company-data',
    'contact',
    'cookie-consent',
    'dashboard',
    'email',
    'guest',
    'help',
    'hr',
    'info',
    'login',
    'logout',
    'marketing',
    'me',
    'moderator',
    'no-reply',
    'noreply',
    'null',
    'otp',
    'password',
    'postmaster',
    'private',
    'profile',
    'public',
    'register',
    'root',
    'sales',
    'security',
    'settings',
    'signin',
    'signup',
    'super-admin',
    'super_admin',
    'superadmin',
    'support',
    'system',
    'two-factor',
    'two-factor-challenge',
    'undefined',
    'user',
    'users',
    'verification',
    'verify',
    'webmaster',
    'www',
];

export const USERNAME_HINT =
    'Optional. 3–20 characters: letters, numbers, dots, dashes or underscores.';

export const USERNAME_FORMAT_MESSAGE =
    'Use 3–20 characters, start and end with a letter or number, and use only letters, numbers, dots, dashes or underscores.';

export const USERNAME_RESERVED_MESSAGE = 'That username is reserved. Choose another.';

const normalize = (value: string): string => value.trim().toLowerCase();

export const isUsernameFormat = (value: string): boolean => {
    const normalized = normalize(value);

    if (normalized.length < USERNAME_MIN || normalized.length > USERNAME_MAX) {
        return false;
    }

    if (!USERNAME_PATTERN.test(normalized)) {
        return false;
    }

    return !/^\d+$/.test(normalized);
};

export const isUsernameReserved = (value: string): boolean =>
    USERNAME_RESERVED.includes(normalize(value));

export const usernameSchema = z
    .string()
    .trim()
    .max(USERNAME_MAX, `Use at most ${USERNAME_MAX} characters`)
    .refine((value) => value === '' || isUsernameFormat(value), USERNAME_FORMAT_MESSAGE)
    .refine((value) => value === '' || !isUsernameReserved(value), USERNAME_RESERVED_MESSAGE);
