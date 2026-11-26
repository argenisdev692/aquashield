/**
 * DatePickerField — calendar date whose wire value is ISO `YYYY-MM-DD`.
 *
 * The popover is the shadcn Calendar; the field never stores a JavaScript
 * `Date`, so a date cannot shift a day across the UTC boundary. Use `min` /
 * `max` (ISO strings) for hard bounds and `yearRange` for a date of birth.
 */
import { useState } from 'react';
import type { Matcher } from 'react-day-picker';
import { CalendarIcon, X } from 'lucide-react';
import FieldShell from './FieldShell';
import { Calendar } from '@/Components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { formatIsoDate, parseIsoDate, toIsoDate } from '@/lib/date';
import { cn } from '@/lib/utils';

interface DatePickerFieldProps {
    label: string;
    name: string;
    value: string | null;
    onChange: (value: string | null) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    clearable?: boolean;
    locale?: string;
    /** ISO `YYYY-MM-DD`. */
    min?: string;
    /** ISO `YYYY-MM-DD`. */
    max?: string;
    /** Switches the caption to a month/year dropdown, e.g. `[1900, 2026]`. */
    yearRange?: [number, number];
}

export default function DatePickerField({
    label,
    name,
    value,
    onChange,
    onBlur,
    error,
    hint,
    required = false,
    disabled = false,
    placeholder = 'Pick a date',
    clearable = true,
    locale = 'en-US',
    min,
    max,
    yearRange,
}: DatePickerFieldProps) {
    const [open, setOpen] = useState(false);

    const selected = parseIsoDate(value);
    const display = formatIsoDate(value, locale);

    const disabledDays: Matcher[] = [];

    const from = parseIsoDate(min);
    const to = parseIsoDate(max);

    if (from) {
        disabledDays.push({ before: from });
    }

    if (to) {
        disabledDays.push({ after: to });
    }

    return (
        <FieldShell label={label} name={name} error={error} hint={hint} required={required}>
            {({ id, describedBy, invalid }) => (
                <div className="relative">
                    <Popover
                        open={open}
                        onOpenChange={(next) => {
                            setOpen(next);

                            if (!next) {
                                onBlur?.();
                            }
                        }}
                    >
                        <PopoverTrigger
                            id={id}
                            name={name}
                            disabled={disabled}
                            aria-invalid={invalid || undefined}
                            aria-describedby={describedBy}
                            className={cn(
                                'flex min-h-10 w-full items-center gap-2 rounded-md border border-surface-300 bg-surface-0 px-3 py-2 text-left text-sm transition-colors duration-200',
                                'hover:border-surface-400 focus-visible:border-primary focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-primary',
                                'disabled:cursor-not-allowed disabled:bg-surface-200 disabled:text-surface-500',
                                'dark:border-surface-700 dark:bg-surface-950 dark:text-surface-0 dark:disabled:bg-surface-700',
                                !value && 'text-surface-500 dark:text-surface-300',
                                error && 'border-aq-danger',
                                clearable && value && !disabled && 'pr-9',
                            )}
                        >
                            <CalendarIcon
                                className="size-4 shrink-0 opacity-60"
                                aria-hidden="true"
                            />
                            <span className="flex-1 truncate">{display || placeholder}</span>
                        </PopoverTrigger>

                        <PopoverContent className="w-auto p-0" align="start">
                            <Calendar
                                mode="single"
                                selected={selected}
                                defaultMonth={selected}
                                onSelect={(date) => {
                                    onChange(date ? toIsoDate(date) : null);
                                    setOpen(false);
                                }}
                                disabled={disabledDays}
                                captionLayout={yearRange ? 'dropdown' : 'label'}
                                startMonth={
                                    yearRange ? new Date(yearRange[0], 0, 1, 12) : undefined
                                }
                                endMonth={
                                    yearRange ? new Date(yearRange[1], 11, 31, 12) : undefined
                                }
                                autoFocus
                            />
                        </PopoverContent>
                    </Popover>

                    {clearable && value && !disabled && (
                        <button
                            type="button"
                            aria-label="Clear date"
                            className="absolute top-1/2 right-2 flex size-6 -translate-y-1/2 items-center justify-center rounded-sm text-surface-500 transition-colors duration-150 hover:text-surface-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent dark:text-surface-300 dark:hover:text-surface-0"
                            onClick={() => {
                                onChange(null);
                                onBlur?.();
                            }}
                        >
                            <X className="size-4" aria-hidden="true" />
                        </button>
                    )}
                </div>
            )}
        </FieldShell>
    );
}
