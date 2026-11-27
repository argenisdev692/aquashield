// @vitest-environment jsdom
import { cleanup, fireEvent, render } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentProps, ReactNode } from 'react';

const { form, put, useNavigationGuard, useUnsavedChangesWarning } = vi.hoisted(() => {
    const put = vi.fn<(url: string, options: unknown) => void>();
    const useNavigationGuard = vi.fn<(isDirty: boolean) => unknown>();
    const useUnsavedChangesWarning = vi.fn<(isDirty: boolean) => void>();

    return {
        put,
        useNavigationGuard,
        useUnsavedChangesWarning,
        form: {
            put,
            processing: false,
            isDirty: true,
            reset: vi.fn<() => void>(),
        },
    };
});

vi.mock('@inertiajs/react', () => ({ Head: () => null }));
vi.mock('@/lib/leave-guard', () => ({
    useNavigationGuard: (isDirty: boolean) => useNavigationGuard(isDirty),
}));
vi.mock('@/Components/DataTable/CrudDialog', () => ({ default: () => null }));
vi.mock('@/Layouts/AppLayout', () => ({
    default: ({ children }: { children?: ReactNode }) => <div>{children}</div>,
}));
vi.mock('@/lib/forms', () => ({
    focusFieldByName: vi.fn<(field: string) => void>(),
    useUnsavedChangesWarning: (isDirty: boolean) => useUnsavedChangesWarning(isDirty),
    useZodForm: () => ({
        form,
        errors: {},
        submit: (action: () => void) => action(),
        submitAttempts: 0,
        touch: vi.fn<(field: string) => void>(),
        setField: vi.fn<(field: string, value: string) => void>(),
        setFields: vi.fn<(patch: Record<string, string>) => void>(),
    }),
}));
vi.mock('@/Modules/CompanyData/hooks/useCompanyAssets', () => ({
    useCompanyAssets: () => ({
        assets: { logo_url: null, logo_white_url: null, mark_url: null, signature_url: null },
        pending: null,
        error: null,
        upload: vi.fn<(...args: unknown[]) => Promise<boolean>>(),
        remove: vi.fn<(...args: unknown[]) => Promise<boolean>>(),
    }),
}));
vi.mock('@/Components/Forms/AppButton', () => ({
    default: ({ children, ...props }: ComponentProps<'button'>) => (
        <button {...props}>{children}</button>
    ),
}));
vi.mock('@/Components/Forms/FormErrorSummary', () => ({ default: () => null }));
vi.mock('@/Components/ui/button', () => ({
    Button: ({ children, ...props }: ComponentProps<'button'>) => (
        <button {...props}>{children}</button>
    ),
}));
vi.mock('@/Components/ui/tabs', () => {
    const Panel = ({ children }: { children?: ReactNode }) => <div>{children}</div>;

    return {
        Tabs: Panel,
        TabsList: Panel,
        TabsTab: Panel,
        TabsPanel: Panel,
    };
});
vi.mock('./components/CompanyHeader', () => ({ default: () => null }));
vi.mock('./components/CompanyAddressPanel', () => ({ default: () => null }));
vi.mock('./components/CompanyBillingPanel', () => ({ default: () => null }));
vi.mock('./components/CompanyBrandingPanel', () => ({ default: () => null }));
vi.mock('./components/CompanyDetailsPanel', () => ({ default: () => null }));
vi.mock('./components/CompanySocialPanel', () => ({ default: () => null }));

import CompanyDataEdit from './Edit';

const company: Modules.CompanyData.Data.CompanyDataDto = {
    uuid: 'company-uuid',
    updated_at: '2026-11-27T12:00:00Z',
    name: 'Contact',
    company_name: 'AquaShield',
    email: 'contact@example.com',
    description: null,
    phone: null,
    nif_nipc: null,
    nie: null,
    bank_beneficiary: null,
    bank_iban: null,
    bank_bic: null,
    bank_name: null,
    invoice_notes: null,
    address: null,
    address_2: null,
    zip_code: null,
    city: null,
    state: null,
    country: null,
    country_code: null,
    latitude: null,
    longitude: null,
    website: null,
    facebook_link: null,
    instagram_link: null,
    linkedin_link: null,
    twitter_link: null,
    tiktok_link: null,
    logo_path: null,
    logo_white_path: null,
    mark_path: null,
};

const props = {
    company,
    assets: { logo_url: null, logo_white_url: null, mark_url: null, signature_url: null },
    canUpdate: true,
    googleMapsApiKey: '',
};

afterEach(cleanup);

beforeEach(() => {
    put.mockClear();
    useNavigationGuard.mockClear().mockReturnValue({
        pendingUrl: null,
        confirmLeave: vi.fn<() => void>(),
        cancelLeave: vi.fn<() => void>(),
    });
    useUnsavedChangesWarning.mockClear();
    form.processing = false;
});

describe('CompanyDataEdit save lifecycle', () => {
    it('registers unsaved-change guards for a dirty profile', () => {
        render(<CompanyDataEdit {...props} />);

        expect(useUnsavedChangesWarning).toHaveBeenCalledWith(true);
        expect(useNavigationGuard).toHaveBeenCalledWith(true);
    });

    it('remounts from server props after success while preserving drafts on validation errors', () => {
        const { container } = render(<CompanyDataEdit {...props} />);
        const formElement = container.querySelector('form');

        expect(formElement).not.toBeNull();
        fireEvent.submit(formElement as HTMLFormElement);

        expect(put).toHaveBeenCalledWith('/company-data', {
            preserveScroll: true,
            preserveState: 'errors',
        });
    });

    it('makes the whole form inert while the save is in flight', () => {
        const { container, rerender } = render(<CompanyDataEdit {...props} />);
        const formElement = container.querySelector('form');

        form.processing = true;
        rerender(<CompanyDataEdit {...props} />);

        expect(formElement?.hasAttribute('inert')).toBe(true);
    });
});
