import { z } from 'zod';

/**
 * Client mirror of Modules\LeadAppointment\Http\Rules\PersonName and the
 * Title Case normalization in NormalizesPersonName.
 *
 * The server stays authoritative; this copy exists so the lead forms fail fast
 * with the same 50-character, letters-and-spaces contract. Keep the pattern,
 * the bound and the casing rule in lockstep with the PHP pair.
 */
export const PERSON_NAME_MAX = 50;

export const PERSON_NAME_PATTERN = /^[\p{L}]+(?: [\p{L}]+)*$/u;

export const PERSON_NAME_FORMAT_MESSAGE = 'Use letters and spaces only.';
export const PERSON_NAME_LENGTH_MESSAGE = `Use at most ${PERSON_NAME_MAX} characters, spaces included.`;

/** Drop anything that is not a Unicode letter or a space while typing. */
export const filterPersonNameInput = (value: string): string => value.replace(/[^\p{L} ]/gu, '');

/**
 * "  pedro  pablo " → "Pedro Pablo". Casing is applied on blur and on the
 * server, never mid-keystroke, so the caret never jumps while typing.
 */
export const normalizePersonName = (value: string): string =>
    value
        .normalize('NFC')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase()
        .replace(
            /(^|\s)(\p{L})/gu,
            (_match, prefix: string, letter: string) =>
                `${prefix}${letter.toLocaleUpperCase('en-US')}`,
        );

export const personNameSchema = (label: string) =>
    z
        .string()
        .trim()
        .min(1, `Enter a ${label}`)
        .max(PERSON_NAME_MAX, PERSON_NAME_LENGTH_MESSAGE)
        .regex(PERSON_NAME_PATTERN, PERSON_NAME_FORMAT_MESSAGE);
