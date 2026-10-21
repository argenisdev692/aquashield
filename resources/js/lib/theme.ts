import { computed, watch } from 'vue';
import { useColorMode } from '@vueuse/core';

/**
 * The one colour-mode store for the app.
 *
 * `resources/views/app.blade.php` already owns the first paint (`.dark` on
 * <html> before the stylesheet) and names `app:theme` as the VueUse key, so
 * this module is the other half of that contract — not a second source of
 * truth. A module-level singleton matters: the header toggle and the command
 * palette each call `useTheme()`, and two independent `useColorMode` refs
 * would drift apart in the same tab (VueUse only syncs across tabs).
 *
 * `disableTransition` is off on purpose: VueUse implements it by injecting an
 * inline <style> element, which a nonce-based CSP blocks. The palette is
 * mounted at the root, so the theme switch is a one-off, not a scroll path.
 */
const createTheme = () => {
    const mode = useColorMode({
        selector: 'html',
        attribute: 'class',
        modes: { light: '', dark: 'dark' },
        storageKey: 'app:theme',
        initialValue: 'dark',
        disableTransition: false,
    });

    const isDark = computed(() => mode.value === 'dark');

    const syncChrome = (dark: boolean): void => {
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';

        const token = dark ? '--color-aq-void' : '--p-surface-50';
        const chrome = getComputedStyle(document.documentElement).getPropertyValue(token).trim();

        if (chrome !== '') {
            document.querySelector('meta[name="theme-color"]')?.setAttribute('content', chrome);
        }
    };

    watch(isDark, syncChrome, { immediate: true });

    return {
        isDark,
        toggleTheme: (): void => {
            mode.value = isDark.value ? 'light' : 'dark';
        },
    };
};

let theme: ReturnType<typeof createTheme> | null = null;

export const useTheme = (): ReturnType<typeof createTheme> => (theme ??= createTheme());
