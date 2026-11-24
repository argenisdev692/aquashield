import * as React from 'react';
import { Input as InputPrimitive } from '@base-ui/react/input';
import { cn } from 'cn';

function Input({ className, type, ...props }: React.ComponentProps<'input'>) {
    return (
        <InputPrimitive
            type={type}
            data-slot="input"
            className={cn(
                // The sign-in field treatment: border-colour change on focus
                // (no ring), the brand soft shadow, and the aq ramp for the
                // invalid state.
                'h-auto w-full min-w-0 appearance-none rounded-md border border-surface-300 bg-surface-0 px-3 py-2 text-sm text-surface-700 shadow-(--aq-input-shadow) outline-none placeholder:text-surface-500 transition-colors duration-200 enabled:hover:border-surface-400 disabled:bg-surface-200 disabled:text-surface-500 dark:border-surface-700 dark:bg-surface-950 dark:text-surface-0 dark:placeholder:text-surface-400 dark:disabled:bg-surface-700 dark:disabled:text-surface-400 enabled:focus:border-primary disabled:cursor-not-allowed aria-invalid:border-aq-danger',
                className,
            )}
            {...props}
        />
    );
}

export { Input };
