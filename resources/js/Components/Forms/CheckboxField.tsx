/**
 * Binary checkbox with an inline label.
 * The label sits beside the box, not above it, so FieldShell is not used.
 * Error gets an id, role="alert" and aria-describedby on the input.
 */
import { useId } from 'react';
import { Checkbox } from '@/Components/ui/checkbox';

interface CheckboxFieldProps {
    label: string;
    name: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
}

export default function CheckboxField({
    label,
    name,
    checked,
    onChange,
    onBlur,
    error,
    hint,
    required = false,
    disabled = false,
}: CheckboxFieldProps) {
    const uid = useId();
    const inputId = `checkbox-${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;
    const hintId = `${inputId}-hint`;
    const errorId = `${inputId}-error`;

    const describedBy =
        [hint && !error && hintId, error && errorId].filter(Boolean).join(' ') || undefined;

    return (
        <div className="flex flex-col gap-1">
            <div className="flex items-start gap-2">
                <Checkbox
                    id={inputId}
                    name={name}
                    checked={checked}
                    onCheckedChange={(value: boolean) => onChange(value)}
                    disabled={disabled}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy}
                    onBlur={onBlur}
                    required={required}
                />
                <label htmlFor={inputId} className="text-sm text-surface-700 dark:text-surface-200">
                    {label}
                    {required && (
                        <>
                            <span className="text-destructive" aria-hidden>
                                *
                            </span>
                            <span className="sr-only">(required)</span>
                        </>
                    )}
                </label>
            </div>

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
        </div>
    );
}
