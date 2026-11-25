// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
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
        render(
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
        render(
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
        render(
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
        render(
            <LeadKanban
                columns={columns}
                transitions={{ new: ['contacted'], contacted: [] }}
                canUpdate={false}
                onSelect={() => undefined}
            />,
        );

        expect(screen.queryByRole('combobox')).toBeNull();
    });

    it('flags the emergency card without relying on colour alone', () => {
        render(
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
        render(
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

    it('reports a rejected stage change in an alert region', () => {
        render(
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

    it('has no structural accessibility violations', async () => {
        const { container } = render(
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
