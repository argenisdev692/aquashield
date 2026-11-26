// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import PermissionMatrix from '@/Pages/AccessControl/Roles/components/PermissionMatrix';

const permissions: Modules.AccessControl.Data.PermissionOptionData[] = [
    { name: 'APPROVE_CLAIM', group: 'CLAIM', is_system: true },
    { name: 'REJECT_CLAIM', group: 'CLAIM', is_system: true },
    { name: 'CREATE_ROLE', group: 'ROLE', is_system: true },
];

afterEach(cleanup);

describe('PermissionMatrix selection scope', () => {
    it('adds only the permissions still visible under the search', () => {
        const onChange = vi.fn<(next: string[]) => void>();

        render(
            <PermissionMatrix
                permissions={permissions}
                value={['CREATE_ROLE']}
                onChange={onChange}
            />,
        );

        fireEvent.change(screen.getByRole('textbox', { name: 'Filter permissions' }), {
            target: { value: 'APPROVE' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Select all shown (1)' }));

        expect(onChange).toHaveBeenCalledWith(['APPROVE_CLAIM', 'CREATE_ROLE']);
    });

    it('clears every selected permission and emits an empty set', () => {
        const onChange = vi.fn<(next: string[]) => void>();

        render(
            <PermissionMatrix
                permissions={permissions}
                value={['APPROVE_CLAIM', 'CREATE_ROLE']}
                onChange={onChange}
            />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Clear all' }));

        expect(onChange).toHaveBeenCalledWith([]);
    });

    it('clears only the permissions shown when a filter is active', () => {
        const onChange = vi.fn<(next: string[]) => void>();

        render(
            <PermissionMatrix
                permissions={permissions}
                value={['APPROVE_CLAIM', 'CREATE_ROLE']}
                onChange={onChange}
            />,
        );

        fireEvent.change(screen.getByRole('textbox', { name: 'Filter permissions' }), {
            target: { value: 'APPROVE' },
        });

        fireEvent.click(screen.getByRole('button', { name: 'Clear shown (1)' }));

        expect(onChange).toHaveBeenCalledWith(['CREATE_ROLE']);
    });
});
