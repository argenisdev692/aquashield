/**
 * WelcomeBrandMoment — animated brand reveal for welcome screen.
 * React port of the Vue WelcomeBrandMoment: the shield assembles out of water
 * behind an IntersectionObserver-gated, reduced-motion-aware R3F canvas, with
 * the still mark as the poster.
 */
import { lazy, Suspense, useEffect, useRef, useState } from 'react';
import { useReducedMotion } from 'framer-motion';
import { cn } from '@/lib/utils';

/**
 * The R3F scene pulls in `three`, `@react-three/fiber` and `drei` — ~600 kB
 * that only this below-the-fold reveal needs. `lazy()` is what actually moves
 * it out of the Welcome chunk; a bare `<Suspense>` around a static import
 * renders a fallback and still ships the bytes.
 */
const BrandMarkScene = lazy(() => import('@/Components/BrandMarkScene'));

const WORKFLOW_PILLS: readonly string[] = ['Live Dispatch', 'Claim Tracking', 'Instant Invoicing'];

export default function WelcomeBrandMoment() {
    const reduceMotion = useReducedMotion();
    const stageRef = useRef<HTMLDivElement | null>(null);
    const [sceneReady, setSceneReady] = useState(false);
    const [showScene, setShowScene] = useState(false);

    const allowsScene = (): boolean => {
        const nav = navigator as Navigator & {
            deviceMemory?: number;
            connection?: { saveData?: boolean };
        };

        if (nav.connection?.saveData === true) {
            return false;
        }

        return !(typeof nav.deviceMemory === 'number' && nav.deviceMemory < 4);
    };

    useEffect(() => {
        if (reduceMotion || !allowsScene()) {
            return;
        }

        const stage = stageRef.current;

        if (!stage) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const entry = entries[0];

                if (entry) {
                    setShowScene(entry.isIntersecting);
                }
            },
            { rootMargin: '240px 0px' },
        );

        observer.observe(stage);

        return () => {
            observer.disconnect();
        };
    }, [reduceMotion]);

    /* Leaving the band unmounts the renderer, so no GPU work happens offscreen,
       and the poster returns — the same reset the Vue version did in its
       `watch(showScene)`. Without it a re-entry would re-show a dead scene. */
    useEffect(() => {
        if (!showScene) {
            setSceneReady(false);
        }
    }, [showScene]);

    return (
        <section
            aria-labelledby="brand-moment-heading"
            className="relative isolate overflow-hidden bg-aq-void"
        >
            {/* Soft radial ambient field behind the section — fades smoothly with no hard edge. */}
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
            >
                <div className="aq-mesh-orb aq-mesh-aqua top-1/2 left-2/3 h-136 w-136 -translate-x-1/2 -translate-y-1/2 opacity-75" />
            </div>

            <div className="mx-auto grid w-full max-w-6xl grid-cols-1 items-center gap-10 px-6 pt-16 pb-12 sm:px-8 lg:grid-cols-12 lg:gap-12 lg:px-12 lg:py-20">
                <div className="order-2 lg:order-1 lg:col-span-6">
                    <p className="text-xs font-semibold tracking-[0.18em] text-aq-accent uppercase">
                        The mark
                    </p>

                    <h2
                        id="brand-moment-heading"
                        className="mt-4 max-w-md text-3xl font-bold tracking-[-0.02em] text-balance text-aq-ink sm:text-4xl"
                    >
                        One shield. Every job.
                    </h2>

                    <p className="mt-5 max-w-md leading-relaxed text-pretty text-aq-ink-soft">
                        Dispatch, jobs, customers, invoices and claims — one surface. The shield is
                        the reminder that every ticket is someone&apos;s home.
                    </p>

                    <ul
                        aria-label="Core workflow capabilities"
                        className="mt-6 flex flex-wrap items-center gap-2.5"
                    >
                        {WORKFLOW_PILLS.map((pill) => (
                            <li
                                key={pill}
                                className="inline-flex items-center gap-2 rounded-full border border-aq-line bg-aq-elevated/45 px-3.5 py-1.5 text-xs font-medium text-aq-ink-soft backdrop-blur-sm"
                            >
                                <span
                                    aria-hidden="true"
                                    className="size-1.5 rounded-full bg-aq-accent"
                                />
                                <span>{pill}</span>
                            </li>
                        ))}
                    </ul>

                    <div className="mt-7">
                        <a
                            href="#workspace"
                            className="group inline-flex items-center gap-2 text-sm font-semibold text-aq-accent transition-colors duration-200 hover:text-aq-accent-soft focus-visible:rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-aq-accent"
                        >
                            <span>Explore live workspace</span>
                            <svg
                                aria-hidden="true"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth={1.8}
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                className="size-4 transition-transform duration-200 group-hover:translate-y-0.5 motion-reduce:transform-none"
                            >
                                <path d="M12 5v14m-6-6 6 6 6-6" />
                            </svg>
                        </a>
                    </div>
                </div>

                <div ref={stageRef} className="order-1 lg:order-2 lg:col-span-6">
                    <div className="relative mx-auto aspect-[5/4] w-full max-w-[25rem]">
                        {/* Ambient backdrop glow behind the 3D shield canvas for depth */}
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute inset-6 -z-10 rounded-full bg-aq-accent/15 blur-3xl"
                        />

                        {/* The poster is the mark, not a stand-in illustration.
                             It holds the exact box the cloud occupies, so the
                             swap never shifts layout. */}
                        <div
                            className={cn(
                                'absolute inset-0 flex items-center justify-center transition-opacity duration-500 motion-reduce:transition-none',
                                sceneReady ? 'opacity-0' : 'opacity-100',
                            )}
                        >
                            <span
                                aria-hidden="true"
                                className="aq-mark aspect-[358/402] h-44 sm:h-52"
                            >
                                <span className="aq-mark__water" />
                            </span>
                        </div>

                        {showScene ? (
                            <div
                                className={cn(
                                    'absolute inset-0 transition-opacity duration-700 motion-reduce:transition-none',
                                    sceneReady ? 'opacity-100' : 'opacity-0',
                                )}
                                aria-hidden="true"
                            >
                                <Suspense fallback={null}>
                                    <BrandMarkScene onReady={() => setSceneReady(true)} />
                                </Suspense>
                            </div>
                        ) : null}

                        {/* Floating Glassmorphism HUD Chip — Top-right */}
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute top-3 right-2 hidden items-center gap-2 rounded-full border border-aq-line bg-aq-base/75 px-3.5 py-1.5 text-xs font-medium text-aq-ink-soft shadow-lg shadow-aq-void/50 backdrop-blur-md sm:inline-flex"
                        >
                            <span className="relative flex size-2 shrink-0">
                                <span className="absolute inline-flex size-full animate-[aq-pulse-ring_2.4s_ease-out_infinite] rounded-full bg-aq-success" />
                                <span className="relative inline-flex size-2 rounded-full bg-aq-success" />
                            </span>
                            <span className="tabular-nums">Job · 4082 · Active Mitigation</span>
                        </div>

                        {/* Floating Glassmorphism HUD Chip — Bottom-left */}
                        <div
                            aria-hidden="true"
                            className="pointer-events-none absolute bottom-3 left-2 hidden items-center gap-2 rounded-full border border-aq-line bg-aq-base/75 px-3.5 py-1.5 text-xs font-medium text-aq-ink-soft shadow-lg shadow-aq-void/50 backdrop-blur-md sm:inline-flex"
                        >
                            <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth={2.2}
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                className="size-3.5 shrink-0 text-aq-accent"
                            >
                                <path d="M20 6 9 17l-5-5" />
                            </svg>
                            <span>Claim &amp; Invoice Synced</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
