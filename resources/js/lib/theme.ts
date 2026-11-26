/**
 * The one colour-mode store for the app.
 * Reads/writes `app:theme` in localStorage and toggles `.dark` on <html>.
 */
import { useEffect, useState, useSyncExternalStore } from 'react';

const STORAGE_KEY = 'app:theme';
const NAV_COLLAPSED_STORAGE_KEY = 'aq:nav:collapsed';

type ThemeMode = 'light' | 'dark';

/**
 * Dark-first is the project policy: no stored preference means dark, matching
 * the first-paint script in `resources/views/app.blade.php`.
 */
const getInitialMode = (): ThemeMode => {
    if (typeof window === 'undefined') {
        return 'dark';
    }

    const stored = localStorage.getItem(STORAGE_KEY);

    return stored === 'light' || stored === 'dark' ? stored : 'dark';
};

let currentMode: ThemeMode = getInitialMode();
const listeners = new Set<() => void>();

const applyTheme = (mode: ThemeMode): void => {
    const root = document.documentElement;

    root.classList.toggle('dark', mode === 'dark');
    root.style.colorScheme = mode;
    localStorage.setItem(STORAGE_KEY, mode);

    const token = mode === 'dark' ? '--color-aq-void' : '--p-surface-50';
    const chrome = getComputedStyle(root).getPropertyValue(token).trim();

    if (chrome !== '') {
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', chrome);
    }
};

const subscribe = (listener: () => void): (() => void) => {
    listeners.add(listener);

    return () => {
        listeners.delete(listener);
    };
};

const getSnapshot = (): ThemeMode => currentMode;
const getServerSnapshot = (): ThemeMode => 'dark';

/*
 * The writer lives at module scope, not inside `useTheme`.
 *
 * React Compiler treats a closure that reassigns a module variable as an
 * impure hook body and skips the component. Keeping the store mutation here
 * also gives `toggleTheme` a stable identity, so an effect or a memo can
 * depend on it without re-running on every render.
 */
const setMode = (next: ThemeMode): void => {
    currentMode = next;
    applyTheme(next);
    listeners.forEach((listener) => listener());
};

const toggleTheme = (): void => {
    setMode(currentMode === 'dark' ? 'light' : 'dark');
};

/**
 * The one colour-mode store for the app.
 *
 * A module-level snapshot + `useSyncExternalStore` means every consumer
 * (`AppLayout`, `ThemeToggle`, the command palette) reads and writes the same
 * value. A hook with its own `useState` would give each component a private
 * copy and let the header and the palette disagree after one toggle.
 */
export const useTheme = () => {
    const mode = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot);

    useEffect(() => {
        // The first-paint script already applied the class; sync the meta tag
        // and persist the resolved mode once the component tree is mounted.
        applyTheme(currentMode);
    }, []);

    return { isDark: mode === 'dark', toggleTheme };
};

/**
 * Sidebar chrome state — the collapsed rail flag. Persisted under the same key
 * the previous shell used (`aq:nav:collapsed`) so an existing preference
 * carries over, and mirrored nowhere else: `lib/theme.ts` is the single home
 * for sidebar persistence, per the frontend architecture layer table.
 */
const getInitialCollapsed = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return localStorage.getItem(NAV_COLLAPSED_STORAGE_KEY) === 'true';
};

const useMediaQuery = (query: string): boolean => {
    const [matches, setMatches] = useState(false);

    useEffect(() => {
        const mediaQueryList = window.matchMedia(query);
        const onChange = (event: MediaQueryListEvent) => setMatches(event.matches);

        setMatches(mediaQueryList.matches);
        mediaQueryList.addEventListener('change', onChange);

        return () => mediaQueryList.removeEventListener('change', onChange);
    }, [query]);

    return matches;
};

export interface SidebarState {
    collapsed: boolean;
    setCollapsed: (collapsed: boolean) => void;
    toggleCollapsed: () => void;
    /** Whether the viewport is at the desktop breakpoint (the rail renders). */
    isDesktop: boolean;
}

