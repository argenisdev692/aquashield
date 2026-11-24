import { cn } from 'cn';

/**
 * shadcn/ui Skeleton on Base UI tokens.
 *
 * The generated primitive is the only loading placeholder: compose table,
 * card and panel skeletons from it instead of hand-rolling `animate-pulse`.
 * `motion-reduce:animate-none` keeps the skeleton still for reduced motion.
 */
function Skeleton({ className, ...props }: React.ComponentProps<'div'>) {
    return (
        <div
            data-slot="skeleton"
            className={cn(
                'animate-pulse rounded-md bg-surface-100 motion-reduce:animate-none dark:bg-surface-800',
                className,
            )}
            {...props}
        />
    );
}

export { Skeleton };
