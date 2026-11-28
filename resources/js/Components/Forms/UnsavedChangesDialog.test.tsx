// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import type { NavigationGuard } from '@/lib/leave-guard';
import UnsavedChangesDialog from './UnsavedChangesDialog';

afterEach(() => {
    cleanup();
});

const guard = (overrides: Partial<NavigationGuard> = {}): NavigationGuard => ({
    pendingUrl: '/leads',
    confirmLeave: vi.fn<() => void>(),
    cancelLeave: vi.fn<() => void>(),
    ...overrides,
});

describe('UnsavedChangesDialog', () => {
    it('continues the pending navigation when the draft is discarded', () => {
        const value = guard();

        render(<UnsavedChangesDialog guard={value} />);

        fireEvent.click(screen.getByRole('button', { name: 'Discard changes' }));

        expect(value.confirmLeave).toHaveBeenCalledTimes(1);
        expect(value.cancelLeave).not.toHaveBeenCalled();
    });

    it('cancels the navigation when the user keeps editing', () => {
        const value = guard();

        render(<UnsavedChangesDialog guard={value} />);

        fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(value.cancelLeave).toHaveBeenCalledTimes(1);
        expect(value.confirmLeave).not.toHaveBeenCalled();
    });
});
