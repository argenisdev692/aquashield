/**
 * WelcomeHero — hero section for welcome screen.
 * React port of the Vue WelcomeHero: kinetic headline, shield mark with the
 * rising tide, live Houston clock, gradient mesh field and the one CTA.
 */
import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { useEntrance, useRise, useLineReveal, useWord } from '@/lib/motion';

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

/* The capability line is what the workspace contains, one sentence with dot
 * separators, not a <dl>: four bare nouns read fine aloud as a sentence, and
 * a definition list would add screen-reader ceremony to chrome. These are
 * claims about the product, verifiable by opening it. */
const CAPABILITIES: readonly string[] = [
    'Live dispatch board',
    'Jobs & work orders',
    'Customers & properties',
    'Invoices & insurance claims',
];

const HEADLINE: readonly string[] = ['Water wins', 'every minute', 'you wait.'];

export default function WelcomeHero() {
    const entrance = useEntrance();
    const rise = useRise();
    const lineReveal = useLineReveal();
    const word = useWord();

    const [now, setNow] = useState(() => new Date());

    /* One reactive instant feeds the greeting and the readout — Houston, not
     * the visitor's device zone, and not a frozen setup-time value. */
    useEffect(() => {
        const interval = window.setInterval(() => {
            setNow(new Date());
        }, 15_000);

        return () => {
            window.clearInterval(interval);
        };
    }, []);

    const houstonTime = houstonClock.format(now);
    const hour = Number(houstonHour.format(now));
    const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';

    return (
        <section
            className="relative isolate flex min-h-[100svh] flex-col overflow-hidden bg-aq-void"
            aria-labelledby="welcome-heading"
        >
            {/* Ambient field: a three-field gradient mesh, under the grid.
                 Radial falloff does what blur(140px) did on the old solid orb,
                 without the per-frame filter bill. */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
            >
                <div className="aq-mesh-orb aq-mesh-aqua -top-44 left-1/2 h-152 w-248 -translate-x-1/2 animate-[aq-orb-drift_26s_ease-in-out_infinite] motion-reduce:animate-none" />
                <div className="aq-mesh-orb aq-mesh-navy top-[16%] -right-44 h-168 w-168 animate-[aq-orb-drift_31s_ease-in-out_-14s_infinite_reverse] motion-reduce:animate-none" />
                <div className="aq-mesh-orb aq-mesh-green -bottom-40 left-[6%] h-120 w-120 animate-[aq-orb-drift_37s_ease-in-out_-21s_infinite] motion-reduce:animate-none" />
                <div className="aq-grid-overlay absolute inset-0" />
            </div>
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-x-0 bottom-0 -z-10 h-48 bg-linear-to-t from-aq-void to-transparent"
            />

            <motion.div
                variants={entrance}
                initial="hidden"
                animate="show"
                className="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center justify-center px-6 pt-28 pb-24 text-center sm:px-8"
            >
                {/* The shield itself, at the one size where the tide reads as
                     water rather than as a loading bar. Decorative: the brand
                     name is in the header and the headline is next. */}
                <motion.div variants={rise} className="mb-6 flex justify-center" aria-hidden="true">
                    <span className="aq-mark aspect-[358/402] h-15 sm:h-16">
                        <span className="aq-mark__water" />
                    </span>
                </motion.div>

                <motion.p
                    variants={rise}
                    className="inline-flex items-center gap-2.5 text-xs font-medium tracking-wide text-aq-ink-muted sm:text-sm"
                >
                    <span className="relative flex size-2">
                        <span
                            aria-hidden="true"
                            className="absolute inline-flex size-full rounded-full bg-aq-success animate-[aq-pulse-ring_2.4s_ease-out_infinite]"
                        />
                        <span
                            aria-hidden="true"
                            className="relative inline-flex size-2 rounded-full bg-aq-success"
                        />
                    </span>
                    <span>
                        {`${greeting} — workspace online ·`}{' '}
                        <span className="whitespace-nowrap">
                            Houston <span className="tabular-nums">{houstonTime}</span>
                        </span>
                    </span>
                </motion.p>

                <motion.h1
                    id="welcome-heading"
                    className="mt-6 text-[clamp(2.5rem,min(9vw,10vh),7rem)] leading-[0.98] font-bold tracking-[-0.04em] text-aq-ink"
                >
                    <span className="sr-only">{HEADLINE.join(' ')}</span>
                    {HEADLINE.map((line, lineIndex) => (
                        <motion.span
                            key={line}
                            aria-hidden="true"
                            className="block"
                            variants={lineReveal}
                            initial="hidden"
                            animate="show"
                        >
                            {line.split(' ').map((token, tokenIndex) => (
                                <motion.span
                                    key={tokenIndex}
                                    className={`${lineIndex === 2 ? 'aq-grad-ink' : ''} mr-[0.24em] inline-block last:mr-0`.trim()}
                                    variants={word}
                                >
                                    {token}
                                </motion.span>
                            ))}
                        </motion.span>
                    ))}
                </motion.h1>

                <motion.p
                    variants={rise}
                    className="mt-6 max-w-xl text-base leading-relaxed text-pretty text-aq-ink-soft sm:text-lg"
                >
                    The internal workspace for AquaShield operations — dispatch, jobs, customers,
                    invoicing and insurance claims in one place.
                </motion.p>

                <motion.div variants={rise} className="mt-8 flex justify-center">
                    <Link
                        href="/dashboard"
                        className="group relative inline-flex h-13 items-center justify-center gap-2 overflow-hidden rounded-xl bg-aq-accent px-8 text-sm font-semibold text-aq-void shadow-lg shadow-aq-accent/25 transition-[transform,box-shadow] duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:shadow-aq-accent/35 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent active:translate-y-0 motion-reduce:transform-none motion-reduce:transition-none"
                    >
                        <span
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-y-0 -left-1/3 w-1/3 bg-aq-sheen/45 blur-sm transition-opacity duration-300 group-hover:opacity-100 group-hover:animate-[aq-shine_1.1s_ease-out] group-focus-visible:opacity-100 group-focus-visible:animate-[aq-shine_1.1s_ease-out]"
                        />
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth={1.8}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            className="size-4 shrink-0"
                        >
                            <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-13Z" />
                            <path d="M4 9.5h16M9 9.5V20" />
                        </svg>
                        Open dashboard
                    </Link>
                </motion.div>

                {/* Hidden on phones: the subhead already lists the same surfaces,
                     and a four-item line under a fixed consent bar is the first
                     thing to lose. The separators ride their item so a wrap can
                     never strand a `·` at the end of a line. */}
                <motion.p
                    variants={rise}
                    className="mt-9 hidden flex-wrap items-center justify-center gap-x-3 gap-y-1.5 text-sm text-aq-ink-muted sm:flex"
                >
                    {CAPABILITIES.map((capability, capabilityIndex) => (
                        <span
                            key={capability}
                            className="inline-flex items-center gap-3 whitespace-nowrap"
                        >
                            {capabilityIndex > 0 ? (
                                <span aria-hidden="true" className="text-aq-ink-disabled">
                                    ·
                                </span>
                            ) : null}
                            <span>{capability}</span>
                        </span>
                    ))}
                </motion.p>
            </motion.div>
        </section>
    );
}
