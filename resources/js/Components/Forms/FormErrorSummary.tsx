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
 */

interface FormErrorSummaryProps {
    errors: Record<string, string | undefined>;
    /** Optional field → label map. Falls back to a humanised field name. */
    labels?: Record<string, string>;
    onFieldFocus: (field: string) => void;
}

const humanize = (field: string): string =>
    field
        .replace(/[_-]+/g, ' ')
        .replace(/([a-z\d])([A-Z])/g, '$1 $2')
        .replace(/^\w/, (character) => character.toUpperCase());

export default function FormErrorSummary({ errors, labels, onFieldFocus }: FormErrorSummaryProps) {
    const keys = Object.keys(errors);

    if (keys.length === 0) {
        return null;
    }

    return (
        <div
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
                            className="text-left underline underline-offset-2 transition-colors duration-150 hover:text-aq-danger focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none"
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
