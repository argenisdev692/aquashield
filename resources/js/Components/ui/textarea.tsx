import * as React from 'react';
import { cn } from 'cn';

function Textarea({ className, ...props }: React.ComponentProps<'textarea'>) {
    return (
        <textarea
            data-slot="textarea"
            className={cn(
                'flex field-sizing-content min-h-16 w-full rounded-lg border border-content-border bg-surface-0 px-2.5 py-2 text-base transition-colors outline-none placeholder:text-surface-500 focus-visible:border-aq-accent focus-visible:ring-3 focus-visible:ring-aq-accent/50 disabled:cursor-not-allowed disabled:bg-surface-200 disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 md:text-sm dark:bg-surface-950 dark:disabled:bg-surface-700 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40',
                className,
            )}
            {...props}
        />
    );
}

export { Textarea };
