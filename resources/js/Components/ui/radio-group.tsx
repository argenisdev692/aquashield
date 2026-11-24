import { Radio as RadioPrimitive } from '@base-ui/react/radio';
import { RadioGroup as RadioGroupPrimitive } from '@base-ui/react/radio-group';
import { cn } from 'cn';

function RadioGroup<Value>({ className, ...props }: RadioGroupPrimitive.Props<Value>) {
    return (
        <RadioGroupPrimitive
            data-slot="radio-group"
            className={cn('grid w-full gap-2', className)}
            {...props}
        />
    );
}

function RadioGroupItem<Value>({ className, ...props }: RadioPrimitive.Root.Props<Value>) {
    return (
        <RadioPrimitive.Root
            data-slot="radio-group-item"
            className={cn(
                'group/radio-group-item peer relative flex aspect-square size-4 shrink-0 rounded-full border border-surface-300 outline-none group-has-[:focus-visible]/field-label:ring-0 group-has-[:focus-visible]/field-label:not-data-checked:border-surface-300 after:absolute after:-inset-x-3 after:-inset-y-2 focus-visible:border-aq-accent focus-visible:ring-3 focus-visible:ring-aq-accent/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 aria-invalid:aria-checked:border-aq-accent dark:bg-surface-950/30 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40 data-checked:border-aq-accent data-checked:bg-aq-accent data-checked:text-aq-void group-has-[:focus-visible]/field-label:data-checked:border-aq-accent dark:data-checked:bg-aq-accent',
                className,
            )}
            {...props}
        >
            <RadioPrimitive.Indicator
                data-slot="radio-group-indicator"
                className="flex size-4 items-center justify-center"
            >
                <span className="absolute top-1/2 left-1/2 size-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-aq-void" />
            </RadioPrimitive.Indicator>
        </RadioPrimitive.Root>
    );
}

export { RadioGroup, RadioGroupItem };
