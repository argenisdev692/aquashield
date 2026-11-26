// @vitest-environment jsdom
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';

/*
 * The ActivityLog page is the audit surface: the event text, the export gate
 * and the view action are the behaviours that matter. It renders the full app
 * shell, so the Inertia transport and the shell's query-backed pieces are
 * stubbed; the table kit itself runs for real.
 */
const { pageProps, visit } = vi.hoisted(() => ({
    visit: vi.fn<(url: string) => void>(),
    pageProps: {
        rows: [
            {
                uuid: '0192f0c0-0000-7000-8000-000000000001',
                log_name: 'backups',
                event: 'backup.quarantined',
                description: 'Quarantined a dump after a failed restore.',
                causer_name: 'Ada Lovelace',
                subject_type: 'Backup',
                properties: {},
                created_at: '2026-11-26T10:00:00+00:00',
            },
        ],
        total: 1,
        currentPage: 1,
        perPage: 15,
        canView: true,
        canExport: true,
        authPermissions: [],
        authIdle: null,
        cookieConsent: null,
        canManageStaff: false,
        canManageAccess: false,
    },
}));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children }: { children?: ReactNode }) => <a href="/activity">{children}</a>,
    router: { visit },
    usePage: () => ({ props: pageProps, url: '/activity' }),
    useHttp: () => ({ processing: false, post: () => Promise.resolve() }),
}));

vi.mock('@/lib/visits', () => ({ useVisitPending: () => false }));
vi.mock('@/lib/auth', () => ({ useLogout: () => vi.fn<() => void>() }));
vi.mock('@/Modules/SavedViews/components/SavedViewsMenu', () => ({ default: () => null }));

import ActivityLogIndex from '@/Pages/ActivityLog/Index';

beforeAll(() => {
    Object.defineProperty(window, 'matchMedia', {
        writable: true,
        value: (query: string) => ({
            matches: false,
            media: query,
            onchange: null,
            addEventListener: vi.fn<() => void>(),
            removeEventListener: vi.fn<() => void>(),
            addListener: vi.fn<() => void>(),
            removeListener: vi.fn<() => void>(),
            dispatchEvent: vi.fn<() => void>(),
        }),
    });
});

afterEach(() => {
    cleanup();
    visit.mockClear();
    pageProps.canView = true;
    pageProps.canExport = true;
});

describe('ActivityLog/Index', () => {
    it('renders the recorded event and offers the detail action', () => {
        render(<ActivityLogIndex />);

        expect(screen.getByText('Recorded events')).toBeTruthy();
        expect(screen.getByText('backup.quarantined')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'View entry backup.quarantined' })).toBeTruthy();
    });

    it('hides the export control without the export capability', () => {
        pageProps.canExport = false;

        render(<ActivityLogIndex />);

        expect(screen.queryByRole('button', { name: /export/i })).toBeNull();
    });

    it('hides the row action without the view capability', () => {
        pageProps.canView = false;

        render(<ActivityLogIndex />);

        expect(screen.queryByRole('button', { name: 'View entry backup.quarantined' })).toBeNull();
    });
});
