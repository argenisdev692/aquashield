// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { a11yViolations } from '@/test/a11y';

/**
 * The board only offers transitions the server said are legal, and posts the
 * stage change with the current card id. The server re-checks the transition;
 * this test pins the half the browser owns: no option for a forbidden stage,
 * and the exact call the drag/selector performs.
 */
const { post } = vi.hoisted(() => ({
    post: vi.fn<(url: string, data?: unknown, options?: unknown) => void>(),
}));

vi.mock('@inertiajs/react', () => ({
    router: { post },
    Link: ({ href, children }: { href: string; children: React.ReactNode }) => (
        <a href={href}>{children}</a>
    ),
}));

import LeadKanban from './LeadKanban';

type Card = Modules.LeadAppointment.Application.Dtos.LeadCardData;
type Column = Modules.LeadAppointment.Application.Dtos.LeadBoardColumnData;

const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false } },
});

const renderKanban = (ui: React.ReactElement) =>
    render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);

const card = (overrides: Partial<Card> = {}): Card => ({
    uuid: 'lead-1',
    full_name: 'Ada Lovelace',
    phone: '555-0100',
    city: 'Dallas',
    state: 'TX',
    region: 'dallas',
    source: 'website',
    status: 'new',
    is_emergency: false,
    is_spam: false,
    last_contacted_at: null,
    inspection_at: null,
    created_at: '2026-11-01T12:00:00+00:00',
    ...overrides,
});

const columns: Column[] = [
    { status: 'new', label: 'New', total: 1, leads: [card()] },
    {
        status: 'contacted',
        label: 'Contacted',
        total: 1,
        leads: [card({ uuid: 'lead-2', full_name: 'Grace Hopper', status: 'contacted' })],
    },
];

beforeEach(() => {
    post.mockReset();
});

afterEach(() => {
    cleanup();
});

