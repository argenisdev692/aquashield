/**
 * ThemeToggle — dark/light mode switch.
 *
 * A header icon control, so it follows the same recipe as the inbox triggers:
 * a 36px square, rounded-lg, quiet hover fill and no resting outline. The old
 * bordered circle was the only ringed button in a bar of borderless ones, and
 * next to the search field it read as a second input.
 *
 * The rail footer keeps its circular sign-out on purpose: that one sits beside
 * a circular avatar, where matching the portrait matters more than matching
 * the toolbar.
 */
import { Sun, Moon } from 'lucide-react';
import { useTheme } from '@/lib/theme';

export default function ThemeToggle() {
    const { isDark, toggleTheme } = useTheme();

    return (
        <button
            type="button"
            className="grid size-9 place-items-center rounded-lg text-surface-700 transition-colors duration-150 hover:bg-surface-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500 motion-reduce:transition-none dark:text-surface-100 dark:hover:bg-surface-800"
            onClick={toggleTheme}
            aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
        >
            {isDark ? (
                <Sun className="size-4" aria-hidden="true" />
            ) : (
                <Moon className="size-4" aria-hidden="true" />
            )}
        </button>
    );
}
