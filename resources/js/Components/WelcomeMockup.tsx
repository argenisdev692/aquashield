/**
 * WelcomeMockup — product band: the app shown, not described.
 * React port of the Vue WelcomeMockup: whileInView entrance from lib/motion,
 * product-frame settle on the plate and a one-shot specular pass.
 */
import { useEffect, useRef, useState } from 'react';
import { motion, useInView } from 'framer-motion';
import { useEntrance, useRise, useSettle } from '@/lib/motion';

export default function WelcomeMockup() {
    const entrance = useEntrance();
    const rise = useRise();
    const settle = useSettle();

    const plateRef = useRef<HTMLDivElement | null>(null);
    const inView = useInView(plateRef, { once: true, margin: '-80px' });
    const [sheened, setSheened] = useState(false);

    useEffect(() => {
        if (inView) {
            setSheened(true);
        }
    }, [inView]);

    return (
        <section aria-labelledby="mockup-heading" className="relative bg-aq-void">
            {/* Inherit the hero's blueprint grid so the band reads as the same
                 surface continuing downward, not a new page. */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 overflow-hidden"
            >
                <div className="aq-grid-overlay absolute inset-0" />
            </div>

            <motion.div
                initial="hidden"
                whileInView="show"
                viewport={{ once: true, margin: '-80px' }}
                variants={entrance}
                className="relative mx-auto w-full max-w-5xl px-6 pt-10 pb-4 sm:px-8"
            >
                <motion.h2
                    id="mockup-heading"
                    variants={rise}
                    className="text-center text-xs font-semibold tracking-[0.18em] text-aq-ink-muted uppercase"
                >
                    The workspace
                </motion.h2>

                {/* Window plate. `relative` carries the sheen span; rounded on
                     every edge, hairline in the accent ramp, lifted off the
                     ground by an accent-tinted shadow so it reads as a surface
                     above the page rather than a flat image. */}
                <motion.div
                    ref={plateRef}
                    variants={settle}
                    className="relative mt-8 overflow-hidden rounded-2xl border border-aq-line bg-aq-base shadow-2xl shadow-aq-void/60 ring-1 ring-aq-accent/10"
                >
                    {/* Chrome bar: three dots, one live status dot and a title,
                         drawn rather than screenshotted, so the swap stays a
                         single file change. */}
                    <div className="flex items-center gap-3 border-b border-aq-line bg-aq-elevated/40 px-4 py-3">
                        <span className="flex shrink-0 items-center gap-1.5" aria-hidden="true">
                            <span className="size-2.5 rounded-full bg-aq-ink-disabled" />
                            <span className="size-2.5 rounded-full bg-aq-ink-disabled" />
                            <span className="size-2.5 rounded-full bg-aq-ink-disabled" />
                        </span>
                        <span className="flex min-w-0 items-center gap-2">
                            <span aria-hidden="true" className="relative flex size-2 shrink-0">
                                <span className="absolute inline-flex size-full rounded-full bg-aq-success animate-[aq-pulse-ring_2.4s_ease-out_infinite]" />
                                <span className="relative inline-flex size-2 rounded-full bg-aq-success" />
                            </span>
                            <span className="truncate text-xs font-medium text-aq-ink-muted">
                                AquaShield · Operations workspace
                            </span>
                        </span>
                    </div>

                    <img
                        src="/img/mockup.webp"
                        alt="The AquaShield operations workspace: the dispatch board, open work orders, monthly revenue and average response time on the dashboard."
                        width={1911}
                        height={898}
                        loading="lazy"
                        decoding="async"
                        className="block w-full bg-aq-void"
                    />

                    {/* The one-shot specular pass. Same recipe as the hero CTA
                         sheen, damped for a large surface. */}
                    <span
                        aria-hidden="true"
                        className={
                            sheened
                                ? 'pointer-events-none absolute inset-y-0 -left-1/3 w-1/3 bg-aq-sheen/25 blur-md opacity-100 animate-[aq-shine_1.4s_ease-out_1_forwards]'
                                : 'pointer-events-none absolute inset-y-0 -left-1/3 w-1/3 bg-aq-sheen/25 blur-md opacity-0'
                        }
                    />
                </motion.div>
            </motion.div>
        </section>
    );
}
