// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { a11yViolations } from '@/test/a11y';

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

import KpiCard from './KpiCard';

afterEach(() => {
    cleanup();
});

describe('KpiCard', () => {
    it('makes the whole metric a link when a destination exists', () => {
        render(
            <KpiCard
                label="Won"
                value={4}
                delta="+2 vs. last month"
                trend="up"
                href="/leads?filter%5Bstatus%5D=won"
            />,
        );

        const link = screen.getByRole('link', { name: /won/i });

        expect(link.getAttribute('href')).toBe('/leads?filter%5Bstatus%5D=won');
        expect(screen.getByText('4')).toBeTruthy();
        expect(screen.getByText('+2 vs. last month')).toBeTruthy();
    });

    it('stays informational when there is no destination', () => {
        render(<KpiCard label="Won" value={0} delta="No activity this month" />);

        expect(screen.queryByRole('link')).toBeNull();
        expect(screen.getByText('No activity this month')).toBeTruthy();
    });

    it('has no structural accessibility violations', async () => {
        const { container } = render(
            <KpiCard label="Won" value={4} delta="+2 vs. last month" trend="up" href="/leads" />,
        );

        expect(await a11yViolations(container)).toEqual([]);
    });
});
