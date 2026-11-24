/**
 * Password field with an explicit show/hide control.
 * Controlled via value/onChange. Toggle is a real button with aria-pressed.
 */
import { useId, useRef, useState } from 'react';
import FieldShell from './FieldShell';
import { Input } from '@/Components/ui/input';
import { Eye, EyeOff } from 'lucide-react';
import { cn } from '@/lib/utils';

interface PasswordFieldProps {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    autocomplete?: string;
    placeholder?: string;
    disabled?: boolean;
    autoFocus?: boolean;
    maxLength?: number;
    minLength?: number;
}

export default function PasswordField({
    label,
    name,
    value,
    onChange,
    onBlur,
    error,
    hint,
    required = false,
    autocomplete = 'current-password',
    placeholder,
    disabled = false,
    autoFocus = false,
    maxLength,
    minLength,
}: PasswordFieldProps) {
    const uid = useId();
    const id = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;
    const [masked, setMasked] = useState(true);

    return (
        <FieldShell id={id} label={label} name={name} error={error} hint={hint} required={required}>
            {({ describedBy }) => (
                <div className="relative">
                    <Input
                        id={id}
                        name={name}
                        type={masked ? 'password' : 'text'}
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        onBlur={onBlur}
                        autoComplete={autocomplete}
                        placeholder={placeholder}
                        disabled={disabled}
                        autoFocus={autoFocus}
                        maxLength={maxLength}
                        minLength={minLength}
                        aria-invalid={error ? true : undefined}
                        aria-describedby={describedBy}
                        className={cn(
                            // 16px on touch widths — iOS Safari zooms a focused
                            // input under 16px, which leaves the whole card scaled.
                            'text-base lg:text-sm',
                            error
                                ? 'border-aq-danger placeholder:text-aq-danger-strong'
                                : undefined,
                            // The show/hide toggle rides at the end; keep the value clear of it.
                            'pr-10',
                        )}
                    />
                    <button
                        type="button"
                        className="absolute inset-y-0 end-0 flex items-center rounded-e-md px-3 text-surface-500 transition-colors duration-150 hover:text-surface-700 focus-visible:text-surface-700 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-primary-500 disabled:pointer-events-none disabled:opacity-50 dark:text-surface-400 dark:hover:text-surface-200 dark:focus-visible:text-surface-200 motion-reduce:transition-none"
                        disabled={disabled}
                        aria-label={masked ? 'Show password' : 'Hide password'}
                        aria-pressed={!masked}
                        onClick={() => setMasked((prev) => !prev)}
                    >
                        {masked ? (
                            <Eye className="size-4" aria-hidden="true" />
                        ) : (
                            <EyeOff className="size-4" aria-hidden="true" />
                        )}
                    </button>
                </div>
            )}
        </FieldShell>
    );
}
