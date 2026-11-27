// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';

const { pageProps, runState } = vi.hoisted(() => ({
    pageProps: {
        rows: [],
        total: 0,
        currentPage: 1,
        perPage: 15,
        lastPage: 1,
        filters: {},
        disks: [],
        runStatus: { status: 'idle', updated_at: null },
        can: {
            create: true,
            delete: false,
            bulk_delete: false,
            restore: false,
            bulk_restore: false,
            download: false,
            export: false,
            view: false,
        },
    },
    runState: {
        run: vi.fn<() => Promise<boolean>>(),
        pending: false,
        polling: false,
        status: 'idle' as Modules.Backups.Domain.Enums.BackupRunStatus,
        error: null as string | null,
        reset: vi.fn<() => void>(),
    },
}));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: { visit: vi.fn<(url: string) => void>() },
    usePage: () => ({ props: pageProps, url: '/backups' }),
}));

vi.mock('@/Layouts/AppLayout', () => ({
    default: ({ children }: { children?: ReactNode }) => <main>{children}</main>,
}));

vi.mock('@/lib/visits', () => ({ useVisitPending: () => false }));

vi.mock('@/lib/crud-table', () => ({
    useCrudTable: () => ({
        kitProps: {},
        state: { page: 1, perPage: 15, search: '', sort: null, filters: {} },
        activeFilterCount: 0,
        hasActiveFilters: false,
        reset: vi.fn<() => void>(),
        exportUrl: vi.fn<(format: string) => string>(),
    }),
    useBulkActions: () => ({
        processing: false,
        error: null,
        deleteRows: vi.fn<(rows: unknown[]) => void>(),
        restoreRows: vi.fn<(rows: unknown[]) => void>(),
    }),
}));

vi.mock('@/Modules/Backups/hooks/useRunBackup', () => ({
    useRunBackup: () => runState,
}));

vi.mock('@/Modules/SavedViews/components/SavedViewsMenu', () => ({ default: () => null }));

vi.mock('@/Components/DataTable', () => ({
    DataTableKit: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
    CrudFilters: () => null,
    DataTableRowActions: () => null,
    CrudDialog: ({
        open,
        title,
        description,
        children,
        confirmLabel,
        confirmHidden,
        onConfirm,
    }: {
        open: boolean;
        title: string;
        description?: string;
        children?: ReactNode;
        confirmLabel: string;
        confirmHidden?: boolean;
        onConfirm: () => void;
    }) =>
        open ? (
            <div role="dialog">
                <h2>{title}</h2>
                <p>{description}</p>
                {children}
                {confirmHidden ? null : (
                    <button type="button" onClick={onConfirm}>
                        {confirmLabel}
                    </button>
                )}
            </div>
        ) : null,
}));

import BackupsIndex from './Index';

beforeEach(() => {
    runState.pending = false;
    runState.polling = false;
    runState.status = 'idle';
    runState.error = null;
    runState.run.mockReset();
    runState.reset.mockReset();
});

afterEach(() => {
    cleanup();
});

describe('Backups/Index run dialog', () => {
    it('opens the live status without resetting polling', () => {
        runState.polling = true;
        runState.status = 'running';
        render(<BackupsIndex />);

        const button = screen.getByRole('button', { name: 'Run backup' });
        expect(button.hasAttribute('disabled')).toBe(false);

        fireEvent.click(button);

        expect(screen.getByRole('heading', { name: 'Backup running' })).toBeTruthy();
        expect(runState.reset).not.toHaveBeenCalled();
    });

    it('shows the terminal failure instead of a queued message', () => {
        runState.status = 'failed';
        runState.error = 'The backup failed. Check the audit trail and queue worker.';
        render(<BackupsIndex />);

        fireEvent.click(screen.getByRole('button', { name: 'Run backup' }));

        expect(screen.getByRole('heading', { name: 'Backup not completed' })).toBeTruthy();
        expect(screen.getByRole('alert').textContent).toContain('The backup failed.');
        expect(
            within(screen.getByRole('dialog')).queryByRole('button', { name: 'Run backup' }),
        ).toBeNull();
    });

    it('shows completion and keeps the archive refresh copy', () => {
        runState.status = 'succeeded';
        render(<BackupsIndex />);

        fireEvent.click(screen.getByRole('button', { name: 'Run backup' }));

        expect(screen.getByRole('heading', { name: 'Backup complete' })).toBeTruthy();
        expect(screen.getByRole('status').textContent).toContain('newest archive');
    });
});
