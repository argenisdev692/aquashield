<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { TableButton } from '@/Components/DataTable';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    BackupRequestError,
    downloadBackupUrl,
    useBackupMutations,
    useBackupQuery,
    type BackupActivity,
    type BackupArchive,
} from '@/Modules/Backups/composables/useBackups';
import Tag from '@/volt/Tag.vue';

defineOptions({
    layout: [AppLayout, { title: 'Backup details' }],
});

const props = defineProps<{
    backup: BackupArchive;
    activities: BackupActivity[];
    can: {
        download: boolean;
        delete: boolean;
        restore: boolean;
    };
}>();

const toast = useToast();
const confirm = useConfirm();

const { data, error, refetch } = useBackupQuery(props.backup.uuid, {
    backup: props.backup,
    activities: props.activities,
});
const { deleteBackup, restoreBackup, busy } = useBackupMutations();

const backup = computed<BackupArchive>(() => data.value?.backup ?? props.backup);
const activities = computed<BackupActivity[]>(() => data.value?.activities ?? []);

const queryErrorMessage = computed<string>(() =>
    error.value instanceof BackupRequestError
        ? error.value.message
        : 'Could not load this backup. Check your connection and try again.',
);

const notifyError = (caught: unknown, fallback: string): void => {
    toast.add({
        severity: 'error',
        summary: 'Action failed',
        detail: caught instanceof BackupRequestError ? caught.message : fallback,
        life: 5000,
    });
};

const removeBackup = async (): Promise<void> => {
    try {
        await deleteBackup(backup.value.uuid);
        toast.add({
            severity: 'success',
            summary: 'Backup quarantined',
            detail: `${backup.value.filename} can be restored for 30 days.`,
            life: 4000,
        });
        router.visit('/backups');
    } catch (caught: unknown) {
        notifyError(caught, 'The backup could not be removed.');
    }
};

const restoreBackupRow = async (): Promise<void> => {
    try {
        await restoreBackup(backup.value.uuid);
        toast.add({
            severity: 'success',
            summary: 'Backup restored',
            detail: `${backup.value.filename} is active again.`,
            life: 4000,
        });
        router.visit('/backups');
    } catch (caught: unknown) {
        notifyError(caught, 'The backup could not be restored.');
    }
};

const confirmDelete = (): void => {
    confirm.require({
        header: 'Quarantine backup',
        message: `Move ${backup.value.filename} (${backup.value.disk}) to quarantine? It stays restorable for 30 days, then the retention command deletes it. This does not touch the newest archive.`,
        acceptLabel: 'Quarantine',
        rejectLabel: 'Cancel',
        accept: () => {
            void removeBackup();
        },
    });
};

const confirmRestore = (): void => {
    confirm.require({
        header: 'Restore backup',
        message: `Restore ${backup.value.filename} (${backup.value.disk}) to the active destination?`,
        acceptLabel: 'Restore',
        rejectLabel: 'Cancel',
        accept: () => {
            void restoreBackupRow();
        },
    });
};
</script>

