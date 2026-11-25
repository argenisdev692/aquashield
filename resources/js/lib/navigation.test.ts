import { describe, expect, it } from 'vitest';
import { dashboardGroups, groupedPrimaryNav, visiblePrimaryNav } from './navigation';

const accessPaths = ['/roles', '/permissions'];

const allPermissions = [
    'VIEW_ANY_LEAD',
    'VIEW_ANY_APPOINTMENT',
    'VIEW_ANY_AVAILABILITY_EXCEPTION',
    'VIEW_COMPANY_DATA',
    'VIEW_ANY_ROLE',
    'VIEW_ANY_PERMISSION',
    'VIEW_ANY_BACKUPS',
    'VIEW_ANY_ACTIVITY_LOGS',
];

const manageBoth = { manageAccess: true, manageStaff: true };

const accessItems = (permissions: string[], manageAccess: boolean) =>
    visiblePrimaryNav(permissions, { manageAccess, manageStaff: false }).filter((item) =>
        accessPaths.includes(item.href),
    );

describe('visiblePrimaryNav access catalogue', () => {
    it('requires the manage-access capability and the matching view permission', () => {
        expect(accessItems(['VIEW_ANY_ROLE'], true).map((item) => item.href)).toEqual(['/roles']);
        expect(accessItems(['VIEW_ANY_PERMISSION'], true).map((item) => item.href)).toEqual([
            '/permissions',
        ]);
    });

    it('hides both links when the role lacks either the capability or the permission', () => {
        expect(accessItems(['VIEW_ANY_ROLE', 'VIEW_ANY_PERMISSION'], false)).toEqual([]);
        expect(accessItems([], true)).toEqual([]);
    });
});

describe('groupedPrimaryNav', () => {
    it('splits the permitted destinations into the rail sections', () => {
        const groups = groupedPrimaryNav(allPermissions, manageBoth);

        expect(groups.map((group) => group.label)).toEqual(['Workspace', 'Organization', 'System']);
        expect(groups[0].items.map((item) => item.href)).toEqual([
            '/dashboard',
            '/leads',
            '/appointments',
            '/availability',
        ]);
        expect(groups[1].items.map((item) => item.href)).toEqual(['/company-data/edit', '/users']);
        expect(groups[2].items.map((item) => item.href)).toEqual([
            '/roles',
            '/permissions',
            '/backups',
            '/activity',
        ]);
    });

    it('drops an empty section instead of rendering its heading', () => {
        const groups = groupedPrimaryNav([], { manageAccess: false, manageStaff: false });

        expect(groups.map((group) => group.label)).toEqual(['Workspace']);
        expect(groups[0].items.map((item) => item.href)).toEqual(['/dashboard']);
    });
});

describe('dashboardGroups', () => {
    it('closes the rail with the account group and no duplicate page anchors', () => {
        const groups = dashboardGroups('/dashboard', allPermissions, manageBoth);

        expect(groups.map((group) => group.label)).toEqual([
            'Workspace',
            'Organization',
            'System',
            'Account',
        ]);
        expect(groups.some((group) => group.label === 'On this page')).toBe(false);
        expect(groups.at(-1)?.items.map((item) => item.href)).toEqual(['/settings/profile']);
    });
});
