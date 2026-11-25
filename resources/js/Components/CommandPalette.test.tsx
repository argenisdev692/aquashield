// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { Search } from 'lucide-react';
import { a11yViolations } from '@/test/a11y';
import type { Command } from '@/lib/commands';
import CommandPalette from './CommandPalette';

/**
 * The palette puts focus in the input and moves the active option with
 * `aria-activedescendant` — the browser does not scroll that option into view.
 * With 17 commands on the dashboard and a 20rem scroller, ArrowDown past the
 * fold used to select a row the user could not see, and Enter ran it.
 */
const commands: Command[] = Array.from({ length: 12 }, (_, index) => ({
    id: `command-${index}`,
    group: index < 6 ? 'Go to' : 'Preferences',
    label: `Destination ${index}`,
    icon: Search,
    run: vi.fn<() => void>(),
}));

const scrollIntoView = vi.fn<() => void>();

class ObserverStub {
    observe(): void {}

    unobserve(): void {}

    disconnect(): void {}
}

beforeEach(() => {
    Element.prototype.scrollIntoView = scrollIntoView;
    globalThis.ResizeObserver ??= ObserverStub as unknown as typeof ResizeObserver;
    scrollIntoView.mockClear();
});

afterEach(() => {
    cleanup();
});

const renderPalette = (): void => {
    render(<CommandPalette open onOpenChange={() => undefined} commands={commands} />);
};

const selectedOption = (): HTMLElement => {
    const selected = document.querySelectorAll<HTMLElement>(
        '[role="option"][aria-selected="true"]',
    );

    expect(selected).toHaveLength(1);

    return selected[0];
};

describe('CommandPalette', () => {
    it('exposes a combobox that owns the active option', () => {
        renderPalette();

        const input = screen.getByRole('combobox', { name: /search commands/i });

        expect(input.getAttribute('aria-activedescendant')).toBe(selectedOption().id);
    });

    it('scrolls the active option into view as the keyboard moves', () => {
        renderPalette();

        const input = screen.getByRole('combobox', { name: /search commands/i });

        for (let step = 0; step < 9; step += 1) {
            fireEvent.keyDown(input, { key: 'ArrowDown' });
        }

        const active = selectedOption();

        expect(active.textContent).toContain('Destination 9');
        expect(input.getAttribute('aria-activedescendant')).toBe(active.id);
        expect(scrollIntoView).toHaveBeenCalled();
    });

    it('wraps from the last option to the first', () => {
        renderPalette();

        const input = screen.getByRole('combobox', { name: /search commands/i });

        fireEvent.keyDown(input, { key: 'ArrowUp' });

        expect(selectedOption().textContent).toContain('Destination 11');
    });

    it('groups the options so a listbox only contains valid children', () => {
        renderPalette();

        const groups = within(screen.getByRole('listbox')).getAllByRole('group');

        expect(groups).toHaveLength(2);
        expect(groups[0].getAttribute('aria-label')).toBe('Go to');
    });

    it('has no structural accessibility violations', async () => {
        renderPalette();

        expect(await a11yViolations(document.body)).toEqual([]);
    });
});
