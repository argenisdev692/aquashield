/**
 * App shell — header, primary rail, mobile drawer, palette.
 * React port of the Vue AppLayout.
 *
 * The header carries the global, page-level controls (search, inbox, theme).
 * The identity lives in the rail footer, so it is never shown twice.
 *
 * The chrome is borderless on purpose: the rail is one surface step above the
 * canvas, the header is a translucent sheet and the footer is whitespace. A
 * 1px line around each region draws three boxes over a page that already has
 * cards, and the frame ends up competing with the content. Separation is
 * carried by surface lightness, the same rule the cards follow.
 */
import { useEffect, useId, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Sun, Moon, Menu, Search, LogOut } from 'lucide-react';
import SessionIdleWarning from '@/Components/SessionIdleWarning';
import AppSidebar from '@/Components/AppSidebar';
import AppFooter from '@/Components/AppFooter';
import BrandLockup from '@/Components/BrandLockup';
import CookieConsentBanner from '@/Components/CookieConsentBanner';
import CursorOrb from '@/Components/CursorOrb';
import FlashBanner from '@/Components/FlashBanner';
import HeaderInbox from '@/Components/HeaderInbox';
import CommandPalette from '@/Components/CommandPalette';
import ThemeToggle from '@/Components/ThemeToggle';
import {
    DASHBOARD_PATH,
    DASHBOARD_SECTIONS,
    SETTINGS_NAV,
    visiblePrimaryNav,
    jumpToSection,
    sectionId,
} from '@/lib/navigation';
import { useLogout } from '@/lib/auth';
import type { Command } from '@/lib/commands';
import { useTheme } from '@/lib/theme';

interface AppLayoutProps {
    title: string;
    children: React.ReactNode;
}

interface IdleSessionConfig {
    warnAfterSeconds: number;
    graceSeconds: number;
    keepAliveEndpoint: string;
    logoutEndpoint: string;
}

