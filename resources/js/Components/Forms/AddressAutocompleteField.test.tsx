// @vitest-environment jsdom
import { act, cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useState } from 'react';
import type { AddressSelection, AddressSuggestion } from '@/lib/google-maps';

const { createAutocompleteSessionToken, fetchAddressSuggestions, toAddressSelection } = vi.hoisted(
    () => ({
        createAutocompleteSessionToken: vi.fn<() => Promise<unknown>>(),
        fetchAddressSuggestions: vi.fn<(...args: unknown[]) => Promise<AddressSuggestion[]>>(),
        toAddressSelection: vi.fn<(suggestion: AddressSuggestion) => Promise<AddressSelection>>(),
    }),
);

vi.mock('@/lib/google-maps', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@/lib/google-maps')>();

    return {
        ...actual,
        createAutocompleteSessionToken,
        fetchAddressSuggestions,
        toAddressSelection,
    };
});

import AddressAutocompleteField from './AddressAutocompleteField';

const suggestion = {
    id: 'place-1',
    main_text: '123 Main St',
    secondary_text: 'Dallas, TX',
    prediction: {} as google.maps.places.PlacePrediction,
} satisfies AddressSuggestion;

const selection: AddressSelection = {
    address: '123 Main St',
    formatted_address: '123 Main St, Dallas, TX 75201',
    zip_code: '75201',
    city: 'Dallas',
    state: 'TX',
    country: 'United States',
    latitude: 32.7767,
    longitude: -96.797,
};

function Harness({ onAddressSelect }: { onAddressSelect: (value: AddressSelection) => void }) {
    const [value, setValue] = useState('');

    return (
        <AddressAutocompleteField
            label="Street address"
            name="address"
            value={value}
            onChange={setValue}
            onAddressSelect={onAddressSelect}
            apiKey="test-key"
        />
    );
}

beforeEach(() => {
    createAutocompleteSessionToken.mockReset().mockResolvedValue({});
    fetchAddressSuggestions.mockReset().mockResolvedValue([suggestion]);
    toAddressSelection.mockReset();
});

afterEach(cleanup);

describe('AddressAutocompleteField selection races', () => {
    it('ignores a Place Details result after the user has typed a newer address', async () => {
        let resolveSelection: ((value: AddressSelection) => void) | undefined;
        toAddressSelection.mockImplementationOnce(
            () =>
                new Promise<AddressSelection>((resolve) => {
                    resolveSelection = resolve;
                }),
        );
        const onAddressSelect = vi.fn<(value: AddressSelection) => void>();

        render(<Harness onAddressSelect={onAddressSelect} />);

        const input = screen.getByRole('combobox', { name: 'Street address' });
        act(() => input.focus());
        fireEvent.change(input, { target: { value: '123 Main' } });
        fireEvent.click(await screen.findByRole('option', { name: /123 Main St/ }));
        fireEvent.change(input, { target: { value: '456 New St' } });

        await act(async () => {
            resolveSelection?.(selection);
            await Promise.resolve();
        });

        expect((input as HTMLInputElement).value).toBe('456 New St');
        expect(onAddressSelect).not.toHaveBeenCalled();
    });
});
