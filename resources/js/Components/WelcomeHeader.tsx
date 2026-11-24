/**
 * WelcomeHeader — fixed entry header for the welcome screen.
 * React port of the Vue WelcomeHeader: transparent over the hero, condensed
 * to glass once the page scrolls, with the brand mark, dispatch line and the
 * guest auth entries.
 */
import { useEffect, useRef, useState } from 'react';
import { Link } from '@inertiajs/react';

interface WelcomeHeaderProps {
    company?: Modules.CompanyData.Data.CompanyBrandingDto;
}

interface HeaderLink {
    readonly href: string;
    readonly label: string;
    readonly inApp: boolean;
}

const MARK = '/img/logo-mark.webp';

export default function WelcomeHeader({ company }: WelcomeHeaderProps) {
    const [scrolled, setScrolled] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);
    const menuButtonRef = useRef<HTMLButtonElement | null>(null);

    useEffect(() => {
        const onScroll = (): void => {
            setScrolled(window.scrollY > 16);
        };

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => {
            window.removeEventListener('scroll', onScroll);
        };
    }, []);

    useEffect(() => {
        if (!menuOpen) {
            return;
        }

        const onDocumentKeydown = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                setMenuOpen(false);
                menuButtonRef.current?.focus();
            }
        };

        document.addEventListener('keydown', onDocumentKeydown);

        return () => {
            document.removeEventListener('keydown', onDocumentKeydown);
        };
    }, [menuOpen]);

    const name = company?.name ?? 'AquaShield Restoration USA';
    const brandParts = name.trim().split(/\s+/);
    const first = brandParts[0] ?? '';
    const rest = brandParts.slice(1).join(' ');

    const phoneHref = company?.phone ? `tel:${company.phone}` : '#';
    const phoneLabel = formatPhone(company?.phone);

    const links: readonly HeaderLink[] = [
        ...(company?.phone
            ? [{ href: `tel:${company.phone}`, label: 'Dispatch line', inApp: false }]
            : []),
        { href: '#paths-heading', label: 'Workspace', inApp: false },
        { href: '/login', label: 'Sign in', inApp: true },
        { href: '/register', label: 'Create account', inApp: true },
    ];

    return (
        <header
            className={`fixed inset-x-0 top-0 z-50 transition-[background-color,border-color,box-shadow,backdrop-filter] duration-300 ease-out motion-reduce:transition-none ${
                scrolled || menuOpen
                    ? 'aq-glass border-b border-aq-line bg-aq-void/75 shadow-lg shadow-aq-void/40 backdrop-blur-md'
                    : 'border-b border-transparent bg-transparent'
            }`}
        >
            <a
                href="#main"
                className="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-10 focus:rounded-lg focus:bg-aq-accent focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-aq-void"
            >
                Skip to content
            </a>

            <div className="mx-auto flex h-18 max-w-6xl items-center justify-between gap-6 px-6 sm:px-8 lg:px-12">
                <a
                    href="/"
                    className="group flex shrink-0 items-center gap-3 rounded-lg transition-opacity duration-200 hover:opacity-85 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-aq-accent"
                    aria-label={`${name} — home`}
                >
                    <span className="aq-logo-shine aq-logo-shine-mark transition-transform duration-200 group-hover:-translate-y-0.5 motion-reduce:transform-none motion-reduce:transition-none">
                        <img
                            src={MARK}
                            alt=""
                            width="358"
                            height="402"
                            className="h-9 w-auto shrink-0 object-contain"
                        />
                    </span>
                    <span className="flex flex-col leading-none">
                        <span className="text-base font-bold tracking-tight text-aq-ink">
                            {first}
                        </span>
                        {rest ? (
                            <span className="mt-0.5 text-[0.625rem] font-medium tracking-[0.16em] text-aq-ink-muted uppercase">
                                {rest}
                            </span>
                        ) : null}
                    </span>
                </a>

                <div className="flex items-center gap-2 sm:gap-3">
                    {company?.phone ? (
                        <a
                            href={phoneHref}
                            className="hidden items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-aq-ink-soft transition-colors duration-200 hover:bg-aq-elevated/50 hover:text-aq-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent sm:inline-flex"
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
                            {phoneLabel}
                        </a>
                    ) : null}

                    <a
                        href="#paths-heading"
                        className="hidden items-center rounded-lg px-3 py-2 text-sm font-medium text-aq-ink-soft transition-colors duration-200 hover:bg-aq-elevated/50 hover:text-aq-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent md:inline-flex"
                    >
                        Workspace
                    </a>

                    <Link
                        href="/login"
                        className="hidden rounded-lg bg-aq-accent px-4 py-2.5 text-sm font-semibold text-aq-void transition-[background-color,box-shadow,transform] duration-200 hover:-translate-y-0.5 hover:bg-aq-accent-soft hover:shadow-lg hover:shadow-aq-accent/30 active:translate-y-0 active:bg-aq-accent-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transform-none motion-reduce:transition-none sm:inline-flex"
                    >
                        Sign in
                    </Link>

                    <button
                        ref={menuButtonRef}
                        type="button"
                        className="inline-flex size-10 items-center justify-center rounded-lg border border-aq-line text-aq-ink transition-colors duration-200 hover:bg-aq-elevated/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent md:hidden"
                        aria-expanded={menuOpen}
                        aria-controls="welcome-mobile-nav"
                        onClick={() => setMenuOpen((open) => !open)}
                    >
                        <span className="sr-only">{menuOpen ? 'Close menu' : 'Open menu'}</span>
                        <svg
                            aria-hidden="true"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth={1.8}
                            strokeLinecap="round"
                            className="size-5"
                        >
                            {menuOpen ? (
                                <path d="M6 6l12 12M18 6L6 18" />
                            ) : (
                                <path d="M4 7h16M4 12h16M4 17h16" />
                            )}
                        </svg>
                    </button>
                </div>
            </div>

            {menuOpen ? (
                <div
                    id="welcome-mobile-nav"
                    className="border-t border-aq-line bg-aq-void/95 backdrop-blur-xl md:hidden"
                >
                    <nav aria-label="Mobile" className="mx-auto max-w-6xl px-6 py-4">
                        <ul className="flex flex-col gap-1">
                            {links.map((link) => (
                                <li key={`${link.href}-${link.label}`}>
                                    {link.inApp ? (
                                        <Link
                                            href={link.href}
                                            className="block rounded-lg px-3 py-2.5 text-sm font-medium text-aq-ink-soft transition-colors duration-200 hover:bg-aq-elevated/50 hover:text-aq-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent"
                                            onClick={() => setMenuOpen(false)}
                                        >
                                            {link.label}
                                        </Link>
                                    ) : (
                                        <a
                                            href={link.href}
                                            className="block rounded-lg px-3 py-2.5 text-sm font-medium text-aq-ink-soft transition-colors duration-200 hover:bg-aq-elevated/50 hover:text-aq-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent"
                                            onClick={() => setMenuOpen(false)}
                                        >
                                            {link.label}
                                        </a>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </nav>
                </div>
            ) : null}
        </header>
    );
}

/**
 * US display formatter for the public header only. The authenticated form kit
 * uses libphonenumber-js (`PhoneField`); this stays dependency-free so the
 * welcome bundle does not pull the phone metadata for one label.
 */
function formatPhone(phone: string | null | undefined): string {
    if (!phone) {
        return '';
    }

    const digits = phone.replace(/\D/g, '');
    const national = digits.length === 11 && digits.startsWith('1') ? digits.slice(1) : digits;

    if (national.length === 10) {
        return `(${national.slice(0, 3)}) ${national.slice(3, 6)}-${national.slice(6)}`;
    }

    return phone;
}
