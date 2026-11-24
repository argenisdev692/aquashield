/**
 * ActivityLog/Show — single activity entry detail.
 * Connected to Inertia: props come from the Laravel controller via usePage().
 */
import { Head, Link, usePage } from '@inertiajs/react';
import { History } from 'lucide-react';
import { Tag } from '@/Components/ui/tag';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/date';

type ActivityEntry = Modules.ActivityLog.Data.ActivityEntryData;

type ActivityPageProps = {
    activity: ActivityEntry;
};

export default function ActivityLogShow() {
    const page = usePage<ActivityPageProps>();
    const activity = page.props.activity;

    const properties = Object.entries(activity.properties ?? {});

    return (
        <AppLayout title="Activity entry">
            <Head title={activity.event} />

            <nav aria-label="Breadcrumb" className="mb-4">
                <ol className="flex flex-wrap items-center gap-1.5 text-sm text-aq-ink-soft">
                    <li>
                        <Link
                            href="/activity"
                            className="rounded-sm underline-offset-2 transition-colors duration-150 hover:text-aq-accent hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent"
                        >
                            Activity log
                        </Link>
                    </li>
                    <li aria-hidden="true">
                        <span className="text-[10px] text-aq-ink-muted">›</span>
                    </li>
                    <li className="truncate font-medium text-aq-ink" aria-current="page">
                        {activity.event}
                    </li>
                </ol>
            </nav>

            <section className="rounded-xl border border-aq-shell-line bg-aq-base p-5 shadow-sm">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
                    <span className="grid size-12 shrink-0 place-items-center rounded-xl bg-aq-accent/10 text-aq-accent dark:bg-aq-accent/15">
                        <History className="size-5" aria-hidden="true" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="truncate font-mono text-lg font-semibold tracking-tight text-aq-ink">
                                {activity.event}
                            </h2>
                            <Tag variant="default">{activity.log_name}</Tag>
                        </div>
                        <p className="mt-1 text-sm text-aq-ink-soft">{activity.description}</p>
                    </div>
                </div>

                <dl className="mt-5 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt className="text-[11px] font-semibold tracking-wide text-surface-600 uppercase dark:text-surface-400">
                            Recorded
                        </dt>
                        <dd className="mt-0.5 text-sm text-surface-900 tabular-nums dark:text-surface-0">
                            {formatDateTime(activity.created_at)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-[11px] font-semibold tracking-wide text-surface-600 uppercase dark:text-surface-400">
                            Causer
                        </dt>
                        <dd className="mt-0.5 text-sm text-surface-900 dark:text-surface-0">
                            {activity.causer_name ?? 'System'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-[11px] font-semibold tracking-wide text-surface-600 uppercase dark:text-surface-400">
                            Subject
                        </dt>
                        <dd className="mt-0.5 text-sm text-surface-900 dark:text-surface-0">
                            {activity.subject_type ?? '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-[11px] font-semibold tracking-wide text-surface-600 uppercase dark:text-surface-400">
                            Entry ID
                        </dt>
                        <dd className="mt-0.5 truncate font-mono text-xs text-surface-700 dark:text-surface-200">
                            {activity.uuid}
                        </dd>
                    </div>
                </dl>
            </section>

            <section className="mt-6 rounded-xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm dark:bg-surface-900">
                <div className="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 className="text-base font-semibold text-surface-900 dark:text-surface-0">
                        Properties
                    </h2>
                    <span className="text-xs text-surface-600 dark:text-surface-300">
                        Allowlisted fields only
                    </span>
                </div>

                {properties.length === 0 && (
                    <p className="mt-3 text-sm text-surface-600 dark:text-surface-300">
                        This entry carries no additional safe properties.
                    </p>
                )}

                {properties.length > 0 && (
                    <dl className="mt-4 divide-y divide-aq-shell-line">
                        {properties.map(([key, value]) => (
                            <div
                                key={key}
                                className="flex flex-col gap-1 py-2.5 sm:flex-row sm:items-baseline sm:gap-4"
                            >
                                <dt className="font-mono text-xs text-surface-600 sm:w-48 sm:shrink-0 dark:text-surface-400">
                                    {key}
                                </dt>
                                <dd className="min-w-0 break-words text-sm text-surface-900 dark:text-surface-0">
                                    {String(value)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                )}

                <p className="mt-4 text-xs text-surface-600 dark:text-surface-300">
                    The full change set remains in the database. This screen never echoes raw
                    properties, causer email addresses or internal ids.
                </p>
            </section>
        </AppLayout>
    );
}
