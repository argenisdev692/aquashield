/**
 * WelcomePaths — the workspace surfaces: four entries into the same board.
 * React port of the Vue WelcomePaths: whileInView reveal with per-card stagger.
 */
import { motion } from 'framer-motion';
import { Link } from '@inertiajs/react';
import { useReveal } from '@/lib/motion';

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
        // The schedule lives in the dashboard pipeline section (calendar view).
        href: '/dashboard#pipeline',
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
        // Won work and its month-over-month delta live in the KPI section.
        href: '/dashboard#kpis',
        path: 'M7 3.5h10v17l-2.5-1.5L12 20.5 9.5 19 7 20.5zM9.5 8h5M9.5 11.5h5M9.5 15h3',
    },
];

function SurfaceCard({ surface, index }: { surface: WorkspaceSurface; index: number }) {
    /*
     * `useReveal` subscribes to `useReducedMotion`, so it is a real hook and
     * must run in a component body. Calling it inside the `.map()` below would
     * change the hook order when the surface list changes and it makes React
     * Compiler skip the whole page.
     */
    const variants = useReveal(index);

    return (
        <motion.div
            initial="hidden"
            whileInView="show"
            viewport={{ once: true, margin: '-60px' }}
            variants={variants}
            className="h-full"
        >
            <Link
                href={surface.href}
                className="group flex h-full flex-col rounded-2xl border border-aq-line bg-aq-elevated/40 p-6 transition-[transform,border-color,background-color] duration-200 hover:-translate-y-1 hover:border-aq-line-strong hover:bg-aq-overlay/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transform-none motion-reduce:transition-none"
            >
                <span className="inline-flex size-11 items-center justify-center rounded-xl border border-aq-line bg-aq-base/60 text-aq-accent transition-transform duration-300 group-hover:scale-105 motion-reduce:transform-none">
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={1.6}
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="size-5"
                    >
                        <path d={surface.path} />
                    </svg>
                </span>
                <span className="mt-5 text-base font-bold text-aq-ink">{surface.title}</span>
                <span className="mt-2 flex-1 text-sm leading-relaxed text-aq-ink-soft">
                    {surface.body}
                </span>
                <span className="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-aq-accent">
                    {surface.action}
                    <svg
                        aria-hidden="true"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={1.8}
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="size-4 transition-transform duration-200 group-hover:translate-x-0.5 motion-reduce:transform-none"
                    >
                        <path d="M5 12h14m-6-6 6 6-6 6" />
                    </svg>
                </span>
            </Link>
        </motion.div>
    );
}

export default function WelcomePaths() {
    const headingVariants = useReveal(0);

    return (
        <section id="workspace" aria-labelledby="paths-heading" className="relative bg-aq-void">
            <div
                aria-hidden="true"
                className="mx-auto h-px w-full max-w-6xl bg-linear-to-r from-transparent via-aq-accent/25 to-transparent"
            />

            <div className="mx-auto w-full max-w-6xl px-6 pt-14 pb-16 sm:px-8 lg:px-12 lg:pt-18 lg:pb-20">
                <motion.div
                    initial="hidden"
                    whileInView="show"
                    viewport={{ once: true, margin: '-80px' }}
                    variants={headingVariants}
                    className="max-w-2xl"
                >
                    <p className="text-xs font-semibold tracking-[0.18em] text-aq-accent uppercase">
                        Inside the workspace
                    </p>
                    <h2
                        id="paths-heading"
                        className="mt-4 text-3xl font-bold tracking-[-0.02em] text-balance text-aq-ink sm:text-4xl"
                    >
                        From intake to payout on one board.
                    </h2>
                    <p className="mt-4 max-w-xl leading-relaxed text-aq-ink-soft">
                        The board is the front door. Each surface below opens the same live job —
                        not a copy of it.
                    </p>
                </motion.div>

                <div className="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {SURFACES.map((surface, surfaceIndex) => (
                        <SurfaceCard key={surface.title} surface={surface} index={surfaceIndex} />
                    ))}
                </div>
            </div>
        </section>
    );
}
