import { describe, expect, it } from 'vitest';
import { visiblePrimaryNav } from './navigation';

const accessPaths = ['/roles', '/permissions'];

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
