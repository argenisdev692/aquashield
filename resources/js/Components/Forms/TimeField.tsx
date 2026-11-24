/**
 * TimeField — a wall-clock time whose wire value is `HH:MM`, never a Date.
 * The browser control is localised; the form keeps the value the server's
 * `date_format:H:i` rule reads without a timezone conversion.
 */
import { useId } from 'react';
import FieldShell from './FieldShell';
import { Input } from '@/Components/ui/input';
import { cn } from '@/lib/utils';

interface TimeFieldProps {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    min?: string;
    max?: string;
    step?: number;
}

export default function TimeField({
    label,
    name,
    value,
    onChange,
    onBlur,
    error,
    hint,
    required = false,
    disabled = false,
    min,
    max,
    step = 300,
}: TimeFieldProps) {
    const uid = useId();
    const id = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;

    return (
        <FieldShell id={id} label={label} name={name} error={error} hint={hint} required={required}>
            <Input
                id={id}
                name={name}
                type="time"
                value={value}
                min={min}
                max={max}
                step={step}
                onChange={(event) => onChange(event.target.value)}
                onBlur={onBlur}
                disabled={disabled}
                aria-invalid={error ? true : undefined}
                className={cn(error && 'border-aq-danger')}
            />
        </FieldShell>
    );
}
