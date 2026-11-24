/**
 * CurrencyField — money input that stores integer minor units (cents).
 *
 * Floats lose money. The model is therefore `number | null` in minor units by
 * default (`1999` = $19.99), and only the focused draft is shown in major
 * units. On blur the value snaps to the fully formatted currency; formatting
 * mid-keystroke would fight the caret.
 *
 * Pass `minorUnits={false}` only when the backend column really is a decimal.
 * `min` / `max` are in the same unit as the model.
 */
import { useEffect, useState } from 'react';
import FieldShell from './FieldShell';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
    InputGroupText,
} from '@/Components/ui/input-group';
import { cn } from '@/lib/utils';

interface CurrencyFieldProps {
    label: string;
    name: string;
    value: number | null;
    onChange: (value: number | null) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    currency?: string;
    locale?: string;
    minorUnits?: boolean;
    min?: number;
    max?: number;
    allowNegative?: boolean;
}

// Intl constructors load locale data; a currency field re-renders on every
// keystroke, so the formatters are cached by their inputs instead of being
// rebuilt in render.
const currencyFormatters = new Map<string, Intl.NumberFormat>();
const numberFormatters = new Map<string, Intl.NumberFormat>();
const decimalFormatters = new Map<string, Intl.NumberFormat>();

const currencyFormatter = (locale: string, currency: string): Intl.NumberFormat => {
    const key = `${locale}|${currency}`;
    const cached = currencyFormatters.get(key);

    if (cached) {
        return cached;
    }

    const formatter = new Intl.NumberFormat(locale, { style: 'currency', currency });
    currencyFormatters.set(key, formatter);

    return formatter;
};

const numberFormatter = (locale: string): Intl.NumberFormat => {
    const cached = numberFormatters.get(locale);

    if (cached) {
        return cached;
    }

    const formatter = new Intl.NumberFormat(locale);
    numberFormatters.set(locale, formatter);

    return formatter;
};

/**
 * The symbol lives in the input adornment, so the blurred value must be a
 * plain number: formatting it as currency here would print "$$19.99".
 */
const decimalFormatter = (locale: string, fractionDigits: number): Intl.NumberFormat => {
    const key = `${locale}|${fractionDigits}`;
    const cached = decimalFormatters.get(key);

    if (cached) {
        return cached;
    }

    const formatter = new Intl.NumberFormat(locale, {
        minimumFractionDigits: fractionDigits,
        maximumFractionDigits: fractionDigits,
    });
    decimalFormatters.set(key, formatter);

    return formatter;
};

export default function CurrencyField({
    label,
    name,
    value,
    onChange,
    onBlur,
    error,
    hint,
    required = false,
    disabled = false,
    placeholder = '0.00',
    currency = 'USD',
    locale = 'en-US',
    minorUnits = true,
    min,
    max,
    allowNegative = false,
}: CurrencyFieldProps) {
    const formatter = currencyFormatter(locale, currency);
    const fractionDigits = formatter.resolvedOptions().maximumFractionDigits ?? 2;
    const factor = 10 ** fractionDigits;
    const symbol =
        formatter.formatToParts(0).find((part) => part.type === 'currency')?.value ?? currency;

    const numberParts = numberFormatter(locale).formatToParts(1234567.89);
    const decimalSeparator = numberParts.find((part) => part.type === 'decimal')?.value ?? '.';

    const majorValue = value === null ? null : minorUnits ? value / factor : value;
    const formatted =
        majorValue === null ? '' : decimalFormatter(locale, fractionDigits).format(majorValue);

    const [focused, setFocused] = useState(false);
    const [draft, setDraft] = useState(majorValue === null ? '' : String(majorValue));

    // An external write (edit form seed, optimistic reset) must reach the
    // input, but a keystroke's own echo must not overwrite the draft.
    useEffect(() => {
        if (!focused) {
            setDraft(majorValue === null ? '' : String(majorValue));
        }
    }, [focused, majorValue]);

    const parseDraft = (raw: string): number | null => {
        const cleaned = raw.replace(/[^\d.,-]/g, '').trim();

        if (!cleaned || cleaned === '-' || cleaned === '.' || cleaned === ',') {
            return null;
        }

        const lastDot = cleaned.lastIndexOf('.');
        const lastComma = cleaned.lastIndexOf(',');
        let decimalIndex = -1;

        if (lastDot !== -1 && lastComma !== -1) {
            // Both conventions present: the rightmost one is the decimal.
            decimalIndex = Math.max(lastDot, lastComma);
        } else if (lastDot !== -1) {
            if (cleaned.indexOf('.') === lastDot) {
                // A single dot in a comma-decimal locale: "19.999" is grouping,
                // "19.99" is a decimal typed against the locale. Multiple dots
                // are always grouping.
                decimalIndex =
                    decimalSeparator === '.' || cleaned.length - lastDot - 1 !== 3 ? lastDot : -1;
            }
        } else if (lastComma !== -1) {
            if (cleaned.indexOf(',') === lastComma) {
                decimalIndex =
                    decimalSeparator === ',' || cleaned.length - lastComma - 1 !== 3
                        ? lastComma
                        : -1;
            }
        }

        let normalized: string;

        if (decimalIndex === -1) {
            normalized = cleaned.replace(/[.,]/g, '');
        } else {
            const integerPart = cleaned.slice(0, decimalIndex).replace(/[.,]/g, '');
            const fractionPart = cleaned.slice(decimalIndex + 1).replace(/[.,]/g, '');
            normalized = `${integerPart}.${fractionPart}`;
        }

        const parsed = Number.parseFloat(normalized);

        if (!Number.isFinite(parsed)) {
            return null;
        }

        return allowNegative ? parsed : Math.max(0, parsed);
    };

    const toModel = (major: number): number => (minorUnits ? Math.round(major * factor) : major);

    const clamp = (model: number): number => {
        let next = model;

        if (min !== undefined) {
            next = Math.max(next, min);
        }

        if (max !== undefined) {
            next = Math.min(next, max);
        }

        return next;
    };

    const commit = (raw: string): void => {
        const parsed = parseDraft(raw);
        onChange(parsed === null ? null : clamp(toModel(parsed)));
    };

    return (
        <FieldShell label={label} name={name} error={error} hint={hint} required={required}>
            {({ id, describedBy, invalid }) => (
                <InputGroup
                    className={cn('h-auto', error && 'border-aq-danger', disabled && 'opacity-60')}
                >
                    <InputGroupAddon>
                        <InputGroupText>{symbol}</InputGroupText>
                    </InputGroupAddon>

                    <InputGroupInput
                        id={id}
                        name={name}
                        type="text"
                        inputMode="decimal"
                        autoComplete="off"
                        value={focused ? draft : formatted}
                        placeholder={placeholder}
                        disabled={disabled}
                        aria-invalid={invalid || undefined}
                        aria-describedby={describedBy}
                        className="text-right tabular-nums"
                        onFocus={() => {
                            setFocused(true);
                            setDraft(majorValue === null ? '' : String(majorValue));
                        }}
                        onChange={(event) => {
                            const raw = event.target.value;
                            setDraft(raw);
                            // Push every parseable keystroke so a submit fired
                            // from the keyboard sees the current draft. The
                            // value is clamped on blur, not while typing, so
                            // typing "5" toward a min of 100 is not rewritten.
                            const parsed = parseDraft(raw);
                            onChange(parsed === null ? null : toModel(parsed));
                        }}
                        onBlur={() => {
                            setFocused(false);
                            commit(draft);
                            onBlur?.();
                        }}
                    />
                </InputGroup>
            )}
        </FieldShell>
    );
}
