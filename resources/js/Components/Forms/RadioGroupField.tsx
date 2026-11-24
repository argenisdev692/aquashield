/**
 * RadioGroupField — a real `<fieldset>` + `<legend>` group.
 *
 * A radio group is one control, not N labels, so FieldShell's single
 * `<label for>` is the wrong contract here. The fieldset carries the group
 * name, the description and the invalid state; each option owns its own
 * label.
 */
import { useId } from 'react';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { cn } from '@/lib/utils';

export interface RadioGroupOption {
    label: string;
    value: string | number;
    description?: string;
    disabled?: boolean;
}

interface RadioGroupFieldProps {
    label: string;
    name: string;
    value: string | number | null;
    onChange: (value: string | number) => void;
    options: RadioGroupOption[];
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    orientation?: 'vertical' | 'horizontal';
}

export default function RadioGroupField({
    label,
    name,
    value,
    onChange,
    options,
    onBlur,
    error,
    hint,
    required = false,
    disabled = false,
    orientation = 'vertical',
}: RadioGroupFieldProps) {
    const uid = useId();
    const baseId = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;
    const hintId = `${baseId}-hint`;
    const errorId = `${baseId}-error`;
    const describedBy = [hint && hintId, error && errorId].filter(Boolean).join(' ') || undefined;
    const invalid = Boolean(error);

    return (
        <fieldset
            aria-describedby={describedBy}
            aria-invalid={invalid || undefined}
            disabled={disabled}
            className="flex min-w-0 flex-col gap-1.5 border-0 p-0"
            onBlur={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget as Node | null)) {
                    onBlur?.();
                }
            }}
        >
            <legend className="text-sm font-medium text-surface-800 dark:text-surface-100">
                {label}
                {required && (
                    <>
                        <span className="text-aq-danger-strong" aria-hidden>
                            *
                        </span>
                        <span className="sr-only">(required)</span>
                    </>
                )}
            </legend>

            <RadioGroup<string | number>
                name={name}
                value={value ?? undefined}
                onValueChange={(next) => onChange(next)}
                disabled={disabled}
                required={required}
                className={cn(
                    'mt-1 gap-2',
                    orientation === 'horizontal' && 'flex flex-wrap gap-x-4',
                )}
            >
                {options.map((option) => {
                    const optionId = `${baseId}-${String(option.value).replace(/[^a-zA-Z0-9_-]/g, '-')}`;
                    const descriptionId = option.description
                        ? `${optionId}-description`
                        : undefined;

                    return (
                        <label
                            key={optionId}
                            htmlFor={optionId}
                            className={cn(
                                'flex items-start gap-2 text-sm text-surface-700 dark:text-surface-200',
                                option.disabled && 'cursor-not-allowed opacity-60',
                                !option.disabled && 'cursor-pointer',
                            )}
                        >
                            <RadioGroupItem
                                id={optionId}
                                value={option.value}
                                disabled={option.disabled}
                                aria-describedby={descriptionId}
                                className="mt-0.5"
                            />

                            <span className="flex flex-col gap-0.5">
                                <span>{option.label}</span>

                                {option.description && (
                                    <span
                                        id={descriptionId}
                                        className="text-xs text-surface-600 dark:text-surface-300"
                                    >
                                        {option.description}
                                    </span>
                                )}
                            </span>
                        </label>
                    );
                })}
            </RadioGroup>

            {hint && !error && (
                <p id={hintId} className="text-xs text-surface-600 dark:text-surface-300">
                    {hint}
                </p>
            )}

            {error && (
                <p id={errorId} role="alert" className="text-xs font-medium text-aq-danger-strong">
                    {error}
                </p>
            )}
        </fieldset>
    );
}
