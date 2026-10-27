<script setup lang="ts">
/**
 * The workspace surfaces: four entries into the same dashboard, each landing
 * on a real anchor (`#pipeline`, `#schedule`, `#revenue`) or the board itself.
 * This is an internal CRM, so the cards describe the modules staff open — no
 * customer intents, no "call now", no estimate funnel.
 *
 * `whileInView` drives the reveal so the section animates once per card, with
 * `inViewOptions` `once`/`margin` keeping it calm. Reduced motion collapses
 * to a static grid.
 */
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { motion, useReducedMotion } from 'motion-v';

interface WorkspaceSurface {
    readonly title: string;
    readonly body: string;
    readonly action: string;
    readonly href: string;
    readonly path: string;
}

const SURFACES: readonly WorkspaceSurface[] = [
    {
        title: 'Dispatch board',
        body: 'The live pipeline: every lead, every job, every stage — from first call to close-out.',
        action: 'Open pipeline',
        href: '/dashboard#pipeline',
        path: 'M4 6h4.5v12H4zM9.75 6h4.5v8h-4.5zM15.5 6H20v10h-4.5z',
    },
    {
        title: 'Jobs & work orders',
        body: "Today's crews, the next visit and the work that is already on the calendar.",
        action: 'Open schedule',
        href: '/dashboard#schedule',
        path: 'M7 4.5v3M17 4.5v3M4.5 9.5h15M5.5 6.5h13a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1h-13a1 1 0 0 1-1-1v-12a1 1 0 0 1 1-1Z',
    },
    {
        title: 'Customers & properties',
        body: 'Contacts, addresses and job history, attached to the work order they belong to.',
        action: 'Open workspace',
        href: '/dashboard',
        path: 'M16 19v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V19M9.5 9.5a2.75 2.75 0 1 0 0-5.5 2.75 2.75 0 0 0 0 5.5ZM17 11a2.5 2.5 0 1 0 0-5M21 19v-1.5a4 4 0 0 0-3-3.87',
    },
    {
        title: 'Invoices & claims',
        body: 'Revenue, outstanding balances and insurance claims in the same view.',
        action: 'Open revenue',
        href: '/dashboard#revenue',
        path: 'M7 3.5h10v17l-2.5-1.5L12 20.5 9.5 19 7 20.5zM9.5 8h5M9.5 11.5h5M9.5 15h3',
    },
];

const reduceMotion = useReducedMotion();

const reveal = computed(() => ({
    hidden: { opacity: 0, y: reduceMotion.value ? 0 : 28 },
    show: (index: number) => ({
        opacity: 1,
        y: 0,
        transition: {
            duration: 0.45,
            ease: 'circOut',
            delay: reduceMotion.value ? 0 : index * 0.08,
        },
    }),
}));
</script>

<template>
    <section aria-labelledby="paths-heading" class="relative bg-aq-void">
        <div class="mx-auto w-full max-w-6xl px-6 py-12 sm:px-8 lg:px-12">
            <motion.div
                initial="hidden"
                whileInView="show"
                :inViewOptions="{ once: true, margin: '-80px' }"
                :variants="reveal"
                :custom="0"
                class="max-w-2xl"
            >
                <p class="text-xs font-semibold tracking-[0.18em] text-aq-accent uppercase">
                    Inside the workspace
                </p>
                <h2
                    id="paths-heading"
                    class="mt-4 text-3xl font-bold tracking-[-0.02em] text-balance text-aq-ink sm:text-4xl"
                >
                    One workspace, every job.
                </h2>
                <p class="mt-4 max-w-xl leading-relaxed text-aq-ink-soft">
                    The board is the front door. Each surface below opens the same live job — not a
                    copy of it.
                </p>
            </motion.div>

            <div class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <motion.div
                    v-for="(surface, surfaceIndex) in SURFACES"
                    :key="surface.title"
                    initial="hidden"
                    whileInView="show"
                    :inViewOptions="{ once: true, margin: '-60px' }"
                    :variants="reveal"
                    :custom="surfaceIndex"
                    class="h-full"
                >
                    <Link
                        :href="surface.href"
                        class="group flex h-full flex-col rounded-2xl border border-aq-line bg-aq-elevated/40 p-6 transition-[transform,border-color,background-color] duration-200 hover:-translate-y-1 hover:border-aq-line-strong hover:bg-aq-overlay/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transform-none motion-reduce:transition-none"
                    >
                        <span
                            class="inline-flex size-11 items-center justify-center rounded-xl border border-aq-line bg-aq-base/60 text-aq-accent transition-transform duration-300 group-hover:scale-105 motion-reduce:transform-none"
                        >
                            <svg
                                aria-hidden="true"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.6"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="size-5"
                            >
                                <path :d="surface.path" />
                            </svg>
                        </span>
                        <span class="mt-5 text-base font-bold text-aq-ink">{{
                            surface.title
                        }}</span>
                        <span class="mt-2 flex-1 text-sm leading-relaxed text-aq-ink-soft">
                            {{ surface.body }}
                        </span>
                        <span
                            class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-aq-accent"
                        >
                            {{ surface.action }}
                            <svg
                                aria-hidden="true"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="size-4 transition-transform duration-200 group-hover:translate-x-0.5 motion-reduce:transform-none"
                            >
                                <path d="M5 12h14m-6-6 6 6-6 6" />
                            </svg>
                        </span>
                    </Link>
                </motion.div>
            </div>
        </div>
    </section>
</template>
