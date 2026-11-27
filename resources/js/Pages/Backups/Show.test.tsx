// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';

type VisitOptions = {
    onCancelToken?: (token: { cancel: () => void }) => void;
    onCancel?: () => void;
    onError?: (errors: Record<string, string>) => void;
    onFinish?: () => void;
    onSuccess?: () => void;
};

const { pageProps, patch, deleteRequest, visit } = vi.hoisted(() => ({
    pageProps: {
        backup: {
            uuid: '0192f0c0-0000-7000-8000-000000000001',
            disk: 'local',
            filename: '2026-11-26-00-00-00.zip',
            size_bytes: 1024,
            size_human: '1.0 KB',
            taken_at: '2026-11-26T00:00:00+00:00',
            age_days: 1,
            is_newest: false,
            is_trashed: true,
        },
        activities: [],
        quarantineDays: 30,
        can: { download: false, delete: false, restore: true },
    },
    patch: vi.fn<(url: string, data: unknown, options: VisitOptions) => void>(),
    deleteRequest: vi.fn<(url: string, options: VisitOptions) => void>(),
    visit: vi.fn<(url: string) => void>(),
}));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children }: { children?: ReactNode }) => <a href="/backups">{children}</a>,
    router: { patch, delete: deleteRequest, visit },
    usePage: () => ({ props: pageProps }),
}));

vi.mock('@/Layouts/AppLayout', () => ({
    default: ({ children }: { children?: ReactNode }) => <main>{children}</main>,
}));

vi.mock('@/Components/DataTable', () => ({
    CrudDialog: ({
        open,
        onConfirm,
        children,
        confirmLabel,
    }: {
        open: boolean;
        onConfirm: () => void;
        children?: ReactNode;
        confirmLabel: string;
    }) =>
        open ? (
            <div role="dialog">
                {children}
                <button type="button" onClick={onConfirm}>
                    {confirmLabel}
                </button>
            </div>
        ) : null,
}));

import BackupsShow from './Show';

beforeEach(() => {
    vi.useFakeTimers();
    patch.mockReset();
    deleteRequest.mockReset();
    visit.mockReset();
    pageProps.backup.is_trashed = true;
    pageProps.can = { download: false, delete: false, restore: true };
});

afterEach(() => {
    cleanup();
    vi.useRealTimers();
});

describe('Backups/Show actions', () => {
    it('renders the backend uuids domain error for restore', () => {
        render(<BackupsShow />);

        fireEvent.click(screen.getByRole('button', { name: 'Restore archive' }));
        const options = patch.mock.calls[0]?.[2];

        act(() => {
            options?.onError?.({ uuids: 'The archive is no longer quarantined.' });
        });

        expect(screen.getByRole('alert').textContent).toContain(
            'The archive is no longer quarantined.',
        );
    });

    it('cancels a stalled restore after the deadline and clears pending', () => {
        render(<BackupsShow />);

        fireEvent.click(screen.getByRole('button', { name: 'Restore archive' }));
        const options = patch.mock.calls[0]?.[2];
        const cancel = vi.fn<() => void>(() => {
            options?.onCancel?.();
            options?.onFinish?.();
        });
        options?.onCancelToken?.({ cancel });

        act(() => {
            vi.advanceTimersByTime(30_000);
        });

        expect(cancel).toHaveBeenCalledTimes(1);
        expect(screen.getByRole('alert').textContent).toContain('The request timed out.');
        expect(
            screen.getByRole('button', { name: 'Restore archive' }).hasAttribute('disabled'),
        ).toBe(false);
    });

    it('renders the backend uuids domain error for quarantine', () => {
        pageProps.backup.is_trashed = false;
        pageProps.can = { download: false, delete: true, restore: false };
        render(<BackupsShow />);

        fireEvent.click(screen.getByRole('button', { name: 'Quarantine' }));
        fireEvent.click(
            within(screen.getByRole('dialog')).getByRole('button', { name: 'Quarantine' }),
        );
        const options = deleteRequest.mock.calls[0]?.[1];

        act(() => {
            options?.onError?.({ uuids: 'The newest archive is protected.' });
        });

        expect(within(screen.getByRole('dialog')).getByRole('alert').textContent).toContain(
            'The newest archive is protected.',
        );
    });
});
