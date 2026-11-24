/**
 * PhoneField — international telephone input backed by libphonenumber-js.
 *
 * The visible value is formatted as the user types (`AsYouType`); the model is
 * E.164 once valid and an international draft (`+3519123456789`) while it is
 * not. The draft keeps the calling code so the picker, the formatter and the
 * Zod rule always agree on the country; a bare national draft could parse as a
 * valid number somewhere else.
 *
 * The country picker carries the flag and the calling code, because the
 * product rule is "include the country code". Its initial country comes from
 * the stored value first (a Portuguese number opens on Portugal even when the
 * company address says otherwise) and from the `country` prop second.
 *
 * Picking another country re-reads the stored number as a national number for
 * the new country, so the field never shows one country's prefix next to
 * another's digits. Typing or pasting a number with its own `+351` prefix
 * moves the picker instead: the number's country wins over the previous choice.
 */
import { useId, useState } from 'react';
import { parsePhoneNumberFromString, type CountryCode } from 'libphonenumber-js';
import FieldShell from './FieldShell';
import CountryPicker from './CountryPicker';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/Components/ui/input-group';
import {
    DEFAULT_PHONE_COUNTRY,
    countryFromPhone,
    detectPhoneCountry,
    formatPhoneDisplay,
    nextPhoneDraft,
    phoneExample,
    toPhoneDraft,
} from '@/lib/phone';

interface PhoneFieldProps {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    /** Defaults to the example number of the selected country. */
    placeholder?: string;
    disabled?: boolean;
    /** Initial country when the value is empty; `US` is the product default. */
    country?: CountryCode;
    /** Optional: the parent owns the country (e.g. to persist it). */
    onCountryChange?: (country: CountryCode) => void;
}

export default function PhoneField({
    label,
    name,
    value,
    onChange,
    onBlur,
    error,
    hint,
    required = false,
    placeholder,
    disabled = false,
    country: countryProp = DEFAULT_PHONE_COUNTRY,
    onCountryChange,
}: PhoneFieldProps) {
    const uid = useId();
    const id = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;

    /*
     * The stored value is the best evidence of the country: a `+351` number
     * belongs to Portugal regardless of what the picker default says.
     */
    const [country, setCountry] = useState<CountryCode>(() => countryFromPhone(value, countryProp));

    const handleChange = (raw: string): void => {
        // A number typed or pasted with its own prefix overrides the picker.
        // `00` is the international access prefix in most of the world.
        const international = raw.trim().startsWith('00')
            ? `+${raw.trim().slice(2)}`
            : raw.trim().startsWith('+')
              ? raw.trim()
              : null;
        const detected = international === null ? null : detectPhoneCountry(international);

        let nextCountry = country;

        if (detected !== null && detected !== country) {
            nextCountry = detected;
            setCountry(detected);
            onCountryChange?.(detected);
        }

        // The field shows the national format, so a backspace can land on a
        // separator. `nextPhoneDraft` decides whether the user removed a
        // separator (then one more digit goes) or a digit (then it is kept).
        const draft = nextPhoneDraft(formatPhoneDisplay(value, country), raw);
        const normalized = draft.startsWith('00') ? `+${draft.slice(2)}` : draft;

        onChange(toPhoneDraft(normalized, nextCountry));
    };

    /**
     * Picking another country re-reads the stored number as a national number
     * for that country. The digits are what the user typed; the prefix is the
     * only thing the picker changes.
     */
    const handleCountryChange = (next: CountryCode): void => {
        setCountry(next);
        onCountryChange?.(next);

        if (value.trim() === '') {
            return;
        }

        const national = parsePhoneNumberFromString(value)?.nationalNumber;

        onChange(toPhoneDraft(national ?? value, next));
    };

    const resolvedPlaceholder = placeholder ?? phoneExample(country);

    return (
        <FieldShell id={id} label={label} name={name} error={error} hint={hint} required={required}>
            {({ describedBy, invalid }) => (
                <InputGroup className="h-auto">
                    <InputGroupAddon
                        align="inline-start"
                        className="border-r border-surface-200/80 pr-0.5 dark:border-surface-700"
                    >
                        <CountryPicker
                            value={country}
                            onChange={handleCountryChange}
                            disabled={disabled}
                        />
                    </InputGroupAddon>

                    <InputGroupInput
                        id={id}
                        name={name}
                        type="tel"
                        value={formatPhoneDisplay(value, country)}
                        onChange={(event) => handleChange(event.target.value)}
                        onBlur={onBlur}
                        placeholder={resolvedPlaceholder}
                        disabled={disabled}
                        inputMode="tel"
                        autoComplete="tel"
                        spellCheck={false}
                        maxLength={24}
                        aria-invalid={invalid ? true : undefined}
                        aria-describedby={describedBy}
                    />
                </InputGroup>
            )}
        </FieldShell>
    );
}
