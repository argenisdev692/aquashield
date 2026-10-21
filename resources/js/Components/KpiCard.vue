<script setup lang="ts">
/**
 * KpiCard — the dashboard metric tile.
 *
 * The 2026 card recipe, token-first:
 * - Entrance: one `.aq-rise` keyframe from globals.css; the per-tile stagger
 *   is an inline `animation-delay`, so a whole KPI row costs a single
 *   animation — no motion-v on dashboard screens. `backwards` fill-mode holds
 *   the pre-state during the delay, and `prefers-reduced-motion` drops the
 *   animation outright.
 * - Hover micro-delight: a 2px lift (the Tailwind v4 `translate` individual
 *   transform property — do NOT transition `transform`; a grid layout switch
 *   would drag that along and make cards slide from the grid corner) plus a
 *   tinted shadow-xl on hover (0.10 black in light, 0.06 white in dark — a
 *   plain `shadow-xl` is invisible on the dark navy card), 200 ms ease-out,
 *   gated by `motion-reduce:transition-none`. Never a scale change: the 1.04
 *   hover ceiling is best met by not scaling at all.
 * - A one-pixel crown hairline brightens on hover. It carries `--aq-line`,
 *   the accent token, so the tile reads as lifted light, not as a new colour.
 * - The trend chip is state vocabulary: up / down / flat map onto the
 *   success / danger / muted tokens. Data, not decoration.
 *
 * The page owns the formatting (Intl): `value` arrives pre-formatted.
 */
import Card from '@/volt/Card.vue';

type Trend = 'up' | 'down' | 'flat';

interface Props {
    /** Small-caps eyebrow above the value, e.g. "New leads · 7 days". */
    label: string;
    /** Pre-formatted display value — the page owns its Intl formatting. */
    value: string;
    /** Delta sentence in the chip; null falls back to "holding steady". */
    delta: string | null;
    trend: Trend;
    /** Zero-based position in the row, driving the CSS entrance stagger. */
    index?: number;
    /** PrimeIcons class for the tile's corner badge, e.g. "pi pi-users". */
    icon?: string;
}

const { label, value, delta, trend, index = 0, icon } = defineProps<Props>();

/**
 * The trend arrows as path data instead of three inline SVG blocks.
 * Stroke width, cap and join are fixed by the <svg> shell in the template.
 */
const TREND_PATHS: Record<Trend, readonly string[]> = {
    up: ['M3 17l6-6 4 4 8-8', 'M14 7h7v7'],
    down: ['M3 7l6 6 4-4 8 8', 'M14 17h7v-7'],
    flat: ['M3 12h18'],
};

/** Colour of the delta chip text — the chip background is trend-neutral. */
const TREND_TONE: Record<Trend, string> = {
    up: 'text-aq-success',
    down: 'text-aq-danger',
    flat: 'text-surface-500 dark:text-surface-400',
};

const TREND_CHIP_BG = 'bg-surface-100 dark:bg-surface-800';

/**
 * 70 ms per tile, clamped: a twelve-tile grid must never wait the better
 * part of a second before its last card arrives.
 */
const staggerDelay = (position: number): string => `${Math.min(position * 70, 280)}ms`;
</script>

<template>
    <div class="aq-rise" :style="{ animationDelay: staggerDelay(index) }">
        <Card
            class="group relative h-full overflow-hidden transition-[translate,box-shadow] duration-200 ease-out motion-reduce:transition-none hover:-translate-y-0.5 hover:shadow-[0_20px_25px_-5px_rgb(0_0_0/0.10),0_8px_10px_-6px_rgb(0_0_0/0.10)] dark:hover:shadow-[0_20px_25px_-5px_rgb(255_255_255/0.06),0_8px_10px_-6px_rgb(255_255_255/0.06)] before:pointer-events-none before:absolute before:inset-x-0 before:top-0 before:h-px before:bg-[var(--aq-line)] before:opacity-60 hover:before:opacity-100 before:content-[''] before:transition-opacity before:duration-300 motion-reduce:before:transition-none"
        >
            <template #title>
                <span
                    class="text-xs font-semibold uppercase tracking-[0.14em] text-surface-500 dark:text-surface-400"
                >
                    {{ label }}
                </span>
            </template>
            <template #content>
                <!-- The badge must live in a named slot: PrimeVue's Card has no
                     default slot, so bare content is silently dropped. -->
                <span
                    v-if="icon"
                    class="absolute top-5 right-5 grid size-10 place-items-center rounded-lg bg-surface-100 text-xl text-aq-accent transition-colors duration-200 group-hover:bg-aq-accent/10 motion-reduce:transition-none dark:bg-surface-800"
                    aria-hidden="true"
                >
                    <i :class="icon" />
                </span>
                <p
                    class="text-3xl font-bold tracking-tight text-surface-900 tabular-nums dark:text-surface-0"
                >
                    {{ value }}
                </p>
                <p class="mt-1.5 flex items-center">
                    <span
                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="[TREND_CHIP_BG, TREND_TONE[trend]]"
                    >
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="size-3.5"
                        >
                            <path v-for="d in TREND_PATHS[trend]" :key="d" :d="d" />
                        </svg>
                        <span class="truncate">{{ delta ?? 'holding steady' }}</span>
                    </span>
                </p>
            </template>
        </Card>
    </div>
</template>
