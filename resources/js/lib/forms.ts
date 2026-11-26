/**
 * useZodForm — React port of the Vue form engine.
 *
 * Inertia owns the request and the server-side error bag; Zod owns the client
 * contract. Built on @inertiajs/react's useForm and Zod validation.
 *
 * The contract:
 *   const { form, errors, submit, touch, clearError } = useZodForm({ initial, schema })
 *
 * - `errors` merges server errors (authoritative) with client errors.
 * - `touch(name)` runs on blur and validates that field only.
 * - `clearError(name)` runs on input, so an error disappears when the user acts.
 * - `submit(action)` validates everything, then runs the Inertia call.
 */
import { useEffect, useState } from 'react';
import { useForm as useInertiaForm } from '@inertiajs/react';
import type { FormDataKeys, FormDataType } from '@inertiajs/core';
import type { ZodType } from 'zod';
import { HttpRequestError } from '@/lib/http';

export type FormValues = Record<string, unknown>;

export type FormErrorMap = Partial<Record<string, string>>;

export interface UseZodFormOptions<T extends FormDataType<T>> {
    initial: T;
    schema: ZodType<T>;
}

/**
 * Focus one control by its `name` attribute.
 *
 * `CSS.escape` keeps a dotted or bracketed field name (`user.email`,
 * `items[0]`) from breaking the selector. Shared by `FormErrorSummary`'s
 * per-field links and the overlay kit so there is one selector, not three.
 */
export function focusFieldByName(field: string): void {
    document.querySelector<HTMLElement>(`[name="${CSS.escape(field)}"]`)?.focus();
}

export function useZodForm<T extends FormDataType<T>>(options: UseZodFormOptions<T>) {
    const { schema, initial } = options;

    /*
     * Inertia v3's `useForm` takes the values object as its FIRST argument
     * (`useForm(data)`), not an options bag. Passing `{ data, refreshOnError }`
     * made the form data `{ data: {...}, refreshOnError: true }`: every field
     * read back `undefined`, React warned about uncontrolled inputs, and Zod
     * reported "expected string, received undefined" for untouched fields.
     */
    const inertiaForm = useInertiaForm<T>(initial);

    const [clientErrors, setClientErrors] = useState<FormErrorMap>({});
    /*
     * Increments on every `submit()` call. `FormErrorSummary` uses the counter
     * to focus itself only after a real submit, never after a blur validation
     * (which used to steal focus from the field the user was tabbing into).
     */
    const [submitAttempts, setSubmitAttempts] = useState(0);

    const errors: FormErrorMap = {};

    /*
     * Fortify validates profile, password and 2FA writes with named bags
     * (`updateProfileInformation`, `updatePassword`,
     * `confirmTwoFactorAuthentication`). Inertia shares those as
     * `{ bagName: { field: message } }`, while a default-bag 422 arrives as
     * `{ field: message }`. Flatten both shapes so the field kit and the error
     * summary always receive a field-keyed map.
     */
    const firstMessage = (raw: unknown): string | undefined => {
        if (typeof raw === 'string' && raw !== '') {
            return raw;
        }

        if (Array.isArray(raw)) {
            return raw.find((value): value is string => typeof value === 'string' && value !== '');
        }

        return undefined;
    };

    const isErrorBag = (value: unknown): value is Record<string, unknown> =>
        typeof value === 'object' && value !== null && !Array.isArray(value);

    for (const [key, raw] of Object.entries(inertiaForm.errors ?? {})) {
        const message = firstMessage(raw);

        if (message !== undefined) {
            errors[key] = message;
            continue;
        }

        if (!isErrorBag(raw)) {
            continue;
        }

        for (const [field, nestedRaw] of Object.entries(raw)) {
            const nestedMessage = firstMessage(nestedRaw);

            if (nestedMessage !== undefined && errors[field] === undefined) {
                errors[field] = nestedMessage;
            }
        }
    }

    for (const [key, value] of Object.entries(clientErrors)) {
        if (value !== undefined && errors[key] === undefined) {
            errors[key] = value;
        }
    }

    const hasErrors = Object.keys(errors).length > 0;

    const validate = () => {
        // Inertia's `data` is the plain values object (v3), not a getter.
        const result = schema.safeParse(inertiaForm.data);

        if (result.success) {
            setClientErrors({});
            return true;
        }

        const next: FormErrorMap = {};

        for (const issue of result.error.issues) {
            const key = issue.path[0];
            if (typeof key === 'string' && next[key] === undefined) {
                next[key] = issue.message;
            }
        }

        setClientErrors(next);
        return false;
    };

    const validateField = (field: Extract<keyof T, string>) => {
        const result = schema.safeParse(inertiaForm.data);
        const message = result.success
            ? undefined
            : result.error.issues.find((issue) => String(issue.path[0]) === field)?.message;

        setClientErrors((current) => {
            if (message === undefined) {
                if (!(field in current)) {
                    return current;
                }

                const next: FormErrorMap = { ...current };
                delete next[field];

                return next;
            }

            if (current[field] === message) {
                return current;
            }

            return { ...current, [field]: message };
        });

        return message === undefined;
    };

    const touch = (field: Extract<keyof T, string>) => {
        validateField(field);
    };

    const clearError = (field: Extract<keyof T, string>) => {
        /*
         * A functional update, not a copy of the render-time map: clearing two
         * fields in one event (the address autofill) must not make the second
         * call resurrect the first key from a stale snapshot.
         */
        setClientErrors((current) => {
            if (!(field in current)) {
                return current;
            }

            const next: FormErrorMap = { ...current };
            delete next[field];

            return next;
        });

        /*
         * Scope the server-error clear to this field. Clearing the whole bag
         * made typing in one input erase the error shown under another.
         *
         * A Fortify named bag (`updatePassword`, `updateProfileInformation`)
         * arrives as `{ bag: { field: message } }`, so `clearErrors(field)` is
         * a silent no-op for it — the flattening above would show the message
         * forever. Clear the owning bag (the smallest scope Inertia exposes)
         * before clearing the field itself.
         */
        for (const [bag, raw] of Object.entries(inertiaForm.errors ?? {})) {
            if (isErrorBag(raw) && Object.hasOwn(raw, field)) {
                inertiaForm.clearErrors(bag as FormDataKeys<T>);
            }
        }

        inertiaForm.clearErrors(field as FormDataKeys<T>);
    };

    /** The same contract for an atomic multi-field write (address autofill). */
    const clearErrors = (fields: readonly string[]) => {
        if (fields.length === 0) {
            return;
        }

        setClientErrors((current) => {
            let changed = false;
            const next: FormErrorMap = { ...current };

            for (const field of fields) {
                if (field in next) {
                    delete next[field];
                    changed = true;
                }
            }

            return changed ? next : current;
        });

        for (const [bag, raw] of Object.entries(inertiaForm.errors ?? {})) {
            if (isErrorBag(raw) && fields.some((field) => Object.hasOwn(raw, field))) {
                inertiaForm.clearErrors(bag as FormDataKeys<T>);
            }
        }

        for (const field of fields) {
            inertiaForm.clearErrors(field as FormDataKeys<T>);
        }
    };

    /*
     * `setData({ ... })` replaced the whole payload in Inertia v3.11: one
     * keystroke in a profile form collapsed `data` to a single key, and the
     * next render crashed an availability probe reading `email.trim()`. The
     * functional updater merges against `dataRef.current`, which is exactly
     * the contract these screens need.
     */
    const setField = <K extends Extract<keyof T, string>>(field: K, value: T[K]): void => {
        inertiaForm.setData((previous) => ({ ...previous, [field]: value }) as T);
        clearError(field);
    };

    /** One atomic write for an address selection (seven fields, one render). */
    const setFields = (patch: Partial<T>): void => {
        const fields = Object.keys(patch);

        if (fields.length === 0) {
            return;
        }

        inertiaForm.setData((previous) => ({ ...previous, ...patch }) as T);
        clearErrors(fields);
    };

    /*
     * `submit` must NOT focus here. It runs inside the submit event, before
     * React has committed the `aria-invalid` attributes a query would look for,
     * so on a first invalid submit (fields never blurred) it found nothing and
     * focus stayed on the submit button. `FormErrorSummary` focuses itself in
     * an effect, in the same commit that renders the errors — which also covers
     * a server 422 arriving after the visit. Its per-field links use
     * `focusFieldByName`.
     */
    const submit = (action: () => void) => {
        setSubmitAttempts((attempts) => attempts + 1);

        if (!validate()) {
            return;
        }
        action();
    };

    return {
        form: inertiaForm,
        errors,
        hasErrors,
        clientErrors,
        submitAttempts,
        validate,
        validateField,
        touch,
        clearError,
        clearErrors,
        setField,
        setFields,
        submit,
    };
}

