// @vitest-environment jsdom
import { act, renderHook } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { z } from 'zod';
import { useZodForm } from './forms';

/**
 * Regression for Inertia v3.11: `setData({ ... })` replaces the entire form
 * payload. One keystroke used to collapse the Users profile form to a single
 * key, and the next render crashed the availability probe reading `email`.
 */
const schema = z.object({
    first_name: z.string().min(1, 'Enter a first name'),
    last_name: z.string(),
    email: z.string().email('Enter a valid email address'),
    zip_code: z.string(),
    city: z.string(),
});

const initial = {
    first_name: 'Ana',
    last_name: 'Diaz',
    email: 'ana@example.com',
    zip_code: '',
    city: '',
};

const useHarness = () => useZodForm({ initial, schema });

describe('useZodForm field writes', () => {
    it('merges a single field instead of replacing the payload', () => {
        const { result } = renderHook(useHarness);

        act(() => {
            result.current.setField('first_name', 'Ana María');
        });

        expect(result.current.form.data).toEqual({
            ...initial,
            first_name: 'Ana María',
        });
        expect(result.current.form.data.email).toBe('ana@example.com');
    });

    it('applies a multi-field patch in one write', () => {
        const { result } = renderHook(useHarness);

        act(() => {
            result.current.setFields({ zip_code: '08001', city: 'Barcelona' });
        });

        expect(result.current.form.data).toEqual({
            ...initial,
            zip_code: '08001',
            city: 'Barcelona',
        });
    });

    it('clears two client errors in one atomic update', () => {
        const { result } = renderHook(useHarness);

        act(() => {
            result.current.setFields({ first_name: '', email: 'not-an-email' });
        });

        act(() => {
            result.current.touch('first_name');
            result.current.touch('email');
        });

        expect(result.current.clientErrors.first_name).toBe('Enter a first name');
        expect(result.current.clientErrors.email).toBe('Enter a valid email address');

        act(() => {
            result.current.setFields({ first_name: 'Ana', email: 'ana@example.com' });
        });

        expect(result.current.clientErrors.first_name).toBeUndefined();
        expect(result.current.clientErrors.email).toBeUndefined();
    });
});
