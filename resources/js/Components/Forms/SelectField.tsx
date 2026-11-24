/**
 * Select field with label, hint and error already wired.
 * Controlled via value/onChange; options are { label, value }.
 */
import { useId } from 'react';
import { cn } from '@/lib/utils';
import FieldShell from './FieldShell';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

interface SelectOption {
    label: string;
    value: string | number;
}

interface SelectFieldProps {
    label: string;
    name: string;
    value: string | null;
    onChange: (value: string | null) => void;
    onBlur?: () => void;
    options: SelectOption[];
    error?: string;
    hint?: string;
    required?: boolean;
    placeholder?: string;
    disabled?: boolean;
}

export default function SelectField({
    label,
    name,
    value,
    onChange,
    onBlur,
    options,
    error,
    hint,
    required = false,
    placeholder,
    disabled = false,
}: SelectFieldProps) {
    const uid = useId();
    const id = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;
    const selectedLabel =
        value === null || value === undefined
            ? undefined
            : options.find((option) => String(option.value) === String(value))?.label;

    return (
        <FieldShell id={id} label={label} name={name} error={error} hint={hint} required={required}>
            {({ describedBy }) => (
                <Select
                    id={id}
                    name={name}
                    items={options}
                    value={value ?? null}
                    onValueChange={(v) => onChange(v)}
                    disabled={disabled}
                    required={required}
                >
                    <SelectTrigger
                        aria-describedby={describedBy}
                        aria-invalid={error ? true : undefined}
                        className={cn('h-10 w-full', error ? 'border-destructive' : undefined)}
                    >
                        <SelectValue placeholder={placeholder}>
                            {selectedLabel ?? placeholder}
                        </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                        {options.map((option) => (
                            <SelectItem key={String(option.value)} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            )}
        </FieldShell>
    );
}