/**
 * Warn before a full page unload while a form has unsaved changes.
 *
 * Inertia's SPA navigation does not fire `beforeunload`; this covers tab close,
 * reload and external links. A dialog-based SPA guard belongs to the page that
 * owns the form.
 */
export function useUnsavedChangesWarning(isDirty: boolean): void {
    useEffect(() => {
        if (!isDirty) {
            return;
        }

        const handler = (event: BeforeUnloadEvent): void => {
            event.preventDefault();
            event.returnValue = '';
        };

        window.addEventListener('beforeunload', handler);

        return () => window.removeEventListener('beforeunload', handler);
    }, [isDirty]);
}

/**
 * The 422 bag a JSON write produces, in the shape the form kit renders.
 *
 * `requestJson` throws `HttpRequestError` with Laravel's field bag for a
 * validation failure; anything else (a 429 with no body, a dropped connection
 * surfacing as `TypeError`) is not field-specific and belongs in the dialog's
 * alert region instead. A dialog that writes outside an Inertia visit needs
 * exactly this mapping, and it must be the same mapping every time.
 */
export interface ServerErrorState {
    readonly fieldErrors: Readonly<Record<string, string>>;
    readonly serverError: string | null;
}

/** The idle state — pass it to `useState` so "no error" is one named value. */
export const NO_SERVER_ERROR: ServerErrorState = { fieldErrors: {}, serverError: null };

export function serverErrorFrom(cause: unknown, fallback: string): ServerErrorState {
    if (cause instanceof HttpRequestError) {
        const fieldErrors = { ...cause.fieldErrors };

        return {
            fieldErrors,
            serverError: Object.keys(fieldErrors).length === 0 ? cause.message : null,
        };
    }

    return {
        fieldErrors: {},
        serverError: cause instanceof Error && cause.message !== '' ? cause.message : fallback,
    };
}
