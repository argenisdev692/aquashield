/**
 * WelcomeWhy — floating "Today at AquaShield" bar for welcome screen.
 * React port of the Vue WelcomeWhy: whileInView reveal on the one glass panel
 * of the page, with the desynchronised levitate loop on the signal chips.
 */
import { motion } from 'framer-motion';
import { useReveal } from '@/lib/motion';

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

export default function WelcomeWhy() {
    const reveal = useReveal();

    return (
        <section aria-labelledby="welcome-why-heading" className="relative bg-aq-void">
            <div className="mx-auto w-full max-w-6xl px-6 py-8 sm:px-8 sm:py-10 lg:px-12">
                <motion.div
                    initial="hidden"
                    whileInView="show"
                    viewport={{ once: true, margin: '-80px' }}
                    variants={reveal}
                    className="aq-glass flex flex-wrap items-center gap-x-8 gap-y-5 rounded-2xl px-6 py-5"
                >
                    <h2
                        id="welcome-why-heading"
                        className="text-xs font-semibold tracking-[0.18em] text-aq-ink-muted uppercase"
                    >
                        Today at AquaShield
                    </h2>

                    <ul className="flex flex-wrap items-center gap-3">
                        {TODAY.map((signal, signalIndex) => (
                            <li
                                key={signal.label}
                                className="flex items-center gap-2.5 rounded-xl border border-aq-line bg-aq-elevated/60 px-4 py-3 animate-[aq-levitate_6s_ease-in-out_infinite] motion-reduce:animate-none"
                                style={{ animationDelay: `${signalIndex * -2}s` }}
                            >
                                <svg
                                    aria-hidden="true"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth={1.6}
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    className="size-4 shrink-0 text-aq-accent"
                                >
                                    <path d={signal.path} />
                                </svg>
                                <span className="text-xs font-semibold text-aq-ink-soft">
                                    {signal.label}
                                </span>
                            </li>
                        ))}
                    </ul>
                </motion.div>
            </div>
        </section>
    );
}