export const useSidebar = (): SidebarState => {
    const [collapsed, setCollapsed] = useState<boolean>(getInitialCollapsed);
    const isDesktop = useMediaQuery('(min-width: 64rem)');

    useEffect(() => {
        localStorage.setItem(NAV_COLLAPSED_STORAGE_KEY, String(collapsed));
    }, [collapsed]);

    const setCollapsedState = (value: boolean): void => setCollapsed(value);
    const toggleCollapsed = (): void => setCollapsed((prev) => !prev);

    return { collapsed, setCollapsed: setCollapsedState, toggleCollapsed, isDesktop };
};

/**
 * Collapsible navigation sections — which of Workspace / Organization / System
 * the user has folded away.
 *
 * A module store rather than `useState` for the same reason the theme is one:
 * the desktop rail and the mobile drawer mount the same list, and two private
 * copies would disagree the moment the drawer opens after a rail toggle. The
 * value is persisted as the list of *collapsed* labels under
 * `aq:nav:collapsed-sections`, so a section added later starts expanded, which
 * is the safe default — a hidden destination is a support ticket.
 */
const NAV_SECTIONS_STORAGE_KEY = 'aq:nav:collapsed-sections';

export type CollapsedSections = Readonly<Record<string, boolean>>;

const NO_COLLAPSED_SECTIONS: CollapsedSections = {};

const readCollapsedSections = (): CollapsedSections => {
    if (typeof window === 'undefined') {
        return NO_COLLAPSED_SECTIONS;
    }

    try {
        const stored = localStorage.getItem(NAV_SECTIONS_STORAGE_KEY);

        if (stored === null) {
            return NO_COLLAPSED_SECTIONS;
        }

        const parsed: unknown = JSON.parse(stored);

        if (!Array.isArray(parsed)) {
            return NO_COLLAPSED_SECTIONS;
        }

        return Object.fromEntries(
            parsed
                .filter((label): label is string => typeof label === 'string')
                .map((label) => [label, true]),
        );
    } catch {
        // A corrupt value or a storage-denied context must not blank the rail.
        return NO_COLLAPSED_SECTIONS;
    }
};

let collapsedSections: CollapsedSections = readCollapsedSections();
const sectionListeners = new Set<() => void>();

const subscribeSections = (listener: () => void): (() => void) => {
    sectionListeners.add(listener);

    return () => {
        sectionListeners.delete(listener);
    };
};

const getSectionsSnapshot = (): CollapsedSections => collapsedSections;
const getSectionsServerSnapshot = (): CollapsedSections => NO_COLLAPSED_SECTIONS;

const commitSections = (next: CollapsedSections): void => {
    collapsedSections = next;
    localStorage.setItem(
        NAV_SECTIONS_STORAGE_KEY,
        JSON.stringify(Object.keys(next).filter((label) => next[label] === true)),
    );
    sectionListeners.forEach((listener) => listener());
};

const toggleSection = (label: string): void => {
    commitSections({ ...collapsedSections, [label]: collapsedSections[label] !== true });
};

/** No-op when already open, so an effect may call it on every navigation. */
const expandSection = (label: string): void => {
    if (collapsedSections[label] !== true) {
        return;
    }

    const next: Record<string, boolean> = {};

    for (const [key, value] of Object.entries(collapsedSections)) {
        if (key !== label) {
            next[key] = value;
        }
    }

    commitSections(next);
};

export interface SidebarSectionsState {
    collapsedSections: CollapsedSections;
    toggleSection: (label: string) => void;
    expandSection: (label: string) => void;
}

export const useSidebarSections = (): SidebarSectionsState => {
    const sections = useSyncExternalStore(
        subscribeSections,
        getSectionsSnapshot,
        getSectionsServerSnapshot,
    );

    return { collapsedSections: sections, toggleSection, expandSection };
};
