// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { a11yViolations } from '@/test/a11y';

/**
 * The idle warning is a security control: it is the only surface that asks for
 * a decision before the server ends the session. A hand-rolled
 * `<div role="alertdialog">` shipped without a name, a focus move or a trap, so
 * a keyboard user kept focus on the page behind the overlay and could not
 * reach "Keep working". These assertions pin the three things that make it a
 * dialog rather than a picture of one.
 */
const session = {
    visible: true,
    remainingSeconds: 45,
    keeping: false,
    keepSession: vi.fn<() => Promise<void>>(),
    logout: vi.fn<() => void>(),
};

vi.mock('@/Modules/Auth/hooks/useIdleSession', () => ({
    useIdleSession: () => session,
}));

import SessionIdleWarning from './SessionIdleWarning';

class ObserverStub {
    observe(): void {}

    unobserve(): void {}

    disconnect(): void {}
}

beforeEach(() => {
    globalThis.ResizeObserver ??= ObserverStub as unknown as typeof ResizeObserver;
    session.visible = true;
    session.keepSession.mockClear();
    session.logout.mockClear();
});

afterEach(() => {
    cleanup();
});

describe('SessionIdleWarning', () => {
    it('renders a named alertdialog, not an anonymous div', async () => {
        render(<SessionIdleWarning />);

        const dialog = screen.getByRole('alertdialog', {
            name: /your session will expire soon/i,
        });

        expect(dialog.getAttribute('aria-describedby')).not.toBeNull();
        expect(dialog.getAttribute('aria-modal')).toBe('true');

        expect(await a11yViolations(document.body)).toEqual([]);
    });

    it('moves focus inside the dialog and ignores Escape', async () => {
        render(<SessionIdleWarning />);

        const dialog = screen.getByRole('alertdialog');

        await waitFor(() => expect(dialog.contains(document.activeElement)).toBe(true));

        fireEvent.keyDown(dialog, { key: 'Escape' });

        expect(screen.getByRole('alertdialog')).toBeTruthy();
    });

    it('keeps the session alive through the kit button', () => {
        render(<SessionIdleWarning />);

        fireEvent.click(screen.getByRole('button', { name: /keep working/i }));

        expect(session.keepSession).toHaveBeenCalledTimes(1);
    });

    it('signs out through the kit button', () => {
        render(<SessionIdleWarning />);

        fireEvent.click(screen.getByRole('button', { name: /sign out/i }));

        expect(session.logout).toHaveBeenCalledTimes(1);
    });

    it('renders nothing while the session is healthy', () => {
        session.visible = false;

        render(<SessionIdleWarning />);

        expect(screen.queryByRole('alertdialog')).toBeNull();
    });
});
