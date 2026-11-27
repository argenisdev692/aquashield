/**
 * CompanyDetailsPanel — identity, contact and public description.
 *
 * All values come from `companyProfileSchema`; the DTO is only read by
 * `toCompanyProfileForm` on the page. Every field is disabled over the same
 * `readOnly` flag, so a view-only visitor can read the record without being
 * able to produce a 403 on submit.
 */
import type { InertiaForm } from '@inertiajs/react';
import { Building2, Globe2 } from 'lucide-react';
import FormSection from '@/Components/Forms/FormSection';
import PhoneField from '@/Components/Forms/PhoneField';
import TextareaField from '@/Components/Forms/TextareaField';
import TextField from '@/Components/Forms/TextField';
import { isCountryCode } from '@/lib/phone';
import type { CompanyProfileForm } from '@/Modules/CompanyData/schemas/companyProfile';

interface CompanyDetailsPanelProps {
    form: Pick<InertiaForm<CompanyProfileForm>, 'data'>;
    errors: Partial<Record<string, string>>;
    onBlur: (field: keyof CompanyProfileForm) => void;
    readOnly: boolean;
    setField: (field: keyof CompanyProfileForm, value: string) => void;
}

export default function CompanyDetailsPanel({
    form,
    errors,
    onBlur,
    readOnly,
    setField,
}: CompanyDetailsPanelProps) {
    /*
     * The address country seeds the phone picker when the record has no phone
     * yet. A stored phone wins inside PhoneField, so a Portuguese number is
     * never displayed under the company's billing country.
     */
    const addressCountry = form.data.country_code.trim().toUpperCase();

    return (
        <div className="flex flex-col gap-6">
            <FormSection
                title="Identity"
                description="How the company is named in the workspace, on invoices and in email."
                icon={<Building2 className="size-4" aria-hidden="true" />}
            >
                <TextField
                    label="Contact name"
                    name="name"
                    value={form.data.name}
                    onChange={(value) => setField('name', value)}
                    onBlur={() => onBlur('name')}
                    error={errors.name}
                    hint="The person AquaShield correspondence is addressed to."
                    required
                    disabled={readOnly}
                />

                <TextField
                    label="Legal company name"
                    name="company_name"
                    value={form.data.company_name}
                    onChange={(value) => setField('company_name', value)}
                    onBlur={() => onBlur('company_name')}
                    error={errors.company_name}
                    hint="Printed on contracts, invoices and public pages."
                    required
                    disabled={readOnly}
                />

                <TextField
                    label="Contact email"
                    name="email"
                    type="email"
                    value={form.data.email}
                    onChange={(value) => setField('email', value)}
                    onBlur={() => onBlur('email')}
                    error={errors.email}
                    hint="Where customer replies and system notices are sent."
                    required
                    autocomplete="email"
                    inputMode="email"
                    disabled={readOnly}
                />

                <PhoneField
                    label="Phone"
                    name="phone"
                    value={form.data.phone}
                    onChange={(value) => setField('phone', value)}
                    onBlur={() => onBlur('phone')}
                    error={errors.phone}
                    hint="Pick the country, then type the number. It is stored in E.164."
                    country={isCountryCode(addressCountry) ? addressCountry : undefined}
                    disabled={readOnly}
                />
            </FormSection>

            <FormSection
                title="Public presence"
                description="The website and the short description customers see first."
                icon={<Globe2 className="size-4" aria-hidden="true" />}
                className="border-t border-aq-shell-line/70 pt-6"
            >
                <TextField
                    label="Website"
                    name="website"
                    type="url"
                    value={form.data.website}
                    onChange={(value) => setField('website', value)}
                    onBlur={() => onBlur('website')}
                    error={errors.website}
                    hint="Full URL, including https://."
                    placeholder="https://aquashieldrestorationusa.com"
                    autocomplete="url"
                    inputMode="url"
                    disabled={readOnly}
                />

                <div className="sm:col-span-2">
                    <TextareaField
                        label="Description"
                        name="description"
                        value={form.data.description}
                        onChange={(value) => setField('description', value)}
                        onBlur={() => onBlur('description')}
                        error={errors.description}
                        hint="A short factual paragraph about the company and its services. It feeds the public company profile and email footers."
                        rows={4}
                        disabled={readOnly}
                    />
                </div>
            </FormSection>
        </div>
    );
}
