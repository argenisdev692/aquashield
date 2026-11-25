/**
 * Backups/Show — single backup archive detail.
 * Connected to Inertia: props come from the Laravel controller via usePage().
 */
import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Database, Download, TriangleAlert } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Tag } from '@/Components/ui/tag';
import { CrudDialog } from '@/Components/DataTable';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime } from '@/lib/date';

type BackupArchive = Modules.Backups.Data.BackupArchiveData;
type BackupActivity = Modules.Backups.Data.BackupActivityData;

type BackupPageProps = {
    backup: BackupArchive;
    activities: BackupActivity[];
    /** Quarantine window the purge command enforces, in days. */
    quarantineDays: number;
    can: {
        download: boolean;
        delete: boolean;
        restore: boolean;
    };
};

export default function BackupsShow() {
    const page = usePage<BackupPageProps>();
    const { backup, activities, can, quarantineDays } = page.props;

    const entries = activities;

    const [confirmQuarantineOpen, setConfirmQuarantineOpen] = useState(false);
    const [restoring, setRestoring] = useState(false);

    const restore = () => {
        setRestoring(true);

        // The web route is PATCH /backups/{uuid}/restore; PUT answers 405.
        router.patch(
            `/backups/${backup.uuid}/restore`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.visit('/backups');
                },
                onFinish: () => setRestoring(false),
            },
        );
    };

    const quarantine = () => {
        setConfirmQuarantineOpen(true);
    };

    const confirmQuarantine = () => {
        router.delete(`/backups/${backup.uuid}`, {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmQuarantineOpen(false);
                router.visit('/backups');
            },
        });
    };

    return (
        <AppLayout title="Backup details">
            <Head title={backup.filename} />

            <nav aria-label="Breadcrumb" className="mb-4">
                <ol className="flex flex-wrap items-center gap-1.5 text-sm text-surface-600 dark:text-surface-300">
                    <li>
                        <Link
                            href="/backups"
                            className="rounded-sm underline-offset-2 transition-colors duration-150 hover:text-primary-600 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
                        >
                            Database backups
                        </Link>
                    </li>
                    <li aria-hidden="true">
                        <span className="text-[10px] text-surface-400">›</span>
                    </li>
                    <li
                        className="truncate font-medium text-surface-900 dark:text-surface-0"
                        aria-current="page"
                    >
                        {backup.filename}
                    </li>
                </ol>
            </nav>

            {backup.is_trashed && (
                <div
                    role="status"
                    className="mb-4 flex flex-col gap-3 rounded-xl border border-aq-danger/40 bg-aq-danger/10 p-4 sm:flex-row sm:items-center"
                >
                    <TriangleAlert className="h-5 w-5 text-aq-danger" aria-hidden="true" />
                    <p className="min-w-0 flex-1 text-sm text-aq-danger">
                        This archive is quarantined. It is not offered for download and is deleted
                        permanently after {quarantineDays} days.
                    </p>
                    {can.restore && (
                        <Button variant="default" onClick={restore} disabled={restoring}>
                            {restoring ? 'Restoring…' : 'Restore archive'}
                        </Button>
                    )}
                </div>
            )}

            <section
                className="relative overflow-hidden rounded-xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm dark:bg-surface-900"
                aria-labelledby="backup-heading"
            >
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                    <span className="grid size-12 shrink-0 place-items-center rounded-xl bg-primary-100 text-primary-600 dark:bg-primary-900/30">
                        <Database className="h-5 w-5" aria-hidden="true" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h2
                                id="backup-heading"
                                className="truncate font-mono text-lg font-semibold tracking-tight text-surface-900 dark:text-surface-0"
                            >
                                {backup.filename}
                            </h2>
                            {backup.is_newest && <Tag variant="default">Newest</Tag>}
                            {backup.is_trashed && <Tag variant="warning">Quarantined</Tag>}
                            {!backup.is_newest && !backup.is_trashed && (
                                <Tag variant="default">Active</Tag>
                            )}
                        </div>
                        <p className="mt-1 text-sm text-surface-600 dark:text-surface-300">
                            {backup.disk} · {backup.size_human} ·
                            {backup.age_days === 0 ? 'taken today' : `${backup.age_days} days old`}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                        {can.download && !backup.is_trashed && (
                            <Button
                                render={<a href={`/backups/${backup.uuid}/download`} />}
                                nativeButton={false}
                                variant="default"
                                size="lg"
                            >
                                <Download className="size-4" aria-hidden="true" />
                                Download
                            </Button>
                        )}
                        {can.delete && !backup.is_trashed && (
                            <Button variant="destructive" onClick={quarantine}>
                                Quarantine
                            </Button>
                        )}
                    </div>
                </div>
            </section>

            <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div className="space-y-6">
                    <section
                        className="rounded-xl border border-aq-shell-line bg-surface-0 p-6 shadow-sm dark:bg-surface-900"
                        aria-labelledby="details-heading"
                    >
                        <h2
                            id="details-heading"
                            className="text-base font-semibold text-surface-900 dark:text-surface-0"
                        >
                            Archive details
                        </h2>
                        <dl className="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-[11px] font-semibold tracking-wide text-surface-600 uppercase dark:text-surface-300">
                                    Filename
                                </dt>
                                <dd className="mt-0.5 truncate font-mono text-sm text-surface-900 dark:text-surface-0">
                                    {backup.filename}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-[11px] font-semibold tracking-wide text-surface-600 uppercase dark:text-surface-300">
                                    Destination
                                </dt>
                                <dd className="mt-0.5 text-sm text-surface-900 dark:text-surface-0">
                                    {backup.disk}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-[11px] font-semibold tracking-wide text-surface-600 uppercase dark:text-surface-300">
                                    Taken
                                </dt>
                                <dd className="mt-0.5 text-sm text-surface-900 tabular-nums dark:text-surface-0">
                                    <time dateTime={backup.taken_at}>
                                        {formatDateTime(backup.taken_at)}
                                    </time>
                                </dd>
                            </div>
                            <div>
                                <dt className="text-[11px] font-semibold tracking-wide text-surface-600 uppercase dark:text-surface-300">
                                    Size
                                </dt>
                                <dd className="mt-0.5 text-sm text-surface-900 tabular-nums dark:text-surface-0">
                                    {backup.size_human}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-[11px] font-semibold tracking-wide text-surface-600 uppercase dark:text-surface-300">
                                    Age
                                </dt>
                                <dd className="mt-0.5 text-sm text-surface-900 tabular-nums dark:text-surface-0">
                                    {backup.age_days === 0 ? 'Today' : `${backup.age_days} days`}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-[11px] font-semibold tracking-wide text-surface-600 uppercase dark:text-surface-300">
                                    Archive ID
                                </dt>
                                <dd className="mt-0.5 truncate font-mono text-xs text-surface-700 dark:text-surface-200">
                                    {backup.uuid}
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <section
                        className="rounded-xl border border-aq-shell-line bg-surface-0 p-6 shadow-sm dark:bg-surface-900"
                        aria-labelledby="audit-heading"
                    >
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <h2
                                id="audit-heading"
                                className="text-base font-semibold text-surface-900 dark:text-surface-0"
                            >
                                Audit trail
                            </h2>
                            <span className="text-xs text-surface-600 dark:text-surface-300">
                                Last {entries.length} event(s) for this archive
                            </span>
                        </div>

                        {entries.length === 0 && (
                            <p className="mt-3 text-sm text-surface-600 dark:text-surface-300">
                                No audit entries have been recorded for this archive yet.
                            </p>
                        )}

                        {entries.length > 0 && (
                            <ol className="mt-4 space-y-3">
                                {entries.map((entry) => (
                                    <li
                                        key={entry.id}
                                        className="rounded-lg border border-aq-shell-line px-3 py-2.5"
                                    >
                                        <div className="flex flex-wrap items-center gap-2">
                                            <Tag variant="default">{entry.event}</Tag>
                                            <span className="text-sm text-surface-900 dark:text-surface-0">
                                                {entry.description}
                                            </span>
                                            <time
                                                className="ml-auto text-xs text-surface-600 tabular-nums dark:text-surface-300"
                                                dateTime={entry.created_at}
                                            >
                                                {formatDateTime(entry.created_at)}
                                            </time>
                                        </div>
                                        <p className="mt-1 text-xs text-surface-600 dark:text-surface-300">
                                            {entry.causer_name ?? 'System'}
                                            {entry.properties?.disk !== undefined &&
                                                ` · ${entry.properties.disk}`}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                </div>

                <aside className="space-y-4">
                    <section className="rounded-xl border border-aq-shell-line bg-surface-0 p-6 shadow-sm dark:bg-surface-900">
                        <h2 className="text-sm font-semibold text-surface-900 dark:text-surface-0">
                            Retention
                        </h2>
                        <dl className="mt-3 space-y-2.5 text-sm">
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-surface-600 dark:text-surface-300">
                                    Quarantine window
                                </dt>
                                <dd className="text-end text-surface-900 tabular-nums dark:text-surface-0">
                                    {quarantineDays} {quarantineDays === 1 ? 'day' : 'days'}
                                </dd>
                            </div>
                            <div className="flex items-center justify-between gap-3">
                                <dt className="text-surface-600 dark:text-surface-300">
                                    Newest protected
                                </dt>
                                <dd className="text-end text-surface-900 tabular-nums dark:text-surface-0">
                                    {backup.is_newest ? 'Yes' : 'No'}
                                </dd>
                            </div>
                        </dl>
                        <p className="mt-3 text-xs text-surface-600 dark:text-surface-300">
                            Irreversible deletion is a console command with a dry run, not a web
                            request.
                        </p>
                    </section>

                    <section className="rounded-xl border border-aq-shell-line bg-surface-0 p-6 shadow-sm dark:bg-surface-900">
                        <h2 className="text-sm font-semibold text-surface-900 dark:text-surface-0">
                            Restore runbook
                        </h2>
                        <p className="mt-2 text-xs text-surface-600 dark:text-surface-300">
                            Restoring the database is out of band: maintenance mode, verify the
                            archive, replay with the database client. The panel never drops the live
                            schema.
                        </p>
                    </section>
                </aside>
            </div>

            <CrudDialog
                open={confirmQuarantineOpen}
                onOpenChange={setConfirmQuarantineOpen}
                title="Move this archive to quarantine?"
                description={`${backup.filename} (${backup.disk}) stays restorable for ${quarantineDays} days, then the retention command deletes it.`}
                confirmLabel="Quarantine"
                onConfirm={confirmQuarantine}
            >
                <p className="text-sm text-surface-600 dark:text-surface-300">
                    Quarantine is reversible from this page. The archive is not offered for download
                    while it is quarantined.
                </p>
            </CrudDialog>
        </AppLayout>
    );
}
