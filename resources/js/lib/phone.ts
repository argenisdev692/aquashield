import { AsYouType, parsePhoneNumberFromString, type CountryCode } from 'libphonenumber-js';
import { z } from 'zod';

/**
 * libphonenumber-js bindings kept outside the component so a Zod schema can
 * call the exact same rule the input formats with.
 */

export const DEFAULT_PHONE_COUNTRY: CountryCode = 'US';

export function formatPhone(value: string, country: CountryCode = DEFAULT_PHONE_COUNTRY): string {
    if (value.trim() === '') {
        return '';
    }

    return new AsYouType(country).input(value);
}

export function toE164(value: string, country: CountryCode = DEFAULT_PHONE_COUNTRY): string | null {
    const parsed = parsePhoneNumberFromString(value, country);

    return parsed?.isValid() === true ? parsed.number : null;
}

export function isValidPhone(value: string, country: CountryCode = DEFAULT_PHONE_COUNTRY): boolean {
    return parsePhoneNumberFromString(value, country)?.isValid() === true;
}

/** The same rule the `PhoneField` uses, ready for a form schema. */
export const phoneSchema = (country: CountryCode = DEFAULT_PHONE_COUNTRY) =>
    z.string().refine((value) => isValidPhone(value, country), 'Enter a valid phone number');
