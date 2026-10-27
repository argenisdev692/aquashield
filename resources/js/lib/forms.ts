import { computed, nextTick, ref, type Ref } from 'vue';
import type { FormDataConvertible } from '@inertiajs/core';
import { useForm } from '@inertiajs/vue3';
import type { ZodType } from 'zod';

/**
 * One form engine for the whole app.
 *
 * Inertia owns the request and the server-side error bag; Zod owns the client
 * contract. Keeping both in one composable is what stops the three historical
 * patterns (manual `safeParse`, native constraint validation, and a resolver
 * nobody used) from reappearing page by page.
 *
 * The contract:
 *   const { form, errors, submit, touch, clearError, formEl } = useZodForm(...)
 *
 * - `errors` merges server errors (authoritative) with client errors.
 * - `touch(name)` runs on blur and validates that field only.
 * - `clearError(name)` runs on input, so an error disappears when the user acts.
 * - `submit(action)` validates everything, focuses the first invalid control,
 *   and only then runs the Inertia call.
 */

export type FormValues = Record<string, FormDataConvertible>;

export type FormErrorMap = Partial<Record<string, string>>;

/**
 * The subset of Inertia's form API this composable uses, typed over `T`.
 *
 * Inertia's `InertiaForm<T>` expands through a recursive `FormDataType` that
 * hits TypeScript's instantiation-depth limit for a generic `T`. This interface
 * keeps field access typed (`form.email`) without repeating that expansion.
 */
export type ZodFormApi<T extends FormValues> = T & {
    errors: Partial<Record<keyof T & string, string>>;
    processing: boolean;
    hasErrors: boolean;
    isDirty: boolean;
    data: () => T;
    clearErrors: () => void;
    reset: (...fields: (keyof T & string)[]) => void;
    post: (url: string, options?: Record<string, unknown>) => void;
    put: (url: string, options?: Record<string, unknown>) => void;
    patch: (url: string, options?: Record<string, unknown>) => void;
    delete: (url: string, options?: Record<string, unknown>) => void;
};

export interface UseZodFormOptions<T extends FormValues> {
    initial: T;
    schema: ZodType<T>;
    /** The page's `useTemplateRef('form')`; keeps the 3.5 template-ref rule. */
    formEl?: Readonly<Ref<HTMLFormElement | null>>;
}

export function useZodForm<T extends FormValues>(options: UseZodFormOptions<T>) {
    /**
     * Inertia's one-argument overload resolves `TForm` through a recursive
     * `FormDataType` constraint that a generic `T` cannot satisfy. The runtime
     * shape is exact, so the cast stays at this single seam instead of leaking
     * `unknown` into every page.
     */
    const createForm = useForm as unknown as (data: FormValues) => unknown;
    const form = createForm(options.initial) as ZodFormApi<T>;
    const formEl = options.formEl ?? ref<HTMLFormElement | null>(null);
    const clientErrors = ref<FormErrorMap>({});

    const errors = computed<FormErrorMap>(() => {
        const merged: FormErrorMap = {};

        for (const [key, value] of Object.entries(form.errors)) {
            const message = Array.isArray(value) ? value[0] : value;

            if (typeof message === 'string' && message !== '') {
                merged[key] = message;
            }
        }

        for (const [key, value] of Object.entries(clientErrors.value)) {
            if (value !== undefined && merged[key] === undefined) {
                merged[key] = value;
            }
        }

        return merged;
    });

    const hasErrors = computed<boolean>(() => Object.keys(errors.value).length > 0);

    const focusFirstError = async (): Promise<void> => {
        await nextTick();

        const target =
            formEl.value?.querySelector<HTMLElement>('[aria-invalid="true"]') ??
            formEl.value?.querySelector<HTMLElement>('input, select, textarea');

        target?.focus();
    };

    const issuesFor = (field: string): string | undefined => {
        const result = options.schema.safeParse(form.data());

        if (result.success) {
            return undefined;
        }

        return result.error.issues.find((issue) => issue.path[0] === field)?.message;
    };

    const validateField = (field: string): boolean => {
        const message = issuesFor(field);
        const next: FormErrorMap = { ...clientErrors.value };

        if (message === undefined) {
            delete next[field];
        } else {
            next[field] = message;
        }

        clientErrors.value = next;

        return message === undefined;
    };

    const validate = (): boolean => {
        const result = options.schema.safeParse(form.data());

        if (result.success) {
            clientErrors.value = {};
            return true;
        }

        const next: FormErrorMap = {};

        for (const issue of result.error.issues) {
            const key = issue.path[0];

            if (typeof key === 'string' && next[key] === undefined) {
                next[key] = issue.message;
            }
        }

        clientErrors.value = next;

        return false;
    };

    const touch = (field: string): void => {
        validateField(field);
    };

    const clearError = (field: keyof T & string): void => {
        if (clientErrors.value[field] !== undefined) {
            const next: FormErrorMap = { ...clientErrors.value };
            delete next[field];
            clientErrors.value = next;
        }

        // Server errors are authoritative; clearing all of them on input is the
        // safe direction. A per-key clear types as `never` for a generic form.
        form.clearErrors();
    };

    const submit = (action: () => void): void => {
        if (!validate()) {
            void focusFirstError();
            return;
        }

        action();
    };

    return {
        form,
        formEl,
        errors,
        hasErrors,
        clientErrors,
        validate,
        validateField,
        touch,
        clearError,
        focusFirstError,
        submit,
    };
}
