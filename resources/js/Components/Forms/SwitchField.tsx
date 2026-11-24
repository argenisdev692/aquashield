/**
 * SwitchField — boolean toggle with an inline label.
 *
 * The label sits beside the control, so FieldShell's stacked label is the
 * wrong shape. The hidden input keeps `name`, and the root carries
 * `aria-label` so the switch is announced even outside a Base UI Field.
 */
import { useId } from 'react';
import { Switch } from '@/Components/ui/switch';
import { cn } from '@/lib/utils';

interface SwitchFieldProps {
    label: string;
    name: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    description?: string;
    required?: boolean;
    disabled?: boolean;
}

export default function SwitchField({
    label,
    name,
    checked,
    onChange,
    onBlur,
    error,
    hint,
    description,
    required = false,
    disabled = false,
}: SwitchFieldProps) {
    const uid = useId();
    const inputId = `switch-${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;
    const hintId = `${inputId}-hint`;
    const errorId = `${inputId}-error`;
    const descriptionId = `${inputId}-description`;
    const describedBy =
        [description && descriptionId, hint && hintId, error && errorId]
            .filter(Boolean)
            .join(' ') || undefined;

    return (
        <div className="flex flex-col gap-1">
            <div className="flex items-center gap-2.5">
                <Switch
                    id={inputId}
                    name={name}
                    checked={checked}
                    onCheckedChange={(next) => onChange(next)}
                    onBlur={onBlur}
                    disabled={disabled}
                    required={required}
                    aria-label={label}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy}
                />

                <div className="flex flex-col gap-0.5">
                    <label
                        htmlFor={inputId}
                        className={cn(
                            'text-sm text-surface-700 dark:text-surface-200',
                            disabled && 'cursor-not-allowed opacity-60',
                        )}
                    >
                        {label}
                        {required && (
                            <>
                                <span className="text-aq-danger-strong" aria-hidden>
                                    *
                                </span>
                                <span className="sr-only">(required)</span>
                            </>
                        )}
                    </label>

                    {description && (
                        <p
                            id={descriptionId}
                            className="text-xs text-surface-600 dark:text-surface-300"
                        >
                            {description}
                        </p>
                    )}
                </div>
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
