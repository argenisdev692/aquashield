/**
 * Local edit: the unchecked dark fill (`dark:bg-input/30`) used to win over
 * `data-checked:bg-aq-accent` in the generated sheet, so a checked box looked
 * empty in dark mode. Scoping the fill to `data-unchecked` leaves the checked
 * accent in control.
 */
import { Checkbox as CheckboxPrimitive } from '@base-ui/react/checkbox';
import { cn } from 'cn';
import { CheckIcon } from 'lucide-react';

function Checkbox({ className, ...props }: CheckboxPrimitive.Root.Props) {
    return (
        <CheckboxPrimitive.Root
            data-slot="checkbox"
            className={cn(
                'peer relative inline-flex size-5 shrink-0 items-center justify-center rounded-sm border border-surface-300 bg-surface-0 text-surface-700 transition-colors duration-200 disabled:bg-surface-200 peer-enabled:peer-hover:border-surface-400 disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-1 focus-visible:outline-offset-2 focus-visible:outline-aq-focus aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 aria-invalid:aria-checked:border-primary dark:data-unchecked:bg-input/30 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40 data-checked:border-aq-accent data-checked:bg-aq-accent data-checked:text-aq-void group-has-[:focus-visible]/field-label:data-checked:border-aq-accent',
                className,
            )}
            {...props}
        >
            <CheckboxPrimitive.Indicator
                data-slot="checkbox-indicator"
                className="grid place-content-center text-current transition-none [&>svg]:size-3.5"
            >
                <CheckIcon />
            </CheckboxPrimitive.Indicator>
        </CheckboxPrimitive.Root>
    );
}

export { Checkbox };