describe('LeadKanban', () => {
    it('offers only the transitions the server allows', () => {
        renderKanban(
            <LeadKanban
                columns={columns}
                transitions={{ new: ['contacted'], contacted: [] }}
                canUpdate
                onSelect={() => undefined}
            />,
        );

        const select = screen.getByLabelText(/move ada lovelace to another stage/i);

        const options = within(select as HTMLElement)
            .getAllByRole('option')
            .map((option) => option.textContent);

        expect(options).toEqual(['Move to…', 'Contacted']);
    });

    it('posts the new stage for the card that was selected', () => {
        renderKanban(
            <LeadKanban
                columns={columns}
                transitions={{ new: ['contacted'], contacted: [] }}
                canUpdate
                onSelect={() => undefined}
            />,
        );

        fireEvent.change(screen.getByLabelText(/move ada lovelace/i), {
            target: { value: 'contacted' },
        });

        expect(post).toHaveBeenCalledTimes(1);
        expect(post.mock.calls[0][0]).toBe('/leads/lead-1/status');
        expect(post.mock.calls[0][1]).toEqual({ status: 'contacted', note: null });
        expect(post.mock.calls[0][2]).toEqual(
            expect.objectContaining({ preserveScroll: true, preserveState: true }),
        );
    });

    it('renders no selector for a stage with no legal transition', () => {
        renderKanban(
            <LeadKanban
                columns={columns}
                transitions={{ new: [], contacted: [] }}
                canUpdate
                onSelect={() => undefined}
            />,
        );

        expect(screen.queryByLabelText(/move ada lovelace/i)).toBeNull();
    });

    it('renders no selector at all without update permission', () => {
        renderKanban(
            <LeadKanban
                columns={columns}
                transitions={{ new: ['contacted'], contacted: [] }}
                canUpdate={false}
                onSelect={() => undefined}
            />,
        );

        expect(screen.queryByRole('combobox')).toBeNull();
    });

    it('keeps the dashboard period and scope in the other-stage links', () => {
        renderKanban(
            <LeadKanban
                columns={[...columns, { status: 'lost', label: 'Lost', total: 3, leads: [] }]}
                transitions={{ new: ['contacted'], contacted: [] }}
                canUpdate
                variant="fit"
                scopeFilters={{ region: 'dallas', source: 'meta' }}
                rangeFrom="2026-11-01"
                rangeTo="2026-11-30"
                onSelect={() => undefined}
            />,
        );

        const href = screen.getByRole('link', { name: /lost/i }).getAttribute('href') ?? '';
        const query = new URL(href, 'https://example.test').searchParams;

        expect(query.get('filter[status]')).toBe('lost');
        expect(query.get('filter[region]')).toBe('dallas');
        expect(query.get('filter[source]')).toBe('meta');
        expect(query.get('filter[date_from]')).toBe('2026-11-01');
        expect(query.get('filter[date_to]')).toBe('2026-11-30');
    });

    it('flags the emergency card without relying on colour alone', () => {
        renderKanban(
            <LeadKanban
                columns={[
                    {
                        status: 'new',
                        label: 'New',
                        total: 1,
                        leads: [card({ is_emergency: true })],
                    },
                ]}
                transitions={{ new: ['contacted'] }}
                canUpdate
                onSelect={() => undefined}
            />,
        );

        expect(screen.getByText('Emergency')).toBeTruthy();
    });

    it('flags a spam lead in the card header', () => {
        renderKanban(
            <LeadKanban
                columns={[
                    {
                        status: 'new',
                        label: 'New',
                        total: 1,
                        leads: [card({ is_spam: true })],
                    },
                ]}
                transitions={{ new: ['contacted'] }}
                canUpdate
                onSelect={() => undefined}
            />,
        );

        expect(screen.getByText('Spam')).toBeTruthy();
    });

    it('disables stage writes while a scope visit is pending', () => {
        renderKanban(
            <LeadKanban
                columns={columns}
                transitions={{ new: ['contacted'], contacted: [] }}
                canUpdate
                disabled
                onSelect={() => undefined}
            />,
        );

        const select = screen.getByLabelText(
            /move ada lovelace to another stage/i,
        ) as HTMLSelectElement;

        expect(select.disabled).toBe(true);
    });

    it('invalidates the client-owned lead queries after a successful move', () => {
        const client = new QueryClient({
            defaultOptions: { queries: { retry: false } },
        });
        const key = ['lead-appointment', 'leads-panel', 'test'];

        client.setQueryData(key, { rows: [], total: 0 });

        render(
            <QueryClientProvider client={client}>
                <LeadKanban
                    columns={columns}
                    transitions={{ new: ['contacted'], contacted: [] }}
                    canUpdate
                    onSelect={() => undefined}
                />
            </QueryClientProvider>,
        );

        fireEvent.change(screen.getByLabelText(/move ada lovelace/i), {
            target: { value: 'contacted' },
        });

        const options = post.mock.calls[0]?.[2] as { onSuccess?: () => void } | undefined;

        act(() => options?.onSuccess?.());

        expect(client.getQueryState(key)?.isInvalidated).toBe(true);
    });

    it('reports a rejected stage change in an alert region', () => {
        renderKanban(
            <LeadKanban
                columns={columns}
                transitions={{ new: ['contacted'], contacted: [] }}
                canUpdate
                errors={{ status: 'That stage change is not allowed.' }}
                onSelect={() => undefined}
            />,
        );

        expect(screen.getByRole('alert').textContent).toContain('not allowed');
    });

    it('keeps the horizontal board reachable from the keyboard', () => {
        renderKanban(
            <LeadKanban
                columns={columns}
                transitions={{ new: ['contacted'], contacted: [] }}
                canUpdate
                onSelect={() => undefined}
            />,
        );

        const board = screen.getByRole('region', { name: /lead pipeline board/i });

        expect(board.getAttribute('tabindex')).toBe('0');
    });

    it('has no structural accessibility violations', async () => {
        const { container } = renderKanban(
            <LeadKanban
                columns={columns}
                transitions={{ new: ['contacted'], contacted: [] }}
                canUpdate
                onSelect={() => undefined}
            />,
        );

        expect(await a11yViolations(container)).toEqual([]);
    });
});
