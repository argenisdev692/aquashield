/**
 * Text input with label, hint and error already wired.
 * The value/onChange are controlled; validation stays in the page's Zod schema.
 */
import { useId } from 'react';
import FieldShell from './FieldShell';
import { Input } from '@/Components/ui/input';
import { cn } from '@/lib/utils';

interface TextFieldProps {
    label: string;
    name: string;
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    type?: 'text' | 'email' | 'password' | 'search' | 'tel' | 'url';
    autocomplete?: string;
    placeholder?: string;
    disabled?: boolean;
    readOnly?: boolean;
    autoFocus?: boolean;
    autoCapitalize?: 'none' | 'sentences' | 'words' | 'characters';
    inputMode?: 'text' | 'email' | 'tel' | 'numeric' | 'decimal' | 'search' | 'url';
    maxLength?: number;
    minLength?: number;
    spellCheck?: boolean;
    /**
     * Extra ids for `aria-describedby`, merged with the shell's hint/error ids.
     * Used by a wrapper that renders a second live message (e.g. the username
     * availability probe) and needs the input to own it.
     */
    describedBy?: string;
}

export default function TextField({
    label,
    name,
    value,
    onChange,
    onBlur,
    error,
    hint,
    required = false,
    type = 'text',
    autocomplete,
    placeholder,
    disabled = false,
    readOnly = false,
    autoFocus = false,
    autoCapitalize,
    inputMode,
    maxLength,
    minLength,
    spellCheck = false,
    describedBy: extraDescribedBy,
}: TextFieldProps) {
    const uid = useId();
    const id = `${name.replace(/[^a-zA-Z0-9_-]/g, '-')}-${uid}`;

    return (
        <FieldShell id={id} label={label} name={name} error={error} hint={hint} required={required}>
            {({ describedBy }) => (
                <Input
                    id={id}
                    name={name}
                    type={type}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    onBlur={onBlur}
                    autoComplete={autocomplete}
                    placeholder={placeholder}
                    disabled={disabled}
                    readOnly={readOnly}
                    autoFocus={autoFocus}
                    autoCapitalize={autoCapitalize}
                    inputMode={inputMode}
                    maxLength={maxLength}
                    minLength={minLength}
                    spellCheck={spellCheck}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={
                        [describedBy, extraDescribedBy].filter(Boolean).join(' ') || undefined
                    }
                    className={cn(
                        // 16px on touch widths: iOS Safari zooms the viewport when
                        // a focused input is under 16px, and the zoom survives the
                        // sign-in. Desktop keeps the 14px density from the ramp.
                        'text-base lg:text-sm',
                        readOnly &&
                            'bg-surface-50 text-surface-500 dark:bg-surface-800/60 dark:text-surface-300',
                        error && 'border-aq-danger placeholder:text-aq-danger-strong',
                    )}
                />
            )}
        </FieldShell>
    );
}
