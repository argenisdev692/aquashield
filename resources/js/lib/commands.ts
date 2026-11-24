/**
 * One row in the command palette. The layout owns the list; the palette only
 * renders and filters it, so a command can never point at a route the app does
 * not actually have.
 */
import type { LucideIcon } from 'lucide-react';

export interface Command {
    id: string;
    /** Section header in the list, e.g. "Jump to". */
    group: string;
    label: string;
    /** Lucide icon component the row renders. */
    icon: LucideIcon;
    /** Extra words the filter should match but the row should not show. */
    keywords?: string;
    run: () => void;
}
