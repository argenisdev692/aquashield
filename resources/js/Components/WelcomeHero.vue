<script setup lang="ts">
/**
 * Entry hero for the internal CRM — text-only, one hierarchy, one action.
 *
 * This is the front door of a staff tool, not a public marketing site. That
 * single fact drives every call below.
 *
 * Research lock (2026): text-only heroes with oversized typography are the
 * dominant pattern for confident entries (UX Pilot trend #13), and every
 * top guide converges on the same rule — headline first, one line of support,
 * ONE primary action, nothing else competing above the fold. Minimalism is
 * generous whitespace plus simple type, and the oversized headline *is* the
 * design.
 *
 * For an internal tool the one action is direct entry, not lead capture. The
 * established pattern for authenticated products is an explicit "Open
 * Dashboard" CTA instead of a marketing or waitlist button: the visitor is
 * staff who already has an account, so there is nothing to sell and nothing
 * to capture. Marketing CTAs ("Get my free estimate", a `tel:` link) promised
 * a commitment the app cannot fulfil.
 *
 * History of this block, kept because the current shape is the residue of
 * two prior revisions:
 *   - The first stacked eight blocks: eyebrow pill, headline, paragraph, a
 *     four-way intent switcher, two CTAs, a dynamic promise line, a
 *     four-cell stat grid. The switcher duplicated WelcomePaths' four
 *     intents one section down — two navigations for the same intent.
 *   - The second kept the marketing framing on a CRM front door: both CTAs
 *     resolved to the same `tel:` number, dressed as primary and secondary,
 *     so the two-intent hierarchy was cosmetic. The proof line carried
 *     invented public-facing claims ("430 projects restored").
 *
 * What stays, and why:
 *   - the live Houston clock — real client-side data, proves the page is on
 *   - the word-by-word kinetic reveal — the one motion moment of the page
 *   - a three-field gradient mesh (aqua / steel navy / signal green) + the
 *     blueprint grid + the bottom fade (structure, not decoration; the old
 *     second hard-edged orb was light pollution — radial-falloff fields
 *     read as one atmosphere, three competing spotlights do not)
 *   - the brand headline — it is the visual asset of the fold and states
 *     what the business protects; the line below it now says what the tool
 *     is, which is what staff need to know on arrival
 *   - one quiet capability line — what the workspace contains, no invented
 *     metrics. Internal users can verify every claim by opening the app, so
 *     fabricated numbers cost trust and buy nothing.
 *
 * One action means no secondary CTA. A second button next to "Open
 * dashboard" would either duplicate the destination or invent a second
 * intent the app does not have.
 *
 * Motion budget: one entrance stagger, CTA micro-interactions, three ambient
 * mesh drifts and the kinetic gradient pan on the closing line. Everything
 * collapses to static under prefers-reduced-motion — the global kill block
 * freezes the pan on its base stop, `motion-reduce:animate-none` stops
 * the mesh.
 */
import { computed, onUnmounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { motion, useReducedMotion } from 'motion-v';

/* Greeting and clock come from the SAME clock — Houston — not from the
 * visitor's device zone. The old code paired a local-hour greeting with a
 * fixed America/Chicago readout, so a visitor in Tokyo at 08:00 local read
 * "Good evening 2:34 PM" on the same line. */
const HOUSTON = 'America/Chicago';

const houstonClock = new Intl.DateTimeFormat('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    timeZone: HOUSTON,
});

const houstonHour = new Intl.DateTimeFormat('en-US', {
    hour: 'numeric',
    hourCycle: 'h23',
    timeZone: HOUSTON,
});

const hour = Number(houstonHour.format(new Date()));

const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';

const houstonTime = ref(houstonClock.format(new Date()));

const clock = window.setInterval(() => {
    houstonTime.value = houstonClock.format(new Date());
}, 30_000);

onUnmounted(() => {
    window.clearInterval(clock);
});

/* The capability line is what the workspace contains, one sentence with dot
 * separators, not a <dl>: four bare nouns read fine aloud as a sentence, and
 * a definition list would add screen-reader ceremony to chrome. These are
 * claims about the product, verifiable by opening it — the previous public
 * marketing metrics are gone because staff can count the real jobs. */
const CAPABILITIES: readonly string[] = [
    'Live dispatch board',
    'Jobs & work orders',
    'Customers & properties',
    'Invoices & insurance claims',
];

const reduceMotion = useReducedMotion();

const entrance = computed(() => ({
    hidden: {},
    show: {
        transition: {
            delayChildren: reduceMotion.value ? 0 : 0.08,
            staggerChildren: reduceMotion.value ? 0 : 0.09,
        },
    },
}));

const rise = computed(() => ({
    hidden: { opacity: 0, y: reduceMotion.value ? 0 : 22 },
    show: {
        opacity: 1,
        y: 0,
        transition: { duration: 0.45, ease: 'circOut' },
    },
}));

/* Word reveal: 0.55em of lift plus a short blur burn-off. The filter runs
 * once per load on a handful of words — a one-shot entrance, never a
 * loop, so the paint cost lands inside the entrance budget. Reduced
 * motion drops both and keeps the plain fade. */
const word = computed(() => ({
    hidden: {
        opacity: 0,
        y: reduceMotion.value ? 0 : '0.55em',
        filter: reduceMotion.value ? 'blur(0px)' : 'blur(6px)',
    },
    show: {
        opacity: 1,
        y: 0,
        filter: 'blur(0px)',
        transition: { duration: 0.5, ease: 'circOut' },
    },
}));

