/**
 * FormSection — a labelled block for long enterprise forms.
 *
 * CRM records routinely carry 40+ fields. Grouping them into named sections
 * with a consistent two-column grid is what keeps a deal, account or contact
 * form scannable; a single unbroken column is where required fields get
 * skipped. Fields that need the full width opt in with
 * `className="sm:col-span-2"` (or `lg:col-span-3`).
 */
import { useId } from 'react';
import { cn } from '@/lib/utils';

interface FormSectionProps {
    title: string;
    description?: string;
    /** Optional leading icon; decorative only, so it is always aria-hidden. */
    icon?: React.ReactNode;
    columns?: 1 | 2 | 3;
    className?: string;
    children: React.ReactNode;
}

export default function FormSection({
    title,
    description,
    icon,
    columns = 2,
    className,
    children,
}: FormSectionProps) {
    const uid = useId();
    const titleId = `${uid}-title`;

    return (
        <section aria-labelledby={titleId} className={cn('flex flex-col gap-3', className)}>
            <div className="flex items-start gap-2.5">
                {icon !== undefined && (
                    <span className="mt-0.5 text-aq-accent" aria-hidden="true">
                        {icon}
                    </span>
                )}

                <div className="flex min-w-0 flex-col gap-1">
                    <h2
                        id={titleId}
                        className="font-heading text-sm font-semibold text-surface-800 dark:text-surface-100"
                    >
                        {title}
                    </h2>

                    {description && (
                        <p className="text-xs text-surface-600 dark:text-surface-300">
                            {description}
                        </p>
                    )}
                </div>
            </div>

            <div
                className={cn(
                    'grid grid-cols-1 gap-3',
                    columns >= 2 && 'sm:grid-cols-2',
                    columns === 3 && 'lg:grid-cols-3',
                )}
            >
                {children}
            </div>
        </section>
    );
}
