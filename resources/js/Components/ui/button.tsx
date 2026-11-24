import { Button as ButtonPrimitive } from '@base-ui/react/button';
import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from 'cn';

const buttonVariants = cva(
    "group/button inline-flex shrink-0 items-center justify-center rounded-lg border border-transparent bg-clip-padding text-sm font-medium whitespace-nowrap transition-all outline-none select-none focus-visible:border-aq-accent focus-visible:ring-3 focus-visible:ring-aq-accent/50 active:not-aria-[haspopup]:translate-y-px disabled:pointer-events-none disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
    {
        variants: {
            variant: {
                default:
                    'bg-aq-action text-aq-action-ink hover:bg-aq-action-hover focus-visible:border-aq-accent focus-visible:ring-3 focus-visible:ring-aq-accent/50',
                outline:
                    'border-surface-300 bg-surface-0 text-surface-700 hover:bg-surface-100 hover:text-surface-900 aria-expanded:bg-surface-100 aria-expanded:text-surface-900 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-100 dark:hover:bg-surface-800 dark:hover:text-surface-0 dark:aria-expanded:bg-surface-800 dark:aria-expanded:text-surface-0',
                secondary:
                    'bg-surface-100 text-surface-900 hover:bg-surface-200 aria-expanded:bg-surface-200 aria-expanded:text-surface-900 dark:bg-surface-800 dark:text-surface-0 dark:hover:bg-surface-700 dark:aria-expanded:bg-surface-700 dark:aria-expanded:text-surface-0',
                ghost: 'hover:bg-surface-100 hover:text-surface-900 aria-expanded:bg-surface-100 aria-expanded:text-surface-900 dark:hover:bg-surface-800 dark:aria-expanded:bg-surface-800',
                destructive:
                    'bg-destructive/10 text-destructive hover:bg-destructive/20 focus-visible:border-destructive/40 focus-visible:ring-destructive/20 dark:bg-destructive/20 dark:hover:bg-destructive/30 dark:focus-visible:ring-destructive/40',
                warning:
                    'border-aq-warning/40 bg-transparent text-aq-warning hover:bg-aq-warning/10 focus-visible:border-aq-warning focus-visible:ring-aq-warning/30 dark:border-aq-warning/40 dark:bg-transparent dark:text-aq-warning dark:hover:bg-aq-warning/15 dark:focus-visible:ring-aq-warning/30',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            size: {
                default:
                    'h-8 gap-1.5 px-2.5 has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2',
                xs: "h-6 gap-1 rounded-[min(var(--radius-md),10px)] px-2 text-xs in-data-[slot=button-group]:rounded-lg has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&_svg:not([class*='size-'])]:size-3",
                sm: "h-7 gap-1 rounded-[min(var(--radius-md),12px)] px-2.5 text-[0.8rem] in-data-[slot=button-group]:rounded-lg has-data-[icon=inline-end]:pr-1.5 has-data-[icon=inline-start]:pl-1.5 [&_svg:not([class*='size-'])]:size-3.5",
                lg: 'h-9 gap-1.5 px-2.5 has-data-[icon=inline-end]:pr-2 has-data-[icon=inline-start]:pl-2',
                /** 36px toolbar action — table toolbars and page header actions. */
                toolbar: 'h-9 gap-1.5 px-3 text-xs font-medium',
                icon: 'size-8',
                'icon-xs':
                    "size-6 rounded-[min(var(--radius-md),10px)] in-data-[slot=button-group]:rounded-lg [&_svg:not([class*='size-'])]:size-3",
                'icon-sm':
                    'size-7 rounded-[min(var(--radius-md),12px)] in-data-[slot=button-group]:rounded-lg',
                'icon-lg': 'size-9',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    },
);

function Button({
    className,
    variant = 'default',
    size = 'default',
    ...props
}: ButtonPrimitive.Props & VariantProps<typeof buttonVariants>) {
    return (
        <ButtonPrimitive
            data-slot="button"
            className={cn(buttonVariants({ variant, size, className }))}
            {...props}
        />
    );
}

export { Button, buttonVariants };