export default function AppLayout({ title, children }: AppLayoutProps) {
    const page = usePage<{
        authPermissions?: string[];
        authIdle?: IdleSessionConfig | null;
        canManageStaff?: boolean;
        canManageAccess?: boolean;
    }>();
    const [navOpen, setNavOpen] = useState(false);
    const [paletteOpen, setPaletteOpen] = useState(false);
    const navPanelId = useId();

    const currentPath = page.url.split('?')[0] ?? '/';

    const { isDark, toggleTheme } = useTheme();
    const logout = useLogout();

    /**
     * The palette's command list — the same destinations the rail and the
     * drawer read from, so a route can never exist in one surface and not the
     * other. Gated on the dashboard path for the section jumps, exactly like
     * the previous shell.
     */
    const commands: Command[] = (() => {
        const destinations: Command[] = visiblePrimaryNav(page.props.authPermissions ?? [], {
            manageStaff: page.props.canManageStaff === true,
            manageAccess: page.props.canManageAccess === true,
        }).map((item) => ({
            id: `nav-${item.href.slice(1)}`,
            group: 'Go to',
            label: item.label,
            icon: item.icon,
            keywords: item.keywords,
            run: () => router.visit(item.href),
        }));

        destinations.push({
            id: `nav-${SETTINGS_NAV.href.slice(1)}`,
            group: 'Go to',
            label: SETTINGS_NAV.label,
            icon: SETTINGS_NAV.icon,
            keywords: SETTINGS_NAV.keywords,
            run: () => router.visit(SETTINGS_NAV.href),
        });

        const sections: Command[] =
            currentPath === DASHBOARD_PATH
                ? DASHBOARD_SECTIONS.map((item) => ({
                      id: `jump-${sectionId(item.href)}`,
                      group: 'On this page',
                      label: item.label,
                      icon: item.icon,
                      keywords: item.keywords,
                      run: () => jumpToSection(sectionId(item.href)),
                  }))
                : [];

        return [
            ...destinations,
            ...sections,
            {
                id: 'toggle-theme',
                group: 'Preferences',
                label: isDark ? 'Switch to light mode' : 'Switch to dark mode',
                icon: isDark ? Sun : Moon,
                keywords: 'theme appearance colour color dark light',
                run: toggleTheme,
            },
            {
                id: 'sign-out',
                group: 'Session',
                label: 'Sign out',
                icon: LogOut,
                keywords: 'logout leave',
                run: logout,
            },
        ];
    })();

    useEffect(() => {
        const onKeydown = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setPaletteOpen((prev) => !prev);
            }
        };
        window.addEventListener('keydown', onKeydown);
        return () => window.removeEventListener('keydown', onKeydown);
    }, []);

    return (
        <div className="mx-auto flex min-h-dvh w-full max-w-384 overflow-x-clip bg-surface-50 text-surface-700 dark:bg-surface-950 dark:text-surface-0">
            <a
                href="#main"
                className="sr-only rounded-lg bg-surface-0 px-4 py-2 text-sm font-medium text-surface-900 focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 dark:bg-surface-900 dark:text-surface-0"
            >
                Skip to content
            </a>

            <AppSidebar
                open={navOpen}
                onOpenChange={setNavOpen}
                currentPath={currentPath}
                panelId={navPanelId}
            />

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="sticky top-0 z-40 bg-surface-0/70 backdrop-blur-xl dark:bg-surface-950/65">
                    <div className="mx-auto flex h-16 w-full max-w-7xl items-center gap-2 px-4 sm:gap-3 sm:px-6 lg:px-8">
                        <button
                            type="button"
                            className="-ms-1 grid size-10 shrink-0 place-items-center rounded-lg text-surface-700 transition-colors duration-150 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none lg:hidden dark:text-surface-100 dark:hover:bg-surface-800"
                            aria-label="Open navigation"
                            aria-controls={navPanelId}
                            aria-expanded={navOpen}
                            onClick={() => setNavOpen(true)}
                        >
                            <Menu className="size-4" aria-hidden="true" />
                        </button>

                        {/*
                         * The rail owns the brand on desktop. Below `lg` the rail
                         * is a drawer, so the same lockup moves into the header —
                         * one component, so the theme-correct mark cannot drift
                         * between the two surfaces.
                         */}
                        <BrandLockup className="min-w-0 lg:hidden" nameClassName="hidden sm:flex" />

                        {/*
                         * Search leads the bar, directly after the rail: it is the
                         * primary way into the app, and it now owns the space that
                         * used to sit empty in the middle of the header. The old
                         * "CRM Workspace" label stood between the rail brand and
                         * the field, saying the product name twice in 40px and
                         * pushing search into the icon cluster on the right.
                         *
                         * Width is fluid up to 28rem: wide enough for a real query,
                         * narrow enough that the action cluster stays anchored to
                         * the end of the row at every desktop width.
                         */}
                        <button
                            type="button"
                            className="hidden h-10 w-full max-w-md min-w-0 flex-1 items-center gap-2.5 rounded-lg border border-content-border bg-surface-0 px-3 text-left text-sm text-surface-500 transition-colors duration-150 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus md:flex dark:bg-surface-800/60 dark:text-surface-300 dark:hover:bg-surface-800 motion-reduce:transition-none"
                            aria-label="Open command palette"
                            aria-keyshortcuts="Control+K Meta+K"
                            onClick={() => setPaletteOpen(true)}
                        >
                            <Search className="size-4 shrink-0" aria-hidden="true" />
                            <span className="truncate">Search…</span>
                            <kbd className="ml-auto inline-flex h-5 shrink-0 items-center rounded border border-surface-200 bg-surface-50 px-1.5 font-sans text-[10px] font-medium text-surface-600 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300">
                                Ctrl K
                            </kbd>
                        </button>

                        <div className="ms-auto flex shrink-0 items-center gap-1.5 sm:gap-2">
                            <button
                                type="button"
                                className="grid size-9 place-items-center rounded-lg text-surface-700 transition-colors duration-150 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus md:hidden dark:text-surface-100 dark:hover:bg-surface-800 motion-reduce:transition-none"
                                aria-label="Open command palette"
                                aria-keyshortcuts="Control+K Meta+K"
                                onClick={() => setPaletteOpen(true)}
                            >
                                <Search className="size-4" aria-hidden="true" />
                            </button>

                            <HeaderInbox kind="notifications" />
                            <HeaderInbox kind="messages" />

                            <ThemeToggle />
                        </div>
                    </div>
                </header>

                {/* One content width at every rail state. The shell caps at
                    1536px, but the rail is 256px expanded and 72px collapsed,
                    so an uncapped column grew from 1216px to 1400px when the
                    rail was collapsed and every CRUD table stretched with it.
                    `max-w-7xl` (1280px, 1216px inside the gutters) is the same
                    width the footer already uses, and it is the band the
                    dashboard grid and the KPI tiles were measured at.

                    It also owns the only horizontal overflow decision: the
                    shell clips stray decorative overflow (`overflow-x-clip`),
                    `min-w-0` lets this column shrink below its content, and a
                    data table scrolls inside its own card scroller — never the
                    browser viewport. Removing any of the three brings back the
                    page-wide scrollbar a wide TanStack table used to create. */}
                <main
                    id="main"
                    className="mx-auto w-full max-w-7xl min-w-0 flex-1 px-4 py-8 sm:px-6 lg:px-8"
                >
                    <h1 className="text-2xl font-bold tracking-tight text-surface-900 dark:text-surface-0">
                        {title}
                    </h1>
                    <FlashBanner className="mt-4" />
                    <div className="mt-6 min-w-0">{children}</div>
                </main>

                <AppFooter social />
            </div>

            {page.props.authIdle ? (
                <SessionIdleWarning
                    warnAfterSeconds={page.props.authIdle.warnAfterSeconds}
                    graceSeconds={page.props.authIdle.graceSeconds}
                    keepAliveEndpoint={page.props.authIdle.keepAliveEndpoint}
                    logoutEndpoint={page.props.authIdle.logoutEndpoint}
                />
            ) : null}

            <CommandPalette open={paletteOpen} onOpenChange={setPaletteOpen} commands={commands} />

            <CookieConsentBanner />
            <CursorOrb />
        </div>
    );
}
