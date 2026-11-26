/**
 * FormErrorSummary — lists every form error, announced once, with a link that
 * focuses the offending control.
 *
 * Inline errors already sit under each field; the summary exists for the case a
 * screen-reader user never reaches them: a long form, a server 422, or a
 * keyboard user who submitted with Enter from the last field. `role="alert"`
 * announces the block, and each item focuses its control by `name`.
 *
 * A message alone ("Enter a valid email address") does not say which field it
 * belongs to. Each entry therefore prefixes the humanised field label.
 *
 * The block focuses ITSELF when errors appear. That has to happen in an effect,
 * not in the submit handler: the handler runs before React commits the fields'
 * `aria-invalid`, so a `[aria-invalid="true"]` query there matches nothing on
 * the first failure. Focusing the container is also the safer target — it
 * exists in the same commit as the messages, and it announces the whole list.
 */
import { useEffect, useRef } from 'react';
import { focusFieldByName } from '@/lib/forms';

interface FormErrorSummaryProps {
    errors: Record<string, string | undefined>;
    /** Optional field → label map. Falls back to a humanised field name. */
    labels?: Record<string, string>;
    /** Defaults to focusing the control by its `name` attribute. */
    onFieldFocus?: (field: string) => void;
    /**
     * The `submitAttempts` counter from `useZodForm`. The summary focuses itself
     * only when this counter advances and errors exist — never when a blur
     * validation adds a first error, which used to steal focus from the field
     * the user was tabbing into. Defaults to 0 (no automatic focus).
     */
    submitAttempts?: number;
}

const humanize = (field: string): string =>
    field
        .replace(/[_-]+/g, ' ')
        .replace(/([a-z\d])([A-Z])/g, '$1 $2')
        .replace(/^\w/, (character) => character.toUpperCase());

export default function FormErrorSummary({
    errors,
    labels,
    onFieldFocus = focusFieldByName,
    submitAttempts = 0,
}: FormErrorSummaryProps) {
    const keys = Object.keys(errors);
    const containerRef = useRef<HTMLDivElement>(null);
    const lastSubmitAttempt = useRef(0);

    useEffect(() => {
        if (submitAttempts <= lastSubmitAttempt.current) {
            return;
        }

        lastSubmitAttempt.current = submitAttempts;

        if (keys.length > 0) {
            containerRef.current?.focus();
        }
    }, [submitAttempts, keys.length]);

    if (keys.length === 0) {
        return null;
    }

    return (
        <div
            ref={containerRef}
            role="alert"
            tabIndex={-1}
            className="rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2.5 text-sm"
        >
            <p className="font-medium text-aq-danger-strong">
                Please fix the following {keys.length === 1 ? 'error' : 'errors'}:
            </p>

            <ul className="mt-1 list-disc space-y-0.5 ps-5">
                {keys.map((field) => (
                    <li key={field} className="text-aq-danger-strong">
                        <button
                            type="button"
                            onClick={() => onFieldFocus(field)}
                            className="text-left underline underline-offset-2 transition-colors duration-150 hover:text-aq-danger focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none"
                        >
                            <span className="font-medium">
                                {labels?.[field] ?? humanize(field)}:
                            </span>{' '}
                            {errors[field]}
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
