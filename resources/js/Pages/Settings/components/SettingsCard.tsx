/**
 * SettingsCard — the one surface used by every settings section.
 *
 * A quiet header (icon + title + description), a content slot and an optional
 * footer keep the four tabs visually consistent without a second card
 * primitive in `Components/ui`.
 */
import { cn } from '@/lib/utils';

interface SettingsCardProps {
    title: string;
    description?: string;
    icon?: React.ReactNode;
    action?: React.ReactNode;
    footer?: React.ReactNode;
    className?: string;
    children: React.ReactNode;
}

export default function SettingsCard({
    title,
    description,
    icon,
    action,
    footer,
    className,
    children,
}: SettingsCardProps) {
    return (
        <section
            className={cn(
                'overflow-hidden rounded-2xl border border-aq-shell-line bg-surface-0 shadow-sm dark:bg-surface-900',
                className,
            )}
        >
            <header className="flex flex-wrap items-start gap-3 border-b border-aq-shell-line/70 px-5 py-4 sm:px-6">
                {icon !== undefined && (
                    <span className="grid size-9 shrink-0 place-items-center rounded-xl bg-aq-accent/10 text-aq-accent">
                        {icon}
                    </span>
                )}

                <div className="min-w-0 flex-1">
                    <h2 className="font-heading text-base font-semibold text-surface-900 dark:text-surface-0">
                        {title}
                    </h2>

                    {description !== undefined && (
                        <p className="mt-0.5 text-sm text-surface-600 dark:text-surface-300">
                            {description}
                        </p>
                    )}
                </div>

                {action !== undefined && <div className="shrink-0">{action}</div>}
            </header>

            <div className="px-5 py-5 sm:px-6">{children}</div>

            {footer !== undefined && (
                <div className="border-t border-aq-shell-line/70 bg-surface-50/60 px-5 py-3 sm:px-6 dark:bg-surface-950/30">
                    {footer}
                </div>
            )}
        </section>
    );
}
