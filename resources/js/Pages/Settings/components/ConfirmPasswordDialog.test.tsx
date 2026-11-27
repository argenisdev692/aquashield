// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';

/**
 * The gate pairs an outline Cancel with the AppButton "Confirm password"
 * CTA. AppButton pins the design-system action height (`h-11`); Cancel left
 * at the shadcn default (`h-8`) rendered a visibly shorter button. The form
 * engine is mocked so the assertion stays on the markup contract, not on
 * Inertia.
 */
vi.mock('@/lib/forms', () => ({
    useZodForm: () => ({
        form: {
            data: { password: '' },
            setData: vi.fn<(key: string, value: unknown) => void>(),
            reset: vi.fn<(key?: string) => void>(),
            processing: false,
        },
        errors: {},
        submit: (action: () => void) => action(),
        touch: vi.fn<(field: string) => void>(),
        clearError: vi.fn<(field: string) => void>(),
    }),
}));

import ConfirmPasswordDialog from './ConfirmPasswordDialog';

afterEach(cleanup);

describe('ConfirmPasswordDialog action heights', () => {
    it('renders Cancel and Confirm password at the same action height', () => {
        render(
            <ConfirmPasswordDialog
                open
                onOpenChange={vi.fn<(open: boolean) => void>()}
                onConfirm={vi.fn<(password: string) => Promise<void>>(async () => {})}
                onConfirmed={vi.fn<() => void>()}
            />,
        );

        const cancel = screen.getByRole('button', { name: 'Cancel' });
        const confirm = screen.getByRole('button', { name: 'Confirm password' });

        expect(cancel.className).toContain('h-11');
        expect(confirm.className).toContain('h-11');
        expect(cancel.className).toContain('min-w-28');
        expect(confirm.className).toContain('min-w-28');
    });
});
