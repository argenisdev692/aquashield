/**
 * AvailabilityField — a TextField with the debounced uniqueness signal.
 *
 * The field never owns the request: it renders the state produced by
 * `useUserAvailability` (a module hook). The inline message is paired with an
 * icon, never colour alone, and the same message is announced politely.
 */
import { useId } from 'react';
import { CheckCircle2, Loader2, XCircle } from 'lucide-react';
import TextField from '@/Components/Forms/TextField';
import { cn } from '@/lib/utils';
import type { FieldAvailability } from '@/Modules/Users/hooks/useUserAvailability';

interface AvailabilityFieldProps {
    label: string;
    name: string;
    type: 'text' | 'email';
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    error?: string;
    hint?: string;
    required?: boolean;
    autocomplete?: string;
    availability: FieldAvailability;
}

export default function AvailabilityField({
    label,
    name,
    type,
    value,
    onChange,
    onBlur,
    error,
    hint,
    required = false,
    autocomplete,
    availability,
}: AvailabilityFieldProps) {
    const visible = error === undefined && availability.state !== 'idle';
    const messageId = useId();

    return (
        <div className="flex flex-col gap-1.5">
            <TextField
                label={label}
                name={name}
                type={type}
                value={value}
                onChange={onChange}
                onBlur={onBlur}
                error={error}
                hint={hint}
                required={required}
                autocomplete={autocomplete}
                autoCapitalize="none"
                spellCheck={false}
                describedBy={visible ? messageId : undefined}
            />

            {visible ? (
                <p
                    id={messageId}
                    aria-live="polite"
                    className={cn(
                        'flex items-center gap-1.5 text-xs font-medium',
                        availability.state === 'available' && 'text-aq-success-strong',
                        availability.state === 'unavailable' && 'text-aq-danger-strong',
                        availability.state === 'checking' &&
                            'text-surface-600 dark:text-surface-300',
                        availability.state === 'error' && 'text-aq-danger-strong',
                    )}
                >
                    {availability.state === 'checking' ? (
                        <Loader2
                            className="size-3.5 animate-spin motion-reduce:animate-none"
                            aria-hidden="true"
                        />
                    ) : availability.state === 'available' ? (
                        <CheckCircle2 className="size-3.5" aria-hidden="true" />
                    ) : (
                        <XCircle className="size-3.5" aria-hidden="true" />
                    )}
                    {availability.message}
                </p>
            ) : null}
        </div>
    );
}
