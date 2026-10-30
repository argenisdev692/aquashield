import { z } from 'zod';

/**
 * Anti-spam allowed email domains (robust, ORM-safe, no N+1).
 * Only well-known consumer / business providers; disposable domains blocked.
 */
export const ALLOWED_DOMAINS: string[] = [
    'gmail.com',
    'googlemail.com',
    'outlook.com',
    'outlook.es',
    'hotmail.com',
    'live.com',
    'msn.com',
    'proton.me',
    'protonmail.com',
    'protonmail.ch',
    'yahoo.com',
    'yahoo.es',
    'icloud.com',
    'me.com',
    'mac.com',
    'aol.com',
    'fastmail.com',
    'zoho.com',
    'zoho.eu',
    'company.com',
];

export const BLOCKED_DOMAINS: string[] = [
    'mailinator.com',
    '10minutemail.com',
    'guerrillamail.com',
    'maildrop.cc',
    'mailnesia.com',
    'yopmail.com',
    'tempmail.net',
    'disposable.com',
    'throwawaymail.com',
    'fakeinbox.com',
    'sharklasers.com',
    'spam4.me',
    'getairmail.com',
];

export const emailSchema = z
    .string()
    .trim()
    .email('Enter a valid email address')
    .max(255)
    .refine(
        (value) => {
            const atPos = value.lastIndexOf('@');
            if (atPos === -1 || atPos === 0 || atPos === value.length - 1) {
                return false;
            }
            const domain = value.slice(atPos + 1).toLowerCase();
            if (BLOCKED_DOMAINS.includes(domain)) {
                return false;
            }
            return ALLOWED_DOMAINS.includes(domain);
        },
        'The email domain is not allowed. Use a known provider (gmail, outlook, proton, yahoo, etc.).',
    );
