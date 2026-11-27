// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { a11yViolations } from '@/test/a11y';
import { dashboardGroups, groupedPrimaryNav } from '@/lib/navigation';

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

import SidebarNav from './SidebarNav';

/**
 * The rail's sections are disclosures, so the tests cover the two things a
 * markup review cannot: a folded group is gone from the keyboard tree, and a
 * group the user opens is remembered instead of collapsing on the next visit.
 */
const PERMISSIONS = [
    'VIEW_ANY_LEAD',
    'VIEW_ANY_APPOINTMENT',
    'VIEW_ANY_AVAILABILITY_EXCEPTION',
    'VIEW_COMPANY_DATA',
    'VIEW_ANY_ROLE',
    'VIEW_ANY_PERMISSION',
    'VIEW_ANY_BACKUPS',
    'VIEW_ANY_ACTIVITY_LOGS',
] as const;

const CAPABILITIES = { manageStaff: true, manageAccess: true };

beforeEach(() => {
    localStorage.clear();
});

afterEach(() => {
    cleanup();
});

describe('SidebarNav sections', () => {
    it('folds every group that is not current and remembers the one the user opens', async () => {
        const { container, rerender } = render(
            <SidebarNav
                groups={groupedPrimaryNav([...PERMISSIONS], CAPABILITIES)}
                currentPath="/dashboard"
            />,
        );

        const workspace = screen.getByRole('button', { name: 'Workspace' });
        const organization = screen.getByRole('button', { name: 'Organization' });

        // Dashboard is a standalone row, so no group owns this route and every
        // section starts folded.
        expect(workspace.getAttribute('aria-expanded')).toBe('false');
        expect(organization.getAttribute('aria-expanded')).toBe('false');
        expect(
            document.getElementById(organization.getAttribute('aria-controls') ?? ''),
        ).not.toBeNull();
        expect(screen.queryByRole('link', { name: 'Users' })).toBeNull();

        fireEvent.click(organization);

        expect(organization.getAttribute('aria-expanded')).toBe('true');
        expect(screen.getByRole('link', { name: 'Users' })).toBeTruthy();
        expect(JSON.parse(localStorage.getItem('aq:nav:sections') ?? '{}')).toEqual({
            Organization: true,
        });
        expect(await a11yViolations(container)).toEqual([]);

        // Leaving the section keeps it open: that is the user's choice. The
        // group that owns the new route opens alongside it.
        rerender(
            <SidebarNav
                groups={groupedPrimaryNav([...PERMISSIONS], CAPABILITIES)}
                currentPath="/leads"
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Organization' }).getAttribute('aria-expanded'),
        ).toBe('true');
        expect(screen.getByRole('link', { name: 'Users' })).toBeTruthy();
        expect(
            screen.getByRole('button', { name: 'Workspace' }).getAttribute('aria-expanded'),
        ).toBe('true');
    });

    it('keeps every destination reachable when the rail collapses to icons', () => {
        render(
            <SidebarNav
                groups={dashboardGroups('/dashboard', [...PERMISSIONS], CAPABILITIES)}
                currentPath="/dashboard"
                collapsed
            />,
        );

        // No arrow in a 72px column: the heading is screen-reader text and the
        // rows render unconditionally.
        expect(screen.queryByRole('button', { name: 'Workspace' })).toBeNull();
        expect(screen.getByRole('link', { name: 'Users' })).toBeTruthy();
        expect(screen.getByRole('link', { name: 'Settings' })).toBeTruthy();
    });
});
