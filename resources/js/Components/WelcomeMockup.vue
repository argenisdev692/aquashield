<script setup lang="ts">
/**
 * Product band — the app shown, not described.
 *
 * The hero states what AquaShield is; this proves it. Research lock (2026):
 * for a product entry the highest-leverage addition is a visual of the
 * product itself, because "interactive demo replaces the static screenshot"
 * is the top B2B conversion trend and a text-only hero asks the visitor to
 * take the claim on faith. For staff, whose alternative is opening the app,
 * one glance has to confirm it is the tool they use all day.
 *
 * ── SWAP POINT ────────────────────────────────────────────────────────────
 * `mockup` below is a PLACEHOLDER capture of the previous hero, not the
 * product. Replace the file `public/img/mockup.webp` with a real screenshot
 * of /dashboard — no other edit is needed. Keep it a clean viewport capture
 * (no browser chrome of its own; the frame below draws that). The frame
 * takes the capture's own ratio, so nothing of the product is cropped at
 * either edge; the intrinsic width/height below are declared for the
 * placeholder so the band reserves its box before the image decodes
 * (no CLS on the scroll), which is all they are for once the capture is
 * swapped — a new capture brings its own ratio, and h-auto follows it.
 *
 * Frame rules:
 *   - the frame is the only glass on this band. `--aq-glass` is a restraint
 *     token: one blurred panel per view, and WelcomePaths already spends it.
 *     This frame is a solid `--color-aq-base` plate with an accent hairline,
 *     which also keeps the screenshot legible (a blur behind UI text is
 *     noise, not depth).
 *   - the one green status dot in the chrome echoes the hero's "workspace
 *     online" pill, so the band reads as the same live surface continuing
 *     down the page rather than a poster pinned on it. It is a minuscule
 *     loop and the only ambient motion on the band.
 *   - lazy + async decoding: the band sits below the fold, so it must never
 *     compete with the hero for bandwidth on first paint.
 *
 * Motion budget: one in-view orchestration, once — the heading rises, the
 * plate lands from a 1.5% settle (the 2026 product-frame entrance: slightly
 * scaled, translateY, catching up to rest) and a single specular pass
 * sweeps the capture on the same viewport enter. Nothing loops; the pulse
 * dot above is the band's only ambient motion. Reduced motion drops the
 * transform components and the global kill block freezes the dot.
 */
import { computed, ref } from 'vue';
import { motion, useReducedMotion } from 'motion-v';

/*
 * Public route, not a bundled import: an absolute path in a static `src` is
 * rewritten as a module id by the Vue asset transform and fails the build as
 * UNRESOLVED_IMPORT, so the path travels as a runtime binding.
 */
const mockup = '/img/mockup.webp';

const reduceMotion = useReducedMotion();

/* The band's own rhythm: heading, then the plate. Same grammar as the
 * hero's entrance so both read as one page moving. */
const entrance = computed(() => ({
    hidden: {},
    show: {
        transition: {
            delayChildren: reduceMotion.value ? 0 : 0.05,
            staggerChildren: reduceMotion.value ? 0 : 0.12,
        },
    },
}));

const rise = computed(() => ({
    hidden: { opacity: 0, y: reduceMotion.value ? 0 : 22 },
    show: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.5, ease: 'circOut' },
    },
}));

/* The plate: y + 1.5% scale settle. The scale is the product-frame entrance
 * signature — the window materialises into place rather than sliding. */
const settle = computed(() => ({
    hidden: reduceMotion.value ? { opacity: 0 } : { opacity: 0, y: 26, scale: 0.985 },
    show: reduceMotion.value
        ? { opacity: 1 }
        : {
              opacity: 1,
              y: 0,
              scale: 1,
              transition: { duration: 0.55, ease: 'circOut' },
          },
}));

