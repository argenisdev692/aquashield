/**
 * KpiCard — the dashboard metric tile.
 *
 * Mirrors the Vue KpiCard recipe: entrance stagger (`aq-rise`), 2px hover
 * lift and an in-flow icon badge. The page owns formatting.
 *
 * Two decisions the type scale forces, not taste:
 *
 * - **The value is 24px, not 30px.** A data-dense dashboard runs on a tight
 *   scale — roughly 11px to 24px, six or seven steps — because size alone
 *   runs out of room fast and a metric that shouts crowds out its label.
 *   24px is also the size that suits *this* tile: a metric value reads
 *   confident in a tile about three grid columns wide and mistaken in one
 *   that spans a single column, and these tiles are ~290-300px — four
 *   columns of the dashboard grid — at every desktop width, because the
 *   content column caps itself at `max-w-7xl`.
 * - **The weight carries the emphasis, not the size.** 400 for values, 500
 *   or 600 for labels, 700 for the one or two numbers that matter most. The
 *   value is the number that matters, so it takes 700 and the label keeps
 *   600 — a three-step ladder that lets the number stay small and still
 *   dominate the card.
 * - **One border, not two.** The card already paints a 1px edge; a second
 *   hairline laid inside the same edge reads as a rendering fault and turns a
 *   row of tiles into a grid of grids.
 */
import { cn } from '@/lib/utils';

type Trend = 'up' | 'down' | 'flat';

interface KpiCardProps {
    /** Small-caps eyebrow above the value, e.g. "New leads · 7 days". */
    label?: string;
    /** Backwards-compatible alias for `label` (Users screen). */
    title?: string;
    /** Pre-formatted display value — the page owns its Intl formatting. */
    value: string | number;
    /** Delta sentence in the chip; null renders no chip rather than inventing a trend. */
    delta?: string | null;
    /** Legacy alias for `delta` (Users screen). */
    subtitle?: string;
    trend?: Trend;
    /** Zero-based position in the row, driving the CSS entrance stagger. */
    index?: number;
    icon?: React.ReactNode;
}

const TREND_PATHS: Record<Trend, readonly string[]> = {
    up: ['M3 17l6-6 4 4 8-8', 'M14 7h7v7'],
    down: ['M3 7l6 6 4-4 8 8', 'M14 17h7v-7'],
    flat: ['M3 12h18'],
};

/**
 * The arrow carries the colour; the label stays neutral. The success token as
 * 12px text on a light chip measures ~1.5:1, and a status colour is not worth a
 * WCAG failure. Direction is still encoded three ways: arrow, sign and words.
 */
const TREND_TONE: Record<Trend, string> = {
    up: 'text-aq-success-strong',
    down: 'text-aq-danger-strong',
    flat: 'text-surface-500 dark:text-surface-300',
};

const staggerDelay = (position: number): string => `${Math.min(position * 70, 280)}ms`;

export default function KpiCard({
    label,
    title,
    value,
    delta,
    subtitle,
    trend = 'flat',
    index = 0,
    icon,
}: KpiCardProps) {
    const eyebrow = label ?? title ?? '';
    const chipLabel = delta ?? subtitle ?? null;

    return (
        <div className="aq-rise" style={{ animationDelay: staggerDelay(index) }}>
            {/*
             * The edge is one step above the card's own surface, so a panel
             * (surface-900) and the card that sits on it (surface-800) never
             * draw the same weight twice. The frame keeps its own token; the
             * row of tiles recedes instead of competing with it.
             *
             * No resting shadow. A tile sits on the same plane as the page
             * behind it and is separated by its border and its surface, not
             * by a drop shadow — a shadow here is a third depth cue saying
             * the same thing as the border, and in dark mode it does nothing
             * at all. The hover lift keeps its shadow: that one is an
             * interaction cue, not static depth.
             */}
            <div className="group relative h-full overflow-hidden rounded-xl border border-surface-200 bg-surface-0 transition-[translate,box-shadow] duration-200 ease-out hover:-translate-y-0.5 hover:shadow-(--aq-shadow-lift) dark:border-surface-800 dark:bg-surface-900 motion-reduce:transition-none">
                <div className="p-4 sm:p-5">
                    <div className="flex items-start justify-between gap-3">
                        {/*
                         * The eyebrow is body text, so it carries the 4.5:1
                         * floor: -400 on a -900 card measures 4.25:1 and
                         * fails. -300 measures 7.3:1.
                         */}
                        <span className="min-w-0 text-xs font-semibold uppercase tracking-[0.14em] text-surface-500 dark:text-surface-300">
                            {eyebrow}
                        </span>
                        {icon && (
                            <span
                                className="grid size-9 shrink-0 place-items-center rounded-lg bg-surface-100 text-lg text-aq-accent transition-colors duration-200 group-hover:bg-aq-accent/10 dark:bg-surface-800 motion-reduce:transition-none"
                                aria-hidden="true"
                            >
                                {icon}
                            </span>
                        )}
                    </div>

                    <p className="mt-3 text-2xl leading-tight font-bold tracking-tight text-surface-900 tabular-nums dark:text-surface-0">
                        {value}
                    </p>

                    {chipLabel !== null && (
                        <p className="mt-1.5 flex items-center">
                            <span className="inline-flex items-center gap-1 rounded-full bg-surface-100 px-2 py-0.5 text-xs font-medium text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                                <svg
                                    aria-hidden="true"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth={2}
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    className={cn('size-3.5', TREND_TONE[trend])}
                                >
                                    {TREND_PATHS[trend].map((path) => (
                                        <path key={path} d={path} />
                                    ))}
                                </svg>
                                <span className="truncate">{chipLabel}</span>
                            </span>
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
