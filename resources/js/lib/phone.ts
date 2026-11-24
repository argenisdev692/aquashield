import {
    AsYouType,
    getCountryCallingCode,
    getCountries,
    getExampleNumber,
    parsePhoneNumberFromString,
    validatePhoneNumberLength,
    type CountryCode,
} from 'libphonenumber-js';
import examples from 'libphonenumber-js/mobile/examples';
import { z } from 'zod';

/**
 * libphonenumber-js bindings kept outside the component so a Zod schema can
 * call the exact same rule the input formats with.
 *
 * The model the form receives is E.164 once the number is complete
 * (`+351912345678`) and an international draft while it is not
 * (`+3519123456789`). The draft keeps the calling code on purpose: the picker,
 * the formatter and the Zod rule all need to know which country the digits
 * belong to. A bare national string cannot carry that, and `912345678` parses
 * as a valid number in a different country — which is how a Portuguese number
 * used to be saved as an American one.
 */

export const DEFAULT_PHONE_COUNTRY: CountryCode = 'US';

const COUNTRY_CODES: ReadonlySet<string> = new Set(getCountries());

/** Narrow a string to a libphonenumber country code (upper-case ISO 3166-1). */
export const isCountryCode = (value: string): value is CountryCode =>
    COUNTRY_CODES.has(value.toUpperCase());

/**
 * Calling code → country, used while the number is still too short to parse.
 * Shared codes keep the product default: `+1` is the US, never whichever NANP
 * island `getCountries()` happens to return first.
 */
const COUNTRY_BY_CALLING_CODE = new Map<string, CountryCode>();

for (const code of getCountries()) {
    const callingCode = getCountryCallingCode(code);

    if (!COUNTRY_BY_CALLING_CODE.has(callingCode) || (callingCode === '1' && code === 'US')) {
        COUNTRY_BY_CALLING_CODE.set(callingCode, code);
    }
}

const regionNames =
    typeof Intl !== 'undefined' && 'DisplayNames' in Intl
        ? new Intl.DisplayNames(['en'], { type: 'region' })
        : null;

/** English country name for a code; the code itself when Intl cannot help. */
export function countryName(country: CountryCode): string {
    return regionNames?.of(country) ?? country;
}

/**
 * The national example for a country, used as the field placeholder and in a
 * validation message. Cached: `getExampleNumber` allocates a PhoneNumber and
 * the placeholder is read on every render of every phone field.
 */
const exampleCache = new Map<CountryCode, string>();

export function phoneExample(country: CountryCode): string {
    const cached = exampleCache.get(country);

    if (cached !== undefined) {
        return cached;
    }

    const example = getExampleNumber(country, examples)?.formatNational() ?? '';

    exampleCache.set(country, example);

    return example;
}

/**
 * The country an international value belongs to, even before the number is
 * long enough to parse: `+3519` is Portugal from the calling code alone.
 * A national value carries no country, so it returns `null`.
 */
export function detectPhoneCountry(value: string): CountryCode | null {
    const trimmed = value.trim();

    if (!trimmed.startsWith('+')) {
        return null;
    }

    const parsed = parsePhoneNumberFromString(trimmed);

    if (parsed?.country !== undefined) {
        return parsed.country;
    }

    const digits = phoneDigits(trimmed);
    let match: CountryCode | null = null;
    let matchLength = 0;

    for (const [callingCode, country] of COUNTRY_BY_CALLING_CODE) {
        if (callingCode.length > matchLength && digits.startsWith(callingCode)) {
            match = country;
            matchLength = callingCode.length;
        }
    }

    return match;
}

/**
 * The country a stored value belongs to. Only an international value carries
 * one; a legacy national value falls back to the caller's default.
 */
export function countryFromPhone(
    value: string,
    fallback: CountryCode = DEFAULT_PHONE_COUNTRY,
): CountryCode {
    return detectPhoneCountry(value) ?? fallback;
}

export function formatPhone(value: string, country: CountryCode = DEFAULT_PHONE_COUNTRY): string {
    if (value.trim() === '') {
        return '';
    }

    return new AsYouType(country).input(value);
}

/**
 * The value the phone input shows.
 *
 * A complete number is displayed in its national format ("912 345 678"): the
 * country already sits next to the flag in the picker, so repeating the prefix
 * inside the field says the same thing twice — and the field is narrower than
 * the number it would have to fit.
 *
 * An incomplete draft is presented the same way. The stored value always
 * carries the calling code, so the field strips it even when the digits are
 * not a valid number yet; that is what keeps the prefix from vanishing the
 * moment the user types one digit too many.
 *
 * The model stays E.164 once valid, so the schema, the server and the stored
 * record keep one canonical shape.
 */
