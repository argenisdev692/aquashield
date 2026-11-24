/**
 * CompanyData/Edit — the company profile, as a tabbed profile sheet.
 *
 * One Inertia form owns every text field; the tabs are keep-mounted panels over
 * that single form, so switching sections never drops a draft and one save
 * commits the record atomically. Brand assets are the deliberate exception:
 * they upload immediately through the module hook and never submit the form.
 *
 * The page is read-only without `canUpdate`; the unlock pencils on the derived
 * address fields are capability-gated too, never role-inferred in the browser.
 */
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Head } from '@inertiajs/react';
import { Building2, Image as ImageIcon, Landmark, Lock, MapPin, Share2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import AppButton from '@/Components/Forms/AppButton';
import FormErrorSummary from '@/Components/Forms/FormErrorSummary';
import { Button } from '@/Components/ui/button';
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/Components/ui/tabs';
import { useZodForm } from '@/lib/forms';
import { cn } from '@/lib/utils';
import {
    companyProfileSchema,
    toCompanyProfileForm,
} from '@/Modules/CompanyData/schemas/companyProfile';
import type { CompanyProfileForm } from '@/Modules/CompanyData/schemas/companyProfile';
import { useCompanyAssets } from '@/Modules/CompanyData/hooks/useCompanyAssets';
import CompanyAddressPanel from './components/CompanyAddressPanel';
import CompanyBillingPanel from './components/CompanyBillingPanel';
import CompanyBrandingPanel from './components/CompanyBrandingPanel';
import CompanyDetailsPanel from './components/CompanyDetailsPanel';
import CompanyHeader from './components/CompanyHeader';
import CompanySocialPanel from './components/CompanySocialPanel';

interface CompanyDataEditProps {
    company: Modules.CompanyData.Data.CompanyDataDto;
    assets: Modules.CompanyData.Data.CompanyAssetsDto;
    canUpdate: boolean;
    googleMapsApiKey: string;
}

type TabValue = 'details' | 'address' | 'billing' | 'social' | 'branding';

const TAB_VALUES: readonly TabValue[] = ['details', 'address', 'billing', 'social', 'branding'];

const isTabValue = (value: string): value is TabValue =>
    TAB_VALUES.some((candidate) => candidate === value);

const tabFromHash = (): TabValue => {
    if (typeof window === 'undefined') {
        return 'details';
    }

    const raw = window.location.hash.replace('#', '');

    return isTabValue(raw) ? raw : 'details';
};

/** Which tab owns a field, so an error in a hidden panel can be reached. */
const FIELD_TAB: Readonly<Record<string, TabValue>> = {
    name: 'details',
    company_name: 'details',
    email: 'details',
    phone: 'details',
    website: 'details',
    description: 'details',

    address: 'address',
    address_2: 'address',
    zip_code: 'address',
    city: 'address',
    state: 'address',
    country: 'address',
    country_code: 'address',
    latitude: 'address',
    longitude: 'address',

    nif_nipc: 'billing',
    nie: 'billing',
    bank_beneficiary: 'billing',
    bank_iban: 'billing',
    bank_bic: 'billing',
    bank_name: 'billing',
    invoice_notes: 'billing',

    facebook_link: 'social',
    instagram_link: 'social',
    linkedin_link: 'social',
    twitter_link: 'social',
    tiktok_link: 'social',
};

const FIELD_LABELS: Readonly<Record<string, string>> = {
    name: 'Contact name',
    company_name: 'Legal company name',
    email: 'Contact email',
    phone: 'Phone',
    website: 'Website',
    description: 'Description',
    address: 'Street address',
    address_2: 'Address line 2',
    zip_code: 'ZIP code',
    city: 'City',
    state: 'State',
    country: 'Country',
    nif_nipc: 'NIF / NIPC',
    nie: 'NIE',
    bank_beneficiary: 'Beneficiary',
    bank_iban: 'IBAN',
    bank_bic: 'BIC / SWIFT',
    bank_name: 'Bank name',
    invoice_notes: 'Invoice notes',
    facebook_link: 'Facebook',
    instagram_link: 'Instagram',
    linkedin_link: 'LinkedIn',
    twitter_link: 'X / Twitter',
    tiktok_link: 'TikTok',
};

export default function CompanyDataEdit({
    company,
    assets,
    canUpdate,
    googleMapsApiKey,
}: CompanyDataEditProps) {
    const [tab, setTab] = useState<TabValue>(tabFromHash);
    const [focusTarget, setFocusTarget] = useState<string | null>(null);

    const { form, errors, submit, touch } = useZodForm({
        initial: toCompanyProfileForm(company),
        schema: companyProfileSchema,
    });

    /*
     * The page owns the asset controller so the header preview and the branding
     * panel read the same URLs after an upload. The hook still owns the request.
     */
    const companyAssets = useCompanyAssets(assets);

    useEffect(() => {
        const syncFromHash = (): void => setTab(tabFromHash());
        window.addEventListener('hashchange', syncFromHash);

        return () => window.removeEventListener('hashchange', syncFromHash);
    }, []);

    /*
     * Switch to the owning panel first, then focus its control on the next
     * commit. With keepMounted panels the input exists either way, but focusing
     * a hidden one is a no-op the user cannot see.
     */
    useEffect(() => {
        if (focusTarget === null) {
            return;
        }

        document.querySelector<HTMLElement>(`[name="${focusTarget}"]`)?.focus();
        setFocusTarget(null);
    }, [focusTarget]);

    const changeTab = (next: unknown): void => {
        if (typeof next !== 'string' || !isTabValue(next)) {
            return;
        }

        setTab(next);
        window.history.replaceState(
            window.history.state,
            '',
            `${window.location.pathname}${window.location.search}#${next}`,
        );
    };

    const focusField = (field: string): void => {
        setTab(FIELD_TAB[field] ?? 'details');
        setFocusTarget(field);
    };

    const readOnly = !canUpdate;

    const onSubmit = (event: FormEvent<HTMLFormElement>): void => {
        event.preventDefault();

        submit(() => {
            form.put('/company-data', {
                preserveScroll: true,
                onSuccess: () => form.setDefaults(),
            });
        });
    };

    return (
        <AppLayout title="Company profile">
            <Head title="Company profile" />

            <div className="mx-auto w-full max-w-6xl space-y-6">
                <CompanyHeader
                    company={company}
                    assets={companyAssets.assets}
                    canUpdate={canUpdate}
                />

                {readOnly && (
                    <div
                        role="status"
                        className="flex items-start gap-2 rounded-xl border border-aq-warning/40 bg-aq-warning/10 px-4 py-3 text-sm text-surface-800 dark:text-surface-100"
                    >
                        <Lock className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        <p>
                            <span className="font-medium">View-only access.</span> Company details
                            and brand assets are managed by a super admin. Every section stays
                            available for reading.
                        </p>
                    </div>
                )}

                <form onSubmit={onSubmit} className="flex flex-col gap-6" noValidate>
                    <FormErrorSummary
                        errors={errors}
                        labels={FIELD_LABELS}
                        onFieldFocus={focusField}
                    />

                    <Tabs value={tab} onValueChange={changeTab}>
                        <TabsList
                            aria-label="Company profile sections"
                            className="rounded-xl border border-aq-shell-line bg-surface-0 px-1 dark:bg-surface-900"
                        >
                            <TabsTab value="details">
                                <Building2 className="size-3.5" aria-hidden="true" />
                                Details
                            </TabsTab>

                            <TabsTab value="address">
                                <MapPin className="size-3.5" aria-hidden="true" />
                                Address
                            </TabsTab>

                            <TabsTab value="billing">
                                <Landmark className="size-3.5" aria-hidden="true" />
                                Billing
                            </TabsTab>

                            <TabsTab value="social">
                                <Share2 className="size-3.5" aria-hidden="true" />
                                Social
                            </TabsTab>

                            <TabsTab value="branding">
                                <ImageIcon className="size-3.5" aria-hidden="true" />
                                Branding
                            </TabsTab>
                        </TabsList>

                        <TabsPanel value="details" padded={false} keepMounted className="pt-6">
                            <div className="rounded-2xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm sm:p-6 dark:bg-surface-900">
                                <CompanyDetailsPanel
                                    form={form}
                                    errors={errors}
                                    onBlur={touch}
                                    readOnly={readOnly}
                                />
                            </div>
                        </TabsPanel>

                        <TabsPanel value="address" padded={false} keepMounted className="pt-6">
                            <div className="rounded-2xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm sm:p-6 dark:bg-surface-900">
                                <CompanyAddressPanel
                                    apiKey={googleMapsApiKey}
                                    form={form}
                                    errors={errors}
                                    onBlur={touch}
                                    canUpdate={canUpdate}
                                />
                            </div>
                        </TabsPanel>

                        <TabsPanel value="billing" padded={false} keepMounted className="pt-6">
                            <div className="rounded-2xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm sm:p-6 dark:bg-surface-900">
                                <CompanyBillingPanel
                                    form={form}
                                    errors={errors}
                                    onBlur={touch}
                                    readOnly={readOnly}
                                />
                            </div>
                        </TabsPanel>

                        <TabsPanel value="social" padded={false} keepMounted className="pt-6">
                            <div className="rounded-2xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm sm:p-6 dark:bg-surface-900">
                                <CompanySocialPanel
                                    form={form}
                                    errors={errors}
                                    onBlur={touch}
                                    readOnly={readOnly}
                                />
                            </div>
                        </TabsPanel>

                        <TabsPanel value="branding" padded={false} keepMounted className="pt-6">
                            <div className="rounded-2xl border border-aq-shell-line bg-surface-0 p-5 shadow-sm sm:p-6 dark:bg-surface-900">
                                <CompanyBrandingPanel
                                    controller={companyAssets}
                                    canUpdate={canUpdate}
                                />
                            </div>
                        </TabsPanel>
                    </Tabs>

                    {canUpdate && (
                        /*
                         * `z-[70]`: the shell's cursor orb is a fixed glow at
                         * z-60, so the action bar has to sit above it or the
                         * orb paints a moving blob across the bar.
                         */
                        <div className="sticky bottom-0 z-[70] flex flex-col gap-3 rounded-2xl border border-aq-shell-line bg-surface-0/95 px-4 py-3 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between dark:bg-surface-900/95">
                            <p
                                aria-live="polite"
                                className="flex items-center gap-2 text-sm text-surface-600 dark:text-surface-300"
                            >
                                <span
                                    className={cn(
                                        'size-2 rounded-full',
                                        form.isDirty ? 'bg-aq-warning' : 'bg-aq-success',
                                    )}
                                    aria-hidden="true"
                                />
                                {form.processing
                                    ? 'Saving changes…'
                                    : form.isDirty
                                      ? 'Unsaved changes'
                                      : 'All changes saved'}
                            </p>

                            <div className="flex flex-col gap-2 sm:flex-row">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={!form.isDirty || form.processing}
                                    onClick={() => form.reset()}
                                >
                                    Discard
                                </Button>

                                <AppButton
                                    type="submit"
                                    loading={form.processing}
                                    disabled={!form.isDirty}
                                >
                                    Save changes
                                </AppButton>
                            </div>
                        </div>
                    )}
                </form>
            </div>
        </AppLayout>
    );
}
