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
