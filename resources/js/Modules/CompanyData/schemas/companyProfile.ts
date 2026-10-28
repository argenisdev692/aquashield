import { z } from 'zod';
import { isValidPhone } from '@/lib/phone';

/**
 * Client contract for the CompanyData singleton.
 *
 * Two shapes, one file: the form values are all strings (what an `<input>`
 * produces, and what Laravel's ConvertEmptyStringsToNull turns back into
 * `null`), while the server contract stays the generated `CompanyDataDto` —
 * never a hand-written mirror of it.
 *
 * Limits mirror `UpdateCompanyDataRequest` exactly. The server re-validates
 * everything; this schema only saves a round trip.
 */

type CompanyDataDto = Modules.CompanyData.Application.Dtos.CompanyDataDto;

/** Empty is allowed; anything else must be a full URL (the server rule is `url`). */
const optionalUrl = (message: string) =>
    z
        .string()
        .trim()
        .refine((value) => value === '' || z.url().safeParse(value).success, { error: message });

/** Empty is allowed; otherwise a finite number within ±limit (server: `between`). */
const optionalCoordinate = (limit: number, message: string) =>
    z
        .string()
        .trim()
        .refine(
            (value) => {
                if (value === '') {
                    return true;
                }

                const parsed = Number(value);

                return Number.isFinite(parsed) && Math.abs(parsed) <= limit;
            },
            { error: message },
        );

export const companyProfileSchema = z.object({
    name: z.string().trim().min(1, 'Enter the contact name').max(255, '255 characters maximum'),
    company_name: z
        .string()
        .trim()
        .min(1, 'Enter the company name')
        .max(255, '255 characters maximum'),
    email: z
        .string()
        .trim()
        .max(255, '255 characters maximum')
        .pipe(z.email('Enter a valid email')),
    phone: z
        .string()
        .trim()
        .refine((value) => value === '' || isValidPhone(value), {
            error: 'Enter a valid phone number',
        }),
    description: z.string().trim(),

    nif_nipc: z.string().trim().max(20, '20 characters maximum'),
    nie: z.string().trim().max(20, '20 characters maximum'),
    bank_beneficiary: z.string().trim().max(255, '255 characters maximum'),
    bank_iban: z.string().trim().max(34, '34 characters maximum'),
    bank_bic: z.string().trim().max(11, '11 characters maximum'),
    bank_name: z.string().trim().max(255, '255 characters maximum'),
    invoice_notes: z.string().trim(),

    address: z.string().trim(),
    address_2: z.string().trim(),
    zip_code: z.string().trim().max(10, '10 characters maximum'),
    city: z.string().trim().max(255, '255 characters maximum'),
    state: z.string().trim().max(255, '255 characters maximum'),
    country: z.string().trim().max(255, '255 characters maximum'),
    country_code: z
        .string()
        .trim()
        .refine((value) => value === '' || value.length === 2, {
            error: 'Use the two-letter ISO code, e.g. US',
        }),
    latitude: optionalCoordinate(90, 'Enter a latitude between -90 and 90'),
    longitude: optionalCoordinate(180, 'Enter a longitude between -180 and 180'),

    website: optionalUrl('Enter a full URL, including https://'),
    facebook_link: optionalUrl('Enter a full URL, including https://'),
    instagram_link: optionalUrl('Enter a full URL, including https://'),
    linkedin_link: optionalUrl('Enter a full URL, including https://'),
    twitter_link: optionalUrl('Enter a full URL, including https://'),
    tiktok_link: optionalUrl('Enter a full URL, including https://'),
});

export type CompanyProfileForm = z.infer<typeof companyProfileSchema>;

/** Nullable DTO → string form values. One direction only: the submit sends `form.data()`. */
export function toCompanyProfileForm(company: CompanyDataDto): CompanyProfileForm {
    const text = (value: string | null): string => value ?? '';
    const decimal = (value: number | null): string => (value === null ? '' : String(value));

    return {
        name: text(company.name),
        company_name: company.company_name,
        email: text(company.email),
        phone: text(company.phone),
        description: text(company.description),

        nif_nipc: text(company.nif_nipc),
        nie: text(company.nie),
        bank_beneficiary: text(company.bank_beneficiary),
        bank_iban: text(company.bank_iban),
        bank_bic: text(company.bank_bic),
        bank_name: text(company.bank_name),
        invoice_notes: text(company.invoice_notes),

        address: text(company.address),
        address_2: text(company.address_2),
        zip_code: text(company.zip_code),
        city: text(company.city),
        state: text(company.state),
        country: text(company.country),
        country_code: text(company.country_code),
        latitude: decimal(company.latitude),
        longitude: decimal(company.longitude),

        website: text(company.website),
        facebook_link: text(company.facebook_link),
        instagram_link: text(company.instagram_link),
        linkedin_link: text(company.linkedin_link),
        twitter_link: text(company.twitter_link),
        tiktok_link: text(company.tiktok_link),
    };
}
