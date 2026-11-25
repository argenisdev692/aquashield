/**
 * Tag — small status label.
 */
import { cn } from '@/lib/utils';

interface TagProps {
    variant?: 'default' | 'success' | 'warning' | 'destructive' | 'outline';
    className?: string;
    children: React.ReactNode;
}

export function Tag({ variant = 'default', className, children }: TagProps) {
    const variants = {
        default: 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-200',
        success:
            'bg-aq-success/10 text-aq-success-strong dark:bg-aq-success/10 dark:text-aq-success-strong',
        warning:
            'bg-aq-warning/10 text-aq-warning-strong dark:bg-aq-warning/10 dark:text-aq-warning-strong',
        destructive:
            'bg-aq-danger/10 text-aq-danger-strong dark:bg-aq-danger/10 dark:text-aq-danger-strong',
        outline:
            'border border-surface-200 text-surface-600 dark:border-surface-800 dark:text-surface-300 dark:bg-transparent',
    };

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-tighter',
                variants[variant],
                className,
            )}
        >
            {children}
        </span>
    );
}
