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

/**
 * Snake_case enum value → sentence-case label (`inspection_scheduled` →
 * `Inspection scheduled`). The one copy every surface reads, so a stage or
 * source can never be spelled two ways on two screens.
 */
export function humanize(value: string): string {
    const label = value.replace(/_/g, ' ');

    return label.charAt(0).toUpperCase() + label.slice(1);
}
