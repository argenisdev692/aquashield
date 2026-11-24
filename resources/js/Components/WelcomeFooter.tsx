/**
 * WelcomeFooter — page-level footer for the entry template.
 * React port of the Vue WelcomeFooter: brand block, dispatch chip, legal row
 * and copyright. Legal pages live on the official site and are linked, never
 * copied.
 */
import SocialLinks from '@/Components/SocialLinks';

interface FooterLink {
    readonly label: string;
    readonly href?: string;
}

const LEGAL: readonly FooterLink[] = [
    { label: 'Privacy Policy', href: 'https://aquashieldrestorationusa.com/privacy-policy' },
    {
        label: 'Terms & Conditions',
        href: 'https://aquashieldrestorationusa.com/terms-and-conditions',
    },
    { label: 'Cookie Policy' },
];

const year = new Date().getFullYear();

export default function WelcomeFooter() {
    return (
        <footer className="relative bg-aq-void">
            <div
                aria-hidden="true"
                className="absolute inset-x-0 top-0 h-px bg-linear-to-r from-transparent via-aq-accent/45 to-transparent"
            />
            <div className="mx-auto w-full max-w-6xl px-6 py-12 sm:px-8 lg:px-12">
                <div className="flex flex-col gap-10 lg:flex-row lg:items-start lg:justify-between">
                    <div className="max-w-sm">
                        <p className="text-sm font-bold text-aq-ink">
                            AquaShield
                            <span className="ml-2 text-xs font-medium text-aq-ink-muted">
                                Restoration USA · Houston, Texas
                            </span>
                        </p>
                        <p className="mt-3 text-sm leading-relaxed text-aq-ink-muted">
                            Internal operations workspace for AquaShield Restoration USA.
                        </p>
                        <p className="mt-5 inline-flex items-center gap-2.5 rounded-full border border-aq-line bg-aq-accent/6 px-3.5 py-1.5 text-xs font-medium text-aq-ink-soft">
                            <span aria-hidden="true" className="relative flex size-2">
                                <span className="absolute inline-flex size-full rounded-full bg-aq-success animate-[aq-pulse-ring_2.4s_ease-out_infinite]" />
                                <span className="relative inline-flex size-2 rounded-full bg-aq-success" />
                            </span>
                            24/7 dispatch · on site within 60 minutes
                        </p>
                        <a
                            href="tel:+17135876423"
                            aria-label="Dispatch line: (713) 587-6423"
                            className="mt-3 inline-flex min-h-11 items-center gap-2.5 rounded-xl border border-aq-line bg-aq-accent/6 px-4 text-sm font-semibold text-aq-ink transition-all duration-200 hover:-translate-y-0.5 hover:border-aq-line-strong hover:bg-aq-accent/10 hover:shadow-(--aq-glow-accent) focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent active:translate-y-0 motion-reduce:transform-none"
                        >
                            <svg
                                aria-hidden="true"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth={1.6}
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                className="size-4 text-aq-accent"
                            >
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z" />
                            </svg>
                            (713) 587-6423
                        </a>
                    </div>

                    {/* Deliberately flush-left inside its column: heading and rows
                         share one edge, so every row (icon or no icon) lines up. */}
                    <nav aria-label="Legal" className="flex flex-col">
                        <h3 className="text-xs font-semibold tracking-[0.18em] text-aq-ink-muted uppercase">
                            Legal
                        </h3>
                        <ul className="mt-1.5 flex flex-col gap-1">
                            {LEGAL.map((link) => (
                                <li key={link.label}>
                                    {link.href ? (
                                        <a
                                            href={link.href}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            aria-label={`${link.label} (opens in a new tab)`}
                                            className="group inline-flex items-center gap-1.5 rounded-lg py-3 text-sm font-medium text-aq-ink-soft transition-colors duration-200 hover:text-aq-ink hover:underline hover:underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent"
                                        >
                                            {link.label}
                                            <svg
                                                aria-hidden="true"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                strokeWidth={1.6}
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                className="size-3.5 text-aq-ink-muted transition-[color,transform] duration-200 group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-aq-accent motion-reduce:transform-none"
                                            >
                                                <path d="M7 17 17 7M7 7h10v10" />
                                            </svg>
                                        </a>
                                    ) : (
                                        <span
                                            title={`${link.label} — page coming soon`}
                                            className="inline-flex cursor-default items-center py-3 text-sm font-medium text-aq-ink-muted"
                                        >
                                            {link.label}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </nav>
                </div>

                <div className="mt-10 flex flex-col items-center justify-between gap-5 border-t border-aq-line pt-6 text-center sm:flex-row sm:text-left">
                    <p className="text-xs text-aq-ink-muted">
                        © {year} AquaShield Restoration USA · Licensed &amp; insured · Direct
                        insurance billing
                    </p>

                    {/*
                     * The brand's public profiles. They exist, so they are links
                     * here too — the same component the app shell footer uses,
                     * so a profile can never be live in one surface and inert
                     * in the other.
                     */}
                    <SocialLinks variant="marketing" />
                </div>
            </div>
        </footer>
    );
}
