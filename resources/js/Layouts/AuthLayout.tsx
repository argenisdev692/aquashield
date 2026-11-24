/**
 * Branded auth shell shared by all Fortify screens.
 * React port of the Vue AuthLayout.
 * Always dark, with its own .dark scope.
 */
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import AppFooter from '@/Components/AppFooter';
import CookieConsentBanner from '@/Components/CookieConsentBanner';
import CursorOrb from '@/Components/CursorOrb';

interface AuthLayoutProps {
    title: string;
    subtitle?: string;
    width?: 'sm' | 'md';
    /**
     * Vertical placement. `center` (default) is the original balanced shell
     * for the single-form screens. `start` pins the column to the top and is
     * what the multi-step screens need — sign-in with its two code transports,
     * and the two-factor challenge. The steps differ in height, and with a
     * centred column every step change moved the logo and the card by half the
     * delta (the "everything jumps" report). A fixed top keeps the brand and
     * the card edges still while only the bottom edge moves.
     */
    align?: 'center' | 'start';
    children: React.ReactNode;
}

export default function AuthLayout({
    title,
    subtitle,
    width = 'sm',
    align = 'center',
    children,
}: AuthLayoutProps) {
    const panelWidths = { sm: 'max-w-sm', md: 'max-w-md' } as const;
    const panelWidth = panelWidths[width];

    const shell =
        align === 'start'
            ? 'justify-start gap-10 pt-10 pb-6 sm:pt-14'
            : 'justify-center-safe py-10';

    return (
        <main
            className={cn(
                'dark relative isolate flex min-h-svh w-full flex-col items-center overflow-x-hidden overflow-y-auto bg-aq-void px-6 scheme-dark',
                shell,
            )}
        >
            <div
                aria-hidden="true"
                className="pointer-events-none absolute inset-0 -z-10 overflow-hidden"
            >
                <div className="absolute -top-40 left-1/2 hidden h-120 w-3xl -translate-x-1/2 rounded-full bg-aq-accent/10 blur-[120px] animate-[aq-orb-drift_24s_ease-in-out_infinite] sm:block" />
                <div className="aq-grid-overlay absolute inset-0" />
            </div>

            <div className={cn('w-full', panelWidth)}>
                <div className="mb-6 flex justify-center">
                    <Link
                        href="/"
                        className="group flex items-center rounded-lg transition-opacity duration-200 hover:opacity-85 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-aq-accent"
                        aria-label="AquaShield Restoration USA — home"
                    >
                        <span className="aq-logo-shine transition-transform duration-200 group-hover:-translate-y-0.5 motion-reduce:transform-none motion-reduce:transition-none">
                            {/* Intrinsic size of the asset (684×724). The old
                                172×212 pair described a 0.81 ratio against a
                                0.945 file, so the centred logo changed width
                                once the image decoded — a layout shift on the
                                one screen that must feel static. */}
                            <img
                                src="/img/logo-white.webp"
                                alt=""
                                width="684"
                                height="724"
                                className="h-24 w-auto shrink-0 object-contain"
                            />
                        </span>
                    </Link>
                </div>

                <div className="rounded-2xl border border-aq-line bg-aq-elevated/45 p-6 text-aq-ink-soft shadow-xl sm:p-7">
                    <div className="text-center">
                        <h1 className="text-xl font-semibold text-aq-ink">{title}</h1>
                        {subtitle && <p className="mt-1 text-sm text-aq-ink-muted">{subtitle}</p>}
                    </div>

                    <div className="mt-6">{children}</div>
                </div>
            </div>

            <AppFooter
                social
                className={cn('w-full max-w-6xl', align === 'start' ? 'mt-auto' : 'mt-10')}
            />

            <CookieConsentBanner />
            <CursorOrb />
        </main>
    );
}
