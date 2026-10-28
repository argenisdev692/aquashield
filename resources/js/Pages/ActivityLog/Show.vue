<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { TableButton } from '@/Components/DataTable';
import AppLayout from '@/Layouts/AppLayout.vue';
import {
    ActivityRequestError,
    useActivityEntryQuery,
    type ActivityEntry,
} from '@/Modules/ActivityLog/composables/useActivityLog';
import Tag from '@/volt/Tag.vue';
import { eventSeverity } from './components/activityDisplay';

defineOptions({
    layout: [AppLayout, { title: 'Activity entry' }],
});

const props = defineProps<{
    activity: ActivityEntry;
}>();

const { data, error, refetch } = useActivityEntryQuery(props.activity.uuid, {
    activity: props.activity,
});

const activity = computed<ActivityEntry>(() => data.value?.activity ?? props.activity);
const properties = computed<Array<[string, string | number]>>(() =>
    Object.entries(activity.value.properties),
);

const queryErrorMessage = computed<string>(() =>
    error.value instanceof ActivityRequestError
        ? error.value.message
        : 'Could not load this entry. Check your connection and try again.',
);
</script>

<template>
    <Head :title="activity.event" />

    <nav aria-label="Breadcrumb" class="mb-4">
        <ol
            class="flex flex-wrap items-center gap-1.5 text-sm text-surface-500 dark:text-surface-400"
        >
            <li>
                <Link
                    href="/activity"
                    class="rounded-sm underline-offset-2 transition-colors duration-150 hover:text-primary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none"
                >
                    Activity log
                </Link>
            </li>
            <li aria-hidden="true">
                <i class="pi pi-angle-right text-[10px]" />
            </li>
            <li
                class="truncate font-medium text-surface-800 dark:text-surface-100"
                aria-current="page"
            >
                {{ activity.event }}
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

    <section
        class="rounded-xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm dark:bg-surface-900"
        aria-labelledby="activity-heading"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
            <span
                class="grid size-12 shrink-0 place-items-center rounded-xl bg-aq-accent/10 text-aq-accent dark:bg-aq-accent/15"
                aria-hidden="true"
            >
                <i class="pi pi-history text-lg" />
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2
                        id="activity-heading"
                        class="truncate font-mono text-lg font-semibold tracking-tight text-surface-900 dark:text-surface-0"
                    >
                        {{ activity.event }}
                    </h2>
                    <Tag :severity="eventSeverity(activity.event)">{{ activity.log_name }}</Tag>
                </div>

                <p class="mt-1 text-sm text-surface-600 dark:text-surface-300">
                    {{ activity.description }}
                </p>
            </div>
        </div>

        <dl class="mt-5 grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt
                    class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                >
                    Recorded
                </dt>
                <dd class="mt-0.5 text-sm text-surface-900 tabular-nums dark:text-surface-0">
                    {{ activity.created_at }}
                </dd>
            </div>
            <div>
                <dt
                    class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                >
                    Causer
                </dt>
                <dd class="mt-0.5 text-sm text-surface-900 dark:text-surface-0">
                    {{ activity.causer_name ?? 'System' }}
                </dd>
            </div>
            <div>
                <dt
                    class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                >
                    Subject
                </dt>
                <dd class="mt-0.5 text-sm text-surface-900 dark:text-surface-0">
                    {{ activity.subject_type ?? '—' }}
                </dd>
            </div>
            <div>
                <dt
                    class="text-[11px] font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                >
                    Entry ID
                </dt>
                <dd
                    class="mt-0.5 truncate font-mono text-xs text-surface-700 dark:text-surface-200"
                >
                    {{ activity.uuid }}
                </dd>
            </div>
        </dl>
    </section>

    <section
        class="mt-6 rounded-xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm dark:bg-surface-900"
        aria-labelledby="properties-heading"
    >
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2
                id="properties-heading"
                class="text-base font-semibold text-surface-900 dark:text-surface-0"
            >
                Properties
            </h2>
            <span class="text-xs text-surface-500 dark:text-surface-400">
                Allowlisted fields only
            </span>
        </div>

        <p
            v-if="properties.length === 0"
            class="mt-3 text-sm text-surface-600 dark:text-surface-300"
        >
            This entry carries no additional safe properties.
        </p>

        <dl v-else class="mt-4 divide-y divide-aq-shell-line">
            <div
                v-for="[key, value] in properties"
                :key="key"
                class="flex flex-col gap-1 py-2.5 sm:flex-row sm:items-baseline sm:gap-4"
            >
                <dt
                    class="font-mono text-xs text-surface-500 sm:w-48 sm:shrink-0 dark:text-surface-400"
                >
                    {{ key }}
                </dt>
                <dd class="min-w-0 break-words text-sm text-surface-900 dark:text-surface-0">
                    {{ value }}
                </dd>
            </div>
        </dl>

        <p class="mt-4 text-xs text-surface-500 dark:text-surface-400">
            The full change set remains in the database. This screen never echoes raw properties,
            causer email addresses or internal ids.
        </p>
    </section>
</template>
