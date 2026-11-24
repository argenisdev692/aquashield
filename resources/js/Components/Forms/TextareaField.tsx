/**
 * TextareaField — multi-line text input with label, hint and error.
 * Controlled via value/onChange.
 */
import { useId } from 'react';
import FieldShell from './FieldShell';
import { Textarea } from '@/Components/ui/textarea';

interface TextareaFieldProps {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    placeholder?: string;
    disabled?: boolean;
    rows?: number;
}

export default function TextareaField({
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
    rows = 4,
}: TextareaFieldProps) {
    const uid = useId();
    const id = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;

    return (
        <FieldShell id={id} label={label} name={name} error={error} hint={hint} required={required}>
            {({ describedBy }) => (
                <Textarea
                    id={id}
                    name={name}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    onBlur={onBlur}
                    placeholder={placeholder}
                    disabled={disabled}
                    rows={rows}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy}
                    className={error ? 'border-destructive' : undefined}
                />
            )}
        </FieldShell>
    );
}