<template>
    <Head :title="backup.filename" />

    <nav aria-label="Breadcrumb" class="mb-4">
        <ol
            class="flex flex-wrap items-center gap-1.5 text-sm text-surface-500 dark:text-surface-400"
        >
            <li>
                <Link
                    href="/backups"
                    class="rounded-sm underline-offset-2 transition-colors duration-150 hover:text-primary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none"
                >
                    Database backups
                </Link>
            </li>
            <li aria-hidden="true">
                <i class="pi pi-angle-right text-[10px]" />
            </li>
            <li
                class="truncate font-medium text-surface-800 dark:text-surface-100"
                aria-current="page"
            >
                {{ backup.filename }}
            </li>
        </ol>
    </nav>

    <div
        v-if="error"
        role="alert"
        class="mb-4 flex flex-col gap-3 rounded-xl border border-aq-danger/40 bg-aq-danger/5 px-4 py-3 sm:flex-row sm:items-center"
    >
        <i class="pi pi-exclamation-triangle text-aq-danger-strong" aria-hidden="true" />
        <p class="min-w-0 flex-1 text-sm text-aq-danger-strong">{{ queryErrorMessage }}</p>
        <TableButton variant="neutral" @click="refetch()">Try again</TableButton>
    </div>

    <div
        v-if="backup.is_trashed"
        role="status"
        class="mb-4 flex flex-col gap-3 rounded-xl border border-aq-danger/40 bg-aq-danger/5 px-4 py-3 sm:flex-row sm:items-center"
    >
        <i class="pi pi-exclamation-triangle text-aq-danger-strong" aria-hidden="true" />
        <p class="min-w-0 flex-1 text-sm text-aq-danger-strong">
            This archive is quarantined. It is not offered for download and is deleted permanently
            after the retention window.
        </p>
        <TableButton v-if="can.restore" variant="primary" :disabled="busy" @click="confirmRestore">
            Restore archive
        </TableButton>
    </div>

    <section
        class="relative overflow-hidden rounded-xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm dark:bg-surface-900"
        aria-labelledby="backup-heading"
    >
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
            <span
                class="grid size-12 shrink-0 place-items-center rounded-xl bg-aq-accent/10 text-aq-accent dark:bg-aq-accent/15"
                aria-hidden="true"
            >
                <i class="pi pi-database text-lg" />
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2
                        id="backup-heading"
                        class="truncate font-mono text-lg font-semibold tracking-tight text-surface-900 dark:text-surface-0"
                    >
                        {{ backup.filename }}
                    </h2>
                    <Tag v-if="backup.is_newest" severity="success">Newest</Tag>
                    <Tag v-if="backup.is_trashed" severity="warn">Quarantined</Tag>
                    <Tag v-else severity="info">Active</Tag>
                </div>

                <p class="mt-1 text-sm text-surface-600 dark:text-surface-300">
                    {{ backup.disk }} · {{ backup.size_human }} ·
                    {{ backup.age_days === 0 ? 'taken today' : `${backup.age_days} days old` }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                <a
                    v-if="can.download && !backup.is_trashed"
                    :href="downloadBackupUrl(backup.uuid)"
                    class="inline-flex h-9 items-center gap-2 rounded-md border border-primary bg-primary px-3 text-sm font-medium text-primary-contrast transition-colors duration-150 hover:border-primary-emphasis hover:bg-primary-emphasis focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none"
                >
                    <i class="pi pi-download text-xs" aria-hidden="true" />
                    Download
                </a>

                <TableButton
                    v-if="can.delete && !backup.is_trashed"
                    variant="danger"
                    :disabled="busy"
                    @click="confirmDelete"
                >
                    Quarantine
                </TableButton>

                <TableButton
                    v-if="can.restore && backup.is_trashed"
                    variant="primary"
                    :disabled="busy"
                    @click="confirmRestore"
                >
                    Restore
                </TableButton>
            </div>
        </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-6">
            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm dark:bg-surface-900"
                aria-labelledby="details-heading"
            >
                <h2
                    id="details-heading"
                    class="text-base font-semibold text-surface-900 dark:text-surface-0"
                >
                    Archive details
                </h2>

                <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt
                            class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            Filename
                        </dt>
                        <dd
                            class="mt-0.5 truncate font-mono text-sm text-surface-900 dark:text-surface-0"
                        >
                            {{ backup.filename }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            Destination
                        </dt>
                        <dd class="mt-0.5 text-sm text-surface-900 dark:text-surface-0">
                            {{ backup.disk }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            Taken
                        </dt>
                        <dd
                            class="mt-0.5 text-sm text-surface-900 tabular-nums dark:text-surface-0"
                        >
                            {{ backup.taken_at }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            Size
                        </dt>
                        <dd
                            class="mt-0.5 text-sm text-surface-900 tabular-nums dark:text-surface-0"
                        >
                            {{ backup.size_human }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            Age
                        </dt>
                        <dd
                            class="mt-0.5 text-sm text-surface-900 tabular-nums dark:text-surface-0"
                        >
                            {{ backup.age_days === 0 ? 'Today' : `${backup.age_days} days` }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                        >
                            Archive ID
                        </dt>
                        <dd
                            class="mt-0.5 truncate font-mono text-xs text-surface-700 dark:text-surface-200"
                        >
                            {{ backup.uuid }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm dark:bg-surface-900"
                aria-labelledby="audit-heading"
            >
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2
                        id="audit-heading"
                        class="text-base font-semibold text-surface-900 dark:text-surface-0"
                    >
                        Audit trail
                    </h2>
                    <span class="text-xs text-surface-500 dark:text-surface-400">
                        Last {{ activities.length }} event(s) for this archive
                    </span>
                </div>

                <p
                    v-if="activities.length === 0"
                    class="mt-3 text-sm text-surface-600 dark:text-surface-300"
                >
                    No audit entries have been recorded for this archive yet.
                </p>

                <ol v-else class="mt-4 space-y-3">
                    <li
                        v-for="entry in activities"
                        :key="entry.id"
                        class="rounded-lg border border-aq-shell-line px-3 py-2.5"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <Tag severity="secondary">{{ entry.event }}</Tag>
                            <span class="text-sm text-surface-800 dark:text-surface-100">{{
                                entry.description
                            }}</span>
                            <time
                                :datetime="entry.created_at"
                                class="ml-auto text-xs text-surface-500 tabular-nums dark:text-surface-400"
                            >
                                {{ entry.created_at }}
                            </time>
                        </div>
                        <p class="mt-1 text-xs text-surface-500 dark:text-surface-400">
                            {{ entry.causer_name ?? 'System' }}
                            <template v-if="entry.properties.disk !== undefined">
                                · {{ entry.properties.disk }}
                            </template>
                        </p>
                    </li>
                </ol>
            </section>
        </div>

        <aside class="space-y-4">
            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-4 shadow-sm dark:bg-surface-900"
            >
                <h2 class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                    Retention
                </h2>
                <dl class="mt-3 space-y-2.5 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-surface-500 dark:text-surface-400">Quarantine window</dt>
                        <dd class="text-end text-surface-800 tabular-nums dark:text-surface-100">
                            30 days
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-surface-500 dark:text-surface-400">Newest protected</dt>
                        <dd class="text-surface-800 dark:text-surface-100">
                            {{ backup.is_newest ? 'Yes' : 'No' }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-surface-500 dark:text-surface-400">
                    Irreversible deletion is a console command with a dry run, not a web request.
                </p>
            </section>

            <section
                class="rounded-xl border border-aq-shell-line bg-surface-0 p-4 shadow-sm dark:bg-surface-900"
            >
                <h2 class="text-sm font-semibold text-surface-900 dark:text-surface-0">
                    Restore runbook
                </h2>
                <p class="mt-2 text-xs text-surface-500 dark:text-surface-400">
                    Restoring the database is out of band: maintenance mode, verify the archive,
                    replay with the database client. The panel never drops the live schema.
                </p>
            </section>
        </aside>
    </div>
</template>
