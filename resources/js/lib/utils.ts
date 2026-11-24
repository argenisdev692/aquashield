import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * App-level class composition (`clsx` + `tailwind-merge`).
 *
 * Keep `cn()` for app-level composition only. shadcn/ui primitives own their
 * variants and local helpers; customise the primitive itself, never by
 * re-styling it with a long `className` at the call site.
 */
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}
