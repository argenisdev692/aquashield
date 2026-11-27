/**
 * The one colour-mode store for the app.
 * Reads/writes `app:theme` in localStorage and toggles `.dark` on <html>.
 */
import { useEffect, useState, useSyncExternalStore } from 'react';

const STORAGE_KEY = 'app:theme';
const NAV_COLLAPSED_STORAGE_KEY = 'aq:nav:collapsed';

/*
 * Storage is a convenience, never a requirement. Safari private mode, ITP,
 * embedded webviews and a full quota can all throw on read or write; an
 * uncaught throw in a mount effect replaces the whole shell with the error
 * boundary, and at module scope it prevents the app from booting at all.
 */
const readStorage = (key: string): string | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        return window.localStorage.getItem(key);
    } catch {
        return null;
    }
};

const writeStorage = (key: string, value: string): void => {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(key, value);
    } catch {
        // Persistence is optional; the in-memory store still works this session.
    }
};

type ThemeMode = 'light' | 'dark';

/**
 * Dark-first is the project policy: no stored preference means dark, matching
 * the first-paint script in `resources/views/app.blade.php`.
 */
const getInitialMode = (): ThemeMode => {
    if (typeof window === 'undefined') {
        return 'dark';
    }

    const stored = readStorage(STORAGE_KEY);

    return stored === 'light' || stored === 'dark' ? stored : 'dark';
};

let currentMode: ThemeMode = getInitialMode();
const listeners = new Set<() => void>();

const applyTheme = (mode: ThemeMode): void => {
    const root = document.documentElement;

    root.classList.toggle('dark', mode === 'dark');
    root.style.colorScheme = mode;

    /*
     * Only write when the persisted value actually differs. `useTheme` runs
     * this on every AppLayout mount, and an unconditional write clobbered a
     * newer choice made in another tab.
     */
    if (readStorage(STORAGE_KEY) !== mode) {
        writeStorage(STORAGE_KEY, mode);
    }

    const token = mode === 'dark' ? '--color-aq-void' : '--p-surface-50';
    const chrome = getComputedStyle(root).getPropertyValue(token).trim();

    if (chrome !== '') {
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', chrome);
    }
};

let storageBound = false;

/**
 * One cross-tab listener for the app. Storage events do not fire in the tab
 * that wrote the value, so this cannot loop with `applyTheme`.
 */
const bindStorage = (): void => {
    if (storageBound || typeof window === 'undefined') {
        return;
    }

    storageBound = true;

    window.addEventListener('storage', (event) => {
        if (event.key !== STORAGE_KEY || event.newValue === null) {
            return;
        }

        if (event.newValue !== 'light' && event.newValue !== 'dark') {
            return;
        }

        if (event.newValue === currentMode) {
            return;
        }

        currentMode = event.newValue;
        applyTheme(currentMode);
        listeners.forEach((listener) => listener());
    });
};

const subscribe = (listener: () => void): (() => void) => {
    bindStorage();
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

    return readStorage(NAV_COLLAPSED_STORAGE_KEY) === 'true';
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
        writeStorage(NAV_COLLAPSED_STORAGE_KEY, String(collapsed));
    }, [collapsed]);

    // Keep the rail in sync when another tab collapses or expands it.
    useEffect(() => {
        const onStorage = (event: StorageEvent): void => {
            if (event.key !== NAV_COLLAPSED_STORAGE_KEY) {
                return;
            }

            setCollapsed(event.newValue === 'true');
        };

        window.addEventListener('storage', onStorage);

        return () => window.removeEventListener('storage', onStorage);
    }, []);

    const setCollapsedState = (value: boolean): void => setCollapsed(value);
    const toggleCollapsed = (): void => setCollapsed((prev) => !prev);

    return { collapsed, setCollapsed: setCollapsedState, toggleCollapsed, isDesktop };
};

/**
 * Collapsible navigation sections — Workspace / Organization / System.
 *
 * Folded by default. A section opens while it owns the current route, and it
 * stays open only when the user leaves it open: this module store persists
 * those explicit choices (`true` open, `false` closed) under `aq:nav:sections`.
 * A section with no entry falls back to the active-route rule in `SidebarNav`,
 * so a new group ships folded and never looks pre-expanded.
 *
 * A module store rather than `useState` for the same reason the theme is one:
 * the desktop rail and the mobile drawer mount the same list, and two private
 * copies would disagree the moment the drawer opens after a rail toggle.
 */
const NAV_SECTIONS_STORAGE_KEY = 'aq:nav:sections';

export type SectionOverrides = Readonly<Record<string, boolean>>;

const NO_SECTION_OVERRIDES: SectionOverrides = {};

const readSectionOverrides = (): SectionOverrides => {
    if (typeof window === 'undefined') {
        return NO_SECTION_OVERRIDES;
    }

    try {
        const stored = localStorage.getItem(NAV_SECTIONS_STORAGE_KEY);

        if (stored === null) {
            return NO_SECTION_OVERRIDES;
        }

        const parsed: unknown = JSON.parse(stored);

        if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
            return NO_SECTION_OVERRIDES;
        }

        const overrides: Record<string, boolean> = {};

        for (const [label, value] of Object.entries(parsed)) {
            if (typeof value === 'boolean') {
                overrides[label] = value;
            }
        }

        return overrides;
    } catch {
        // A corrupt value or a storage-denied context must not blank the rail.
        return NO_SECTION_OVERRIDES;
    }
};

let sectionOverrides: SectionOverrides = readSectionOverrides();
const sectionListeners = new Set<() => void>();

const subscribeSections = (listener: () => void): (() => void) => {
    sectionListeners.add(listener);

    return () => {
        sectionListeners.delete(listener);
    };
};

const getSectionsSnapshot = (): SectionOverrides => sectionOverrides;
const getSectionsServerSnapshot = (): SectionOverrides => NO_SECTION_OVERRIDES;

const commitSections = (next: SectionOverrides): void => {
    sectionOverrides = next;
    writeStorage(NAV_SECTIONS_STORAGE_KEY, JSON.stringify(next));
    sectionListeners.forEach((listener) => listener());
};

/** Records the user's choice; the active-route default applies when absent. */
const setSectionOpen = (label: string, open: boolean): void => {
    if (sectionOverrides[label] === open) {
        return;
    }

    commitSections({ ...sectionOverrides, [label]: open });
};

export interface SidebarSectionsState {
    /** Explicit open/closed choices; a missing label uses the route default. */
    sectionOverrides: SectionOverrides;
    setSectionOpen: (label: string, open: boolean) => void;
}

export const useSidebarSections = (): SidebarSectionsState => {
    const sections = useSyncExternalStore(
        subscribeSections,
        getSectionsSnapshot,
        getSectionsServerSnapshot,
    );

    return { sectionOverrides: sections, setSectionOpen };
};
