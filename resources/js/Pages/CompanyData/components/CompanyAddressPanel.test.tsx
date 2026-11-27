// @vitest-environment jsdom
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { CompanyProfileForm } from '@/Modules/CompanyData/schemas/companyProfile';
import CompanyAddressPanel from './CompanyAddressPanel';

const data: CompanyProfileForm = {
    updated_at: '2026-11-27T12:00:00Z',
    name: 'Contact',
    company_name: 'AquaShield',
    email: 'contact@example.com',
    phone: '',
    description: '',
    nif_nipc: '',
    nie: '',
    bank_beneficiary: '',
    bank_iban: '',
    bank_bic: '',
    bank_name: '',
    invoice_notes: '',
    address: '123 Main St',
    address_2: '',
    zip_code: '75201',
    city: 'Dallas',
    state: 'TX',
    country: 'United States',
    country_code: 'US',
    latitude: '32.7767',
    longitude: '-96.797',
    website: '',
    facebook_link: '',
    instagram_link: '',
    linkedin_link: '',
    twitter_link: '',
    tiktok_link: '',
};

const setup = () => {
    const setFields = vi.fn<(patch: Partial<CompanyProfileForm>) => void>();
    const setField = vi.fn<(field: keyof CompanyProfileForm, value: string) => void>();

    render(
        <CompanyAddressPanel
            apiKey=""
            form={{ data }}
            errors={{}}
            onBlur={vi.fn<(field: keyof CompanyProfileForm) => void>()}
            canUpdate
            setField={setField}
            setFields={setFields}
        />,
    );

    return { setField, setFields };
};

afterEach(cleanup);

describe('CompanyAddressPanel field writes', () => {
    it('clears coordinates when a derived address component is edited manually', () => {
        const { setFields } = setup();

        fireEvent.click(screen.getByRole('button', { name: 'Edit city' }));
        fireEvent.change(screen.getByLabelText('City'), { target: { value: 'Austin' } });

        expect(setFields).toHaveBeenCalledWith({
            city: 'Austin',
            latitude: '',
            longitude: '',
        });
    });

    it('clears coordinates through the form kit when the street line changes', () => {
        const { setFields } = setup();

        fireEvent.change(screen.getByLabelText('Street address'), {
            target: { value: '456 New St' },
        });

        expect(setFields).toHaveBeenCalledWith({
            address: '456 New St',
            latitude: '',
            longitude: '',
        });
    });
});