export function formatPhoneDisplay(
    value: string,
    country: CountryCode = DEFAULT_PHONE_COUNTRY,
): string {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '';
    }

    const parsed = parsePhoneNumberFromString(trimmed);
    const digits = phoneDigits(trimmed);
    const callingCode = getCountryCallingCode(country);

    /*
     * A complete number is national once it belongs to the selected country —
     * or at least to the same calling code (`+1` is US and Canada).
     */
    if (
        parsed?.isValid() === true &&
        (parsed.country === country || parsed.countryCallingCode === callingCode)
    ) {
        return parsed.formatNational();
    }

    // A legacy national draft: format those digits, no prefix involved.
    if (!trimmed.startsWith('+')) {
        return new AsYouType(country).input(digits);
    }

    /*
     * An international draft whose calling code is still unknown (the user is
     * typing `+351`) stays visible with its `+`; swallowing it made the next
     * keystroke land in the national draft and the country was lost.
     */
    if (!digits.startsWith(callingCode)) {
        return `+${digits}`;
    }

    const national = digits.slice(callingCode.length);

    if (national === '') {
        return `+${callingCode}`;
    }

    return new AsYouType(country).input(national);
}

/**
 * The model for the form: E.164 for a complete number, an international draft
 * (`+<callingCode><digits>`) for anything shorter, so switching the flag or
 * validating under the right country never loses the number's origin.
 */
export function toPhoneDraft(value: string, country: CountryCode = DEFAULT_PHONE_COUNTRY): string {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '';
    }

    if (trimmed.startsWith('+')) {
        return parsePhoneNumberFromString(trimmed)?.number ?? trimmed;
    }

    const digits = phoneDigits(trimmed);

    if (digits === '') {
        return '';
    }

    return (
        parsePhoneNumberFromString(digits, country)?.number ??
        `+${getCountryCallingCode(country)}${digits}`
    );
}

export function toE164(value: string, country: CountryCode = DEFAULT_PHONE_COUNTRY): string | null {
    const parsed = parsePhoneNumberFromString(value, country);

    return parsed?.isValid() === true ? parsed.number : null;
}

/**
 * `country` is optional on purpose. With one, the value is read as a national
 * number for that country (the field's contract). Without one, only an
 * international value is accepted — plus the US-formatted values stored before
 * the field started emitting an international draft, which stay editable.
 */
export function isValidPhone(value: string, country?: CountryCode): boolean {
    const trimmed = value.trim();

    if (trimmed === '') {
        return false;
    }

    if (country !== undefined) {
        return parsePhoneNumberFromString(trimmed, country)?.isValid() === true;
    }

    if (trimmed.startsWith('+')) {
        return parsePhoneNumberFromString(trimmed)?.isValid() === true;
    }

    return parsePhoneNumberFromString(trimmed, DEFAULT_PHONE_COUNTRY)?.isValid() === true;
}

/**
 * Why a value is not a phone number, phrased in the user's terms: the country
 * and an example in its national format. `null` means "no problem".
 *
 * This is what turns "Enter a valid phone number" into "Too many digits for
 * Portugal. Example: 912 345 678." — the only actionable message when the
 * country has a fixed length and the user typed the wrong count.
 */
export function phoneValidationMessage(value: string, country?: CountryCode): string | null {
    const trimmed = value.trim();

    if (trimmed === '') {
        return null;
    }

    if (isValidPhone(trimmed, country)) {
        return null;
    }

    const resolved = country ?? detectPhoneCountry(trimmed) ?? DEFAULT_PHONE_COUNTRY;
    const name = countryName(resolved);
    const example = phoneExample(resolved);
    const suffix = example === '' ? '' : ` Example: ${example}.`;
    const length = validatePhoneNumberLength(trimmed, country);

    if (length === 'TOO_SHORT') {
        return `Too few digits for ${name}.${suffix}`;
    }

    if (length === 'TOO_LONG') {
        return `Too many digits for ${name}.${suffix}`;
    }

    if (length === 'NOT_A_NUMBER') {
        return 'That is not a phone number.';
    }

    return `Enter a valid ${name} phone number.${suffix}`;
}

/** Digits only, the part a phone number actually is. */
export function phoneDigits(value: string): string {
    return value.replace(/\D/g, '');
}

/**
 * The next draft for a formatted phone input.
 *
 * A formatted field shows separators the user never typed, so one backspace
 * often removes a space, a dash or a closing bracket instead of a digit — and
 * the formatter would put the character straight back. When that is what
 * happened, one more digit is dropped instead, which is what the user meant.
 */
export function nextPhoneDraft(current: string, raw: string): string {
    const currentDigits = phoneDigits(current);
    const rawDigits = phoneDigits(raw);

    // Nothing but a leading "+" is left: the raw wins, so the field can be
    // emptied instead of keeping the "+" forever.
    if (currentDigits === '') {
        return raw;
    }

    if (rawDigits.length === currentDigits.length && raw.length < current.length) {
        const trimmed = currentDigits.slice(0, -1);

        if (trimmed === '') {
            return '';
        }

        return current.trim().startsWith('+') ? `+${trimmed}` : trimmed;
    }

    return raw;
}

/** Empty is allowed; anything else is validated against its own country. */
export function phoneField(options: { country?: CountryCode; max?: number } = {}): z.ZodString {
    const { country, max = 32 } = options;

    return z
        .string()
        .trim()
        .max(max, `${max} characters maximum`)
        .superRefine((value, ctx) => {
            const message = phoneValidationMessage(value, country);

            if (message !== null) {
                ctx.addIssue({ code: 'custom', message });
            }
        });
}

/** The same rule the `PhoneField` uses, ready for a standalone form schema. */
export const phoneSchema = (country?: CountryCode) => phoneField({ country });
