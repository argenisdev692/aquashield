/**
 * DateField — calendar date whose wire value is an ISO date (`YYYY-MM-DD`),
 * never a JavaScript `Date`. The browser control displays a localised string;
 * the form stores the date the server can validate without a timezone shift.
 */
import { useId } from 'react';
import FieldShell from './FieldShell';
import { Input } from '@/Components/ui/input';

interface DateFieldProps {
    label: string;
    name: string;
    value: string | null;
    onChange: (value: string | null) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    disabled?: boolean;
    min?: string;
    max?: string;
}

export default function DateField({
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
}: DateFieldProps) {
    const uid = useId();
    const id = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;

    return (
        <FieldShell id={id} label={label} name={name} error={error} hint={hint} required={required}>
            <Input
                id={id}
                name={name}
                type="date"
                value={value ?? ''}
                min={min}
                max={max}
                onChange={(event) =>
                    onChange(event.target.value === '' ? null : event.target.value)
                }
                onBlur={onBlur}
                disabled={disabled}
                aria-invalid={error ? true : undefined}
                className={error ? 'border-aq-danger' : undefined}
            />
        </FieldShell>
    );
}
