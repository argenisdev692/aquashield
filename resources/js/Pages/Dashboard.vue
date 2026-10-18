<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { motion, useReducedMotion } from 'motion-v';
import AppLayout from '@/Layouts/AppLayout.vue';
import Card from '@/volt/Card.vue';
import Skeleton from '@/volt/Skeleton.vue';

interface Kpi {
    readonly label: string;
    readonly value: string;
    readonly delta: string;
    readonly trend: 'up' | 'down' | 'flat';
}

const loading = ref(true);
const kpis: readonly Kpi[] = [
    { label: 'Active jobs', value: '12', delta: '+2 this week', trend: 'up' },
    { label: 'Open tickets', value: '3', delta: '1 overdue', trend: 'down' },
    { label: 'Revenue (mo)', value: '$18.4k', delta: '+8% vs last mo', trend: 'up' },
    { label: 'Avg. response', value: '47 min', delta: 'target 60 min', trend: 'flat' },
];

onMounted(() => {
    window.setTimeout(() => {
        loading.value = false;
    }, 320);
});

const reduceMotion = useReducedMotion();

const containerVariants = computed(() => ({
    hidden: {},
    show: { transition: { staggerChildren: reduceMotion.value ? 0 : 0.05 } },
}));

const itemVariants = computed(() => ({
    hidden: { opacity: 0, y: reduceMotion.value ? 0 : 12 },
    show: { opacity: 1, y: 0, transition: { duration: 0.25, ease: 'easeOut' } },
}));
</script>

<template>
    <AppLayout title="Dashboard">
        <motion.section
            :variants="containerVariants"
            initial="hidden"
            animate="show"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <motion.div v-for="kpi in kpis" :key="kpi.label" :variants="itemVariants" class="group">
                <Card
                    class="h-full transition-shadow duration-200 hover:shadow-lg motion-reduce:transition-none"
                >
                    <template #title>{{ kpi.label }}</template>
                    <template #content>
                        <template v-if="loading">
                            <Skeleton class="mb-2 h-8 w-24" />
                            <Skeleton class="h-4 w-32" />
                        </template>
                        <template v-else>
                            <p
                                class="text-3xl font-bold tracking-tight text-surface-900 dark:text-surface-0 tabular-nums"
                            >
                                {{ kpi.value }}
                            </p>
                            <p
                                class="mt-1 text-xs font-medium"
                                :class="
                                    kpi.trend === 'up'
                                        ? 'text-aq-success'
                                        : kpi.trend === 'down'
                                          ? 'text-aq-danger'
                                          : 'text-surface-500 dark:text-surface-400'
                                "
                            >
                                {{ kpi.delta }}
                            </p>
                        </template>
                    </template>
                </Card>
            </motion.div>
        </motion.section>

        <section class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-[2fr_1fr]">
            <Card>
                <template #title>Recent activity</template>
                <template #subtitle>Latest updates across your workspace</template>
                <template #content>
                    <div v-if="loading" class="space-y-3">
                        <Skeleton v-for="n in 4" :key="n" class="h-10 w-full" />
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center gap-2 py-10 text-center"
                    >
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            class="size-10 text-surface-300 dark:text-surface-600"
                        >
                            <path d="M12 6v6l4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <p class="text-sm font-medium text-surface-700 dark:text-surface-200">
                            No recent activity yet
                        </p>
                        <p class="max-w-xs text-xs text-surface-500 dark:text-surface-400">
                            Events will appear here once you start working in the workspace.
                        </p>
                    </div>
                </template>
            </Card>

            <Card>
                <template #title>Quick actions</template>
                <template #content>
                    <ul class="flex flex-col gap-2">
                        <li>
                            <button
                                type="button"
                                class="w-full rounded-lg border border-surface-200 bg-surface-0 px-3 py-2.5 text-left text-sm font-medium text-surface-700 transition-colors duration-150 hover:bg-surface-100 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-100 dark:hover:bg-surface-700 motion-reduce:transition-none"
                            >
                                New customer
                            </button>
                        </li>
                        <li>
                            <button
                                type="button"
                                class="w-full rounded-lg border border-surface-200 bg-surface-0 px-3 py-2.5 text-left text-sm font-medium text-surface-700 transition-colors duration-150 hover:bg-surface-100 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-100 dark:hover:bg-surface-700 motion-reduce:transition-none"
                            >
                                Create invoice
                            </button>
                        </li>
                        <li>
                            <button
                                type="button"
                                class="w-full rounded-lg border border-surface-200 bg-surface-0 px-3 py-2.5 text-left text-sm font-medium text-surface-700 transition-colors duration-150 hover:bg-surface-100 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-100 dark:hover:bg-surface-700 motion-reduce:transition-none"
                            >
                                View reports
                            </button>
                        </li>
                    </ul>
                </template>
            </Card>
        </section>
    </AppLayout>
</template>
