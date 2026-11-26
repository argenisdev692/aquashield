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
 * markup review cannot: the folded group is gone from the keyboard tree, and a
 * navigation into a folded group reopens it instead of hiding the page the user
 * just landed on.
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
    it('folds a section with a disclosure and reopens it when the route lands inside', async () => {
        const { container, rerender } = render(
            <SidebarNav
                groups={groupedPrimaryNav([...PERMISSIONS], CAPABILITIES)}
                currentPath="/dashboard"
            />,
        );

        const organization = screen.getByRole('button', { name: 'Organization' });

        expect(organization.getAttribute('aria-expanded')).toBe('true');
        expect(
            document.getElementById(organization.getAttribute('aria-controls') ?? ''),
        ).not.toBeNull();
        expect(screen.getByRole('link', { name: 'Users' })).toBeTruthy();

        fireEvent.click(organization);

        expect(organization.getAttribute('aria-expanded')).toBe('false');
        expect(screen.queryByRole('link', { name: 'Users' })).toBeNull();
        expect(JSON.parse(localStorage.getItem('aq:nav:collapsed-sections') ?? '[]')).toEqual([
            'Organization',
        ]);
        expect(await a11yViolations(container)).toEqual([]);

        rerender(
            <SidebarNav
                groups={groupedPrimaryNav([...PERMISSIONS], CAPABILITIES)}
                currentPath="/users"
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Organization' }).getAttribute('aria-expanded'),
        ).toBe('true');
        expect(screen.getByRole('link', { name: 'Users' })).toBeTruthy();
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
