/**
 * FieldShell — label + control + hint + error with ARIA wiring.
 * React port of the Vue Form Kit FieldShell.
 *
 * Provides ids derived from the field name so FormErrorSummary can link
 * to the control, and hint/error ids referenced through aria-describedby.
 */
import { useId } from 'react';
import { cn } from '@/lib/utils';

export interface FieldShellRenderProps {
    id: string;
    describedBy?: string;
    invalid: boolean;
}

interface FieldShellProps {
    label: string;
    name?: string;
    hint?: string;
    error?: string;
    required?: boolean;
    invalid?: boolean;
    id?: string;
    /**
     * Layout overrides for the whole shell. Only the OTP field uses it today:
     * a segmented code sits centred on the card, so its label and error have
     * to share that axis or the block reads as two left/centre halves.
     */
    className?: string;
    children: React.ReactNode | ((props: FieldShellRenderProps) => React.ReactNode);
}

export default function FieldShell({
    label,
    name = 'field',
    hint,
    error,
    required = false,
    invalid = false,
    id: propId,
    className,
    children,
}: FieldShellProps) {
    const uid = useId();
    const safeName = name.replace(/[^a-zA-Z0-9_-]/g, '-');
    const id = propId || `${safeName}-${uid}`;
    const hintId = `${id}-hint`;
    const errorId = `${id}-error`;

    const describedBy =
        [hint && !error ? hintId : undefined, error ? errorId : undefined]
            .filter(Boolean)
            .join(' ') || undefined;

    const isInvalid = invalid || Boolean(error);

    return (
        <div className={cn('flex flex-col gap-1.5', className)}>
            <label
                htmlFor={id}
                className="text-sm font-medium text-surface-800 dark:text-surface-100"
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

            {typeof children === 'function'
                ? children({ id, describedBy, invalid: isInvalid })
                : children}

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
