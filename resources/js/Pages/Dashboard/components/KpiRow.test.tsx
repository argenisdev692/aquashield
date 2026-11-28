// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
    Link: ({
        href,
        children,
        ...props
    }: {
        href: string;
        children: React.ReactNode;
        className?: string;
    }) => (
        <a href={href} {...props}>
            {children}
        </a>
    ),
}));

import KpiRow from './KpiRow';

afterEach(() => {
    cleanup();
});

const stats = {
    leads: { current: 24, previous: 20, series: [0, 2, 4] },
    inspections: { current: 18, previous: 18, series: [1, 2, 3] },
    won: { current: 32, previous: 28, series: [2, 4, 6] },
    appointments: { current: 27, previous: 30, series: [3, 2, 1] },
};

const query = (href: string): URLSearchParams => new URL(href, 'https://example.test').searchParams;

describe('KpiRow', () => {
    it('renders the four period metrics with their scoped destinations', () => {
        render(
            <KpiRow
                stats={stats}
                rangeFrom="2026-11-01"
                rangeTo="2026-11-30"
                filters={{ region: 'dallas', source: 'meta' }}
                canViewLeads
                canViewCalendar
            />,
        );

        expect(screen.getByText('24')).toBeTruthy();
        expect(screen.getByText('18')).toBeTruthy();
        expect(screen.getByText('32')).toBeTruthy();
        expect(screen.getByText('27')).toBeTruthy();

        const won = query(screen.getByRole('link', { name: /won/i }).getAttribute('href') ?? '');
        const leads = query(
            screen.getByRole('link', { name: /new leads/i }).getAttribute('href') ?? '',
        );
        const inspections = query(
            screen.getByRole('link', { name: /inspections/i }).getAttribute('href') ?? '',
        );

        expect(won.get('filter[status]')).toBe('won');
        expect(won.get('filter[converted_from]')).toBe('2026-11-01');
        expect(won.get('filter[converted_to]')).toBe('2026-11-30');
        expect(leads.get('filter[status]')).toBeNull();
        expect(leads.get('filter[date_from]')).toBe('2026-11-01');
        expect(inspections.get('filter[scheduled_only]')).toBe('1');
        expect(leads.get('filter[region]')).toBe('dallas');
        expect(leads.get('filter[source]')).toBe('meta');
    });

    it('does not render a destination when the viewer lacks the permission', () => {
        render(
            <KpiRow
                stats={stats}
                rangeFrom="2026-11-01"
                rangeTo="2026-11-30"
                canViewLeads={false}
                canViewCalendar={false}
            />,
        );

        expect(screen.queryByRole('link')).toBeNull();
    });
});
