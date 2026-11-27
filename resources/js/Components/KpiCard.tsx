/**
 * KpiCard — the dashboard metric tile.
 *
 * A metric tile has one job: let the eye compare four numbers without reading
 * four cards. The label sits on a single line, the value is the only loud type
 * on the row, the sparkline shows the period's shape and the chip answers
 * "compared with what?" underneath. The icon is a quiet anchor, not a second
 * focal point.
 *
 * The tile is optionally a link. When the metric already maps to a filtered
 * list, the whole surface becomes the target (a 296px hit area is far easier to
 * acquire than a "view" affordance), with one focus ring around the tile.
 */
import { Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { sparklineGeometry } from '@/lib/sparkline';
import { cn } from '@/lib/utils';

type Trend = 'up' | 'down' | 'flat';

interface KpiCardProps {
    /** Small-caps eyebrow above the value, e.g. "New leads". */
    label?: string;
    /** Backwards-compatible alias for `label`. */
    title?: string;
    /** Pre-formatted display value — the page owns its Intl formatting. */
    value: string | number;
    /** Delta sentence in the chip; null renders no chip rather than inventing a trend. */
    delta?: string | null;
    /** Legacy alias for `delta`. */
    subtitle?: string;
    trend?: Trend;
    /**
     * Daily counts for the current period, oldest first. Rendered as a quiet
     * area line under the value; fewer than two points renders nothing.
     */
    series?: readonly number[];
    /** Zero-based position in the row, driving the CSS entrance stagger. */
    index?: number;
    icon?: React.ReactNode;
    /** Same-origin destination for the metric's filtered list. */
    href?: string;
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
    series,
    index = 0,
    icon,
    href,
}: KpiCardProps) {
    const eyebrow = label ?? title ?? '';
    const chipLabel = delta ?? subtitle ?? null;
    const sparkline = series === undefined ? null : sparklineGeometry(series);

    const card = (
        <div
            className={cn(
                'group relative flex h-full flex-col overflow-hidden rounded-xl border p-4 sm:p-5',
                'border-surface-200 bg-surface-0 transition-[translate,border-color,box-shadow] duration-200 ease-out',
                'hover:-translate-y-0.5 hover:border-aq-accent/40 hover:shadow-(--aq-shadow-lift)',
                'dark:border-surface-800 dark:bg-surface-900 dark:hover:border-aq-accent/35',
                'motion-reduce:transition-none',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate text-[11px] font-semibold tracking-[0.14em] text-surface-600 uppercase dark:text-surface-300">
                        {eyebrow}
                    </p>
                    {/*
                     * 30px is the row's one loud step. The label is 11px
                     * uppercase and the chip 11px, so the number dominates by
                     * scale rather than by decoration.
                     */}
                    <p className="mt-2.5 text-3xl leading-none font-bold tracking-tight text-surface-900 tabular-nums dark:text-surface-0">
                        {value}
                    </p>
                </div>

                {icon && (
                    <span
                        className="grid size-9 shrink-0 place-items-center rounded-lg bg-aq-accent/10 text-aq-accent transition-colors duration-200 group-hover:bg-aq-accent/15 dark:bg-aq-accent/10 motion-reduce:transition-none"
                        aria-hidden="true"
                    >
                        {icon}
                    </span>
                )}
            </div>

            {sparkline !== null && series !== undefined && (
                /*
                 * The line is the accent token, the fill a translucent wash of
                 * the same colour, so the tile stays a metric, not a chart.
                 * The accessible name summarises the shape; the numbers are
                 * already in the value and the chip.
                 */
                <svg
                    role="img"
                    aria-label={`Daily trend over ${series.length} days, peak ${Math.max(...series)}`}
                    viewBox="0 0 120 32"
                    preserveAspectRatio="none"
                    className="mt-3 h-8 w-full text-aq-accent"
                >
                    <path d={sparkline.area} fill="currentColor" opacity={0.12} />
                    <path
                        d={sparkline.line}
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={1.5}
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        vectorEffect="non-scaling-stroke"
                    />
                </svg>
            )}

            <div className="mt-auto flex items-center justify-between gap-2 pt-3">
                {chipLabel !== null ? (
                    <span className="inline-flex min-w-0 items-center gap-1 rounded-full border border-surface-200/80 bg-surface-50 px-2 py-0.5 text-[11px] font-medium text-surface-600 dark:border-surface-700/80 dark:bg-surface-800/60 dark:text-surface-200">
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth={2}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            className={cn('size-3 shrink-0', TREND_TONE[trend])}
                        >
                            {TREND_PATHS[trend].map((path) => (
                                <path key={path} d={path} />
                            ))}
                        </svg>
                        <span className="truncate">{chipLabel}</span>
                    </span>
                ) : (
                    <span />
                )}

                {href !== undefined && (
                    <ArrowUpRight
                        className="size-3.5 shrink-0 text-surface-400 transition-colors duration-150 group-hover:text-aq-accent dark:text-surface-500 dark:group-hover:text-aq-accent motion-reduce:transition-none"
                        aria-hidden="true"
                    />
                )}
            </div>
        </div>
    );

    return (
        <div className="aq-rise h-full" style={{ animationDelay: staggerDelay(index) }}>
            {href !== undefined ? (
                <Link
                    href={href}
                    className="block h-full rounded-xl transition-shadow duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none"
                >
                    {card}
                </Link>
            ) : (
                card
            )}
        </div>
    );
}
