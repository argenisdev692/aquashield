<script setup lang="ts">
/**
 * Scroll-narrative companion to the entry hero: four intent paths revealed as
 * the visitor scrolls, each landing on a real destination — the phone line.
 * No invented destinations, no dead buttons.
 *
 * `whileInView` drives the reveal so the section animates once per card, with
 * `inViewOptions` `once`/`margin` keeping it calm. Reduced motion
 * collapses to a static grid.
 */
import { computed } from 'vue';
import { motion, useReducedMotion } from 'motion-v';

interface WelcomePath {
    readonly title: string;
    readonly body: string;
    readonly action: string;
    readonly href: string;
    readonly path: string;
}

const PATHS: readonly WelcomePath[] = [
    {
        title: 'Active leak or flood',
        body: 'Water spreading now. One tap reaches a live dispatcher — no forms, no hold music.',
        action: 'Call now',
        href: 'tel:+17135876423',
        path: 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z',
    },
    {
        title: 'Water mitigation',
        body: 'Extraction, structural dry-out and mold prevention — photographed and documented for your claim.',
        action: 'Call now',
        href: 'tel:+17135876423',
        path: 'M12 3.5s6 6.6 6 11a6 6 0 1 1-12 0c0-4.4 6-11 6-11Z',
    },
    {
        title: 'Roofing',
        body: 'Storm repair through full replacement. Licensed and insured, with direct insurance billing.',
        action: 'Call now',
        href: 'tel:+17135876423',
        path: 'M4 11.5 12 4.5l8 7M6.5 10v9.5h11V10',
    },
    {
        title: 'Free estimate',
        body: 'Tell us what happened and we will tell you what it takes to put it right — no obligation.',
        action: 'Get estimated',
        href: 'tel:+17135876423',
        path: 'M9 4.5h6v4h-6zM7 6.5H5.5V20h13V6.5H17M9 12h6M9 15.5h4',
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
        <div class="mx-auto w-full max-w-6xl px-6 py-24 sm:px-8 lg:px-12">
            <motion.div
                initial="hidden"
                whileInView="show"
                :inViewOptions="{ once: true, margin: '-80px' }"
                :variants="reveal"
                :custom="0"
                class="max-w-2xl"
            >
                <p class="text-xs font-semibold tracking-[0.18em] text-aq-accent uppercase">
                    Choose your path
                </p>
                <h2
                    id="paths-heading"
                    class="mt-4 text-3xl font-bold tracking-[-0.02em] text-balance text-aq-ink sm:text-4xl"
                >
                    One page, four front doors.
                </h2>
                <p class="mt-4 max-w-xl leading-relaxed text-aq-ink-soft">
                    Restoration is never one-size-fits-all. Start from the situation you are in and
                    every step after it already knows the context.
                </p>
            </motion.div>

            <div class="mt-12 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <motion.a
                    v-for="(card, cardIndex) in PATHS"
                    :key="card.title"
                    :href="card.href"
                    initial="hidden"
                    whileInView="show"
                    :inViewOptions="{ once: true, margin: '-60px' }"
                    :variants="reveal"
                    :custom="cardIndex"
                    class="group flex flex-col rounded-2xl border border-[var(--aq-line)] bg-aq-elevated/40 p-6 transition-[transform,border-color,background-color] duration-200 hover:-translate-y-1 hover:border-[var(--aq-line-strong)] hover:bg-aq-overlay/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transform-none motion-reduce:transition-none"
                >
                    <span
                        class="inline-flex size-11 items-center justify-center rounded-xl border border-[var(--aq-line)] bg-aq-base/60 text-aq-accent transition-transform duration-300 group-hover:scale-105 motion-reduce:transform-none"
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
                            <path :d="card.path" />
                        </svg>
                    </span>
                    <span class="mt-5 text-base font-bold text-aq-ink">{{ card.title }}</span>
                    <span class="mt-2 flex-1 text-sm leading-relaxed text-aq-ink-soft">
                        {{ card.body }}
                    </span>
                    <span
                        class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-aq-accent"
                    >
                        {{ card.action }}
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
                </motion.a>
            </div>
        </div>
    </section>
</template>