/*
 * One specular pass over the capture, fired by the same viewport enter that
 * runs the reveal. The sweep animates once (`1 forwards`) and parks at
 * translateX(220%) — off the plate, inside overflow-hidden — so the class
 * staying on cannot replay the animation and the band's no-loop budget
 * holds. Reduced motion is covered by the global kill block (0.01ms, one
 * pass, imperceptible).
 */
const sheened = ref(false);

const onReveal = () => {
    if (!sheened.value) {
        sheened.value = true;
    }
};
</script>

<template>
    <section aria-labelledby="mockup-heading" class="relative bg-aq-void">
        <!-- Inherit the hero's blueprint grid so the band reads as the same
             surface continuing downward, not a new page. Rhythm note: this
             band opens the page's largest seam — Why bar (`py-16/20`) onto
             this `pt`. `pt-16` leaves the seam at 144/128px (8pt multiples),
             matched to the Paths seam below (112px) instead of the 192px
             void `pt-28` opened after the page's smallest block. -->
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
            <div class="aq-grid-overlay absolute inset-0"></div>
        </div>

        <motion.div
            initial="hidden"
            whileInView="show"
            :inViewOptions="{ once: true, margin: '-80px' }"
            :variants="entrance"
            @viewportEnter="onReveal"
            class="relative mx-auto w-full max-w-5xl px-6 pt-10 pb-4 sm:px-8"
        >
            <motion.h2
                id="mockup-heading"
                :variants="rise"
                class="text-center text-xs font-semibold tracking-[0.18em] text-aq-ink-muted uppercase"
            >
                The workspace
            </motion.h2>

            <!-- Window plate. `relative` carries the sheen span; rounded on
                 every edge, hairline in the accent ramp, lifted off the
                 ground by an accent-tinted shadow so it reads as a surface
                 above the page rather than a flat image. -->
            <motion.div
                :variants="settle"
                class="relative mt-8 overflow-hidden rounded-2xl border border-aq-line bg-aq-base shadow-2xl shadow-aq-void/60 ring-1 ring-aq-accent/10"
            >
                <!-- Chrome bar: three dots, one live status dot and a title,
                     drawn rather than screenshotted, so the swap stays a
                     single file change. -->
                <div
                    class="flex items-center gap-3 border-b border-aq-line bg-aq-elevated/40 px-4 py-3"
                >
                    <span class="flex shrink-0 items-center gap-1.5" aria-hidden="true">
                        <span class="size-2.5 rounded-full bg-aq-ink-disabled"></span>
                        <span class="size-2.5 rounded-full bg-aq-ink-disabled"></span>
                        <span class="size-2.5 rounded-full bg-aq-ink-disabled"></span>
                    </span>
                    <span class="flex min-w-0 items-center gap-2">
                        <span aria-hidden="true" class="relative flex size-2 shrink-0">
                            <span
                                class="absolute inline-flex size-full rounded-full bg-aq-success animate-[aq-pulse-ring_2.4s_ease-out_infinite]"
                            ></span>
                            <span
                                class="relative inline-flex size-2 rounded-full bg-aq-success"
                            ></span>
                        </span>
                        <span class="truncate text-xs font-medium text-aq-ink-muted">
                            AquaShield · Operations workspace
                        </span>
                    </span>
                </div>

                <img
                    :src="mockup"
                    alt="The AquaShield operations workspace: the dispatch board, open work orders, monthly revenue and average response time on the dashboard."
                    width="1911"
                    height="898"
                    loading="lazy"
                    decoding="async"
                    class="block w-full bg-aq-void"
                />

                <!-- The one-shot specular pass. Same recipe as the hero CTA
                     sheen, damped for a large surface. -->
                <span
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-y-0 -left-1/3 w-1/3 bg-aq-sheen/25 blur-md"
                    :class="
                        sheened
                            ? 'opacity-100 animate-[aq-shine_1.4s_ease-out_1_forwards]'
                            : 'opacity-0'
                    "
                ></span>
            </motion.div>
        </motion.div>
    </section>
</template>
