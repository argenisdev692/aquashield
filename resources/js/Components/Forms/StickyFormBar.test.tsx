// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest';
import { cleanup, render, screen } from '@testing-library/react';
import StickyFormBar from './StickyFormBar';

/**
 * The bar pairs outline secondary actions with the AppButton submit CTA.
 * AppButton pins the design-system action height (`h-11`); a secondary left
 * at the shadcn default (`h-8`) renders a visibly shorter Cancel/Discard next
 * to it. Assert the equal-height contract on every action the bar can render,
 * with both the default "Save changes" label and a custom one.
 */

afterEach(cleanup);

describe('StickyFormBar action heights', () => {
    it('renders Cancel and Save changes at the same action height', () => {
        render(
            <StickyFormBar
                summary="Profile · 2 roles"
                isDirty
                processing={false}
                cancelLabel="Cancel"
                onCancel={vi.fn<() => void>()}
            />,
        );

        const cancel = screen.getByRole('button', { name: 'Cancel' });
        const submit = screen.getByRole('button', { name: 'Save changes' });

        expect(cancel.className).toContain('h-11');
        expect(submit.className).toContain('h-11');
    });

    it('renders Discard, Cancel and a custom submit label at the same action height', () => {
        render(
            <StickyFormBar
                summary="Invitation · no email yet · 0 roles"
                isDirty
                processing={false}
                submitLabel="Create user"
                cancelLabel="Cancel"
                onCancel={vi.fn<() => void>()}
                onDiscard={vi.fn<() => void>()}
            />,
        );

        const discard = screen.getByRole('button', { name: 'Discard' });
        const cancel = screen.getByRole('button', { name: 'Cancel' });
        const submit = screen.getByRole('button', { name: 'Create user' });

        for (const button of [discard, cancel, submit]) {
            expect(button.className).toContain('h-11');
        }
    });

    it('keeps secondary actions on the kit pairing geometry', () => {
        render(
            <StickyFormBar
                summary="Profile · 2 roles"
                isDirty
                processing={false}
                cancelLabel="Cancel"
                onCancel={vi.fn<() => void>()}
                onDiscard={vi.fn<() => void>()}
            />,
        );

        for (const name of ['Cancel', 'Discard']) {
            const button = screen.getByRole('button', { name });
            expect(button.className).toContain('min-w-28');
            expect(button.className).toContain('rounded-md');
        }
    });
});
