import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

/**
 * App-level class composition (`clsx` + `tailwind-merge`).
 *
 * Keep `cn()` for app-level composition only. Volt components ship their own
 * local `ptViewMerge` helper — do not duplicate `tailwind-merge` logic by hand.
 * Customise Volt styling through the component's `pt` pass-through config,
 * never with inline classes at the call site.
 */
export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}
