<script setup lang="ts">
/**
 * The floating "Today at AquaShield" bar — operational chips lifted into
 * their own slot between the welcome hero and the workspace surfaces.
 *
 * These are categories, not fabricated counters: the live numbers land with
 * the first domain module, and a static "12 claims" would be a lie staff can
 * disprove by opening the board. Same levitation: each chip rides
 * `aq-levitate` on a desynchronised delay (`index * -2s`), so the group
 * breathes instead of pulsing in unison. The bar is the one glass panel on
 * the page — the restraint rule allows exactly one blurred surface per view.
 *
 * Reveal is `whileInView` + `once`, like every other welcome section: a band
 * that has already been scrolled past must not replay. Under reduced motion
 * the levitation loop stops (globals.css kills ambient animations) and the
 * entrance keeps only its fade.
 */
import { computed } from 'vue';
import { motion, useReducedMotion } from 'motion-v';

interface Signal {
    readonly path: string;
    readonly label: string;
}

const TODAY: readonly Signal[] = [
    {
        path: 'M12 7v5.2l3.4 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        label: 'On-call rotation',
    },
    {
        path: 'M7 3.5h7.5L18.5 8v12.5a1 1 0 0 1-1 1h-11a1 1 0 0 1-1-1v-16a1 1 0 0 1 1-1Zm7 0V8h4.5M9 13h6M9 17h4',
        label: 'Claims in flight',
    },
    {
        path: 'M16 19v-1.5a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4V19M9.5 9.5a2.75 2.75 0 1 0 0-5.5 2.75 2.75 0 0 0 0 5.5ZM17 11a2.5 2.5 0 1 0 0-5M21 19v-1.5a4 4 0 0 0-3-3.87',
        label: 'Crews today',
    },
];

const reduceMotion = useReducedMotion();

const reveal = computed(() => ({
    hidden: { opacity: 0, y: reduceMotion.value ? 0 : 24 },
    show: { opacity: 1, y: 0, transition: { duration: 0.45, ease: 'circOut' } },
}));
</script>

<template>
    <section aria-labelledby="welcome-why-heading" class="relative bg-aq-void">
        <div class="mx-auto w-full max-w-6xl px-6 py-8 sm:px-8 sm:py-10 lg:px-12">
            <motion.div
                initial="hidden"
                whileInView="show"
                :inViewOptions="{ once: true, margin: '-80px' }"
                :variants="reveal"
                class="aq-glass flex flex-wrap items-center gap-x-8 gap-y-5 rounded-2xl px-6 py-5"
            >
                <h2
                    id="welcome-why-heading"
                    class="text-xs font-semibold tracking-[0.18em] text-aq-ink-muted uppercase"
                >
                    Today at&nbsp;AquaShield
                </h2>

                <ul class="flex flex-wrap items-center gap-3">
                    <li
                        v-for="(signal, signalIndex) in TODAY"
                        :key="signal.label"
                        class="flex items-center gap-2.5 rounded-xl border border-aq-line bg-aq-elevated/60 px-4 py-3 animate-[aq-levitate_6s_ease-in-out_infinite] motion-reduce:animate-none"
                        :style="{ animationDelay: `${signalIndex * -2}s` }"
                    >
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.6"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="size-4 shrink-0 text-aq-accent"
                        >
                            <path :d="signal.path" />
                        </svg>
                        <span class="text-xs font-semibold text-aq-ink-soft">{{
                            signal.label
                        }}</span>
                    </li>
                </ul>
            </motion.div>
        </div>
    </section>
</template>