const headline = ['Water wins', 'every minute', 'you wait.'];
</script>

<template>
    <section
        class="relative isolate flex min-h-[100svh] flex-col overflow-hidden bg-aq-void"
        aria-labelledby="welcome-heading"
    >
        <!-- Ambient field: a three-field gradient mesh, under the grid.
             Radial-gradient falloff does what blur(140px) did on the old
             solid orb, without the per-frame filter bill. -->
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
            <div
                class="aq-mesh-orb aq-mesh-aqua -top-44 left-1/2 h-152 w-248 -translate-x-1/2 animate-[aq-orb-drift_26s_ease-in-out_infinite] motion-reduce:animate-none"
            ></div>
            <div
                class="aq-mesh-orb aq-mesh-navy top-[16%] -right-44 h-168 w-168 animate-[aq-orb-drift_31s_ease-in-out_-14s_infinite_reverse] motion-reduce:animate-none"
            ></div>
            <div
                class="aq-mesh-orb aq-mesh-green -bottom-40 left-[6%] h-120 w-120 animate-[aq-orb-drift_37s_ease-in-out_-21s_infinite] motion-reduce:animate-none"
            ></div>
            <div class="aq-grid-overlay absolute inset-0"></div>
        </div>
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 bottom-0 -z-10 h-48 bg-linear-to-t from-aq-void to-transparent"
        ></div>

        <motion.div
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center justify-center px-6 pt-32 pb-24 text-center sm:px-8"
            :variants="entrance"
            initial="hidden"
            animate="show"
        >
            <motion.p
                :variants="rise"
                class="inline-flex items-center gap-2.5 text-xs font-medium tracking-wide text-aq-ink-muted sm:text-sm"
            >
                <span class="relative flex size-2">
                    <span
                        class="absolute inline-flex size-full rounded-full bg-aq-success animate-[aq-pulse-ring_2.4s_ease-out_infinite]"
                    ></span>
                    <span class="relative inline-flex size-2 rounded-full bg-aq-success"></span>
                </span>
                {{ greeting }} — workspace online · Houston {{ houstonTime }}
            </motion.p>

            <h1
                id="welcome-heading"
                class="mt-8 text-[clamp(3rem,9vw,7rem)] leading-[0.98] font-bold tracking-[-0.04em] text-aq-ink"
            >
                <span class="sr-only">{{ headline.join(' ') }}</span>
                <motion.span
                    v-for="(line, lineIndex) in headline"
                    :key="line"
                    aria-hidden="true"
                    class="block"
                    :variants="{ hidden: {}, show: { transition: { staggerChildren: 0.05 } } }"
                >
                    <motion.span
                        v-for="(token, tokenIndex) in line.split(' ')"
                        :key="`${lineIndex}-${tokenIndex}`"
                        class="mr-[0.24em] inline-block last:mr-0 will-change-transform"
                        :class="lineIndex === 2 ? 'aq-grad-ink' : ''"
                        :variants="word"
                    >
                        {{ token }}
                    </motion.span>
                </motion.span>
            </h1>

            <motion.p
                :variants="rise"
                class="mt-7 max-w-xl text-base leading-relaxed text-pretty text-aq-ink-soft sm:text-lg"
            >
                The internal workspace for AquaShield operations — dispatch, jobs, customers,
                invoicing and insurance claims in one place.
            </motion.p>

            <motion.div :variants="rise" class="mt-10 flex justify-center">
                <Link
                    href="/dashboard"
                    class="group relative inline-flex h-13 items-center justify-center gap-2 overflow-hidden rounded-xl bg-aq-accent px-8 text-sm font-semibold text-aq-void shadow-lg shadow-aq-accent/25 transition-[transform,box-shadow] duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-aq-accent/35 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent active:translate-y-0 motion-reduce:transform-none motion-reduce:transition-none"
                >
                    <span
                        aria-hidden="true"
                        class="pointer-events-none absolute inset-y-0 -left-1/3 w-1/3 bg-aq-sheen/45 opacity-0 blur-sm transition-opacity duration-300 group-hover:opacity-100 group-hover:animate-[aq-shine_1.1s_ease-out] group-focus-visible:opacity-100 group-focus-visible:animate-[aq-shine_1.1s_ease-out]"
                    ></span>
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="size-4 shrink-0"
                    >
                        <path
                            d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-13Z"
                        />
                        <path d="M4 9.5h16M9 9.5V20" />
                    </svg>
                    Open dashboard
                </Link>
            </motion.div>

            <motion.p
                :variants="rise"
                class="mt-12 flex flex-wrap items-center justify-center gap-x-3 gap-y-1.5 text-sm text-aq-ink-muted"
            >
                <template v-for="(capability, capabilityIndex) in CAPABILITIES" :key="capability">
                    <span
                        v-if="capabilityIndex > 0"
                        aria-hidden="true"
                        class="text-aq-ink-disabled"
                    >
                        ·
                    </span>
                    <span>{{ capability }}</span>
                </template>
            </motion.p>
        </motion.div>

        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 bottom-5 flex justify-center"
        >
            <span
                class="animate-[aq-scroll-cue_2.6s_ease-in-out_infinite] motion-reduce:animate-none"
            >
                <svg
                    aria-hidden="true"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.6"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="size-4 text-aq-ink-muted"
                >
                    <path d="m6 9 6 6 6-6" />
                </svg>
            </span>
        </div>
    </section>
</template>
