/**
 * CompanyBillingPanel — fiscal identity, bank details and invoice defaults.
 *
 * These fields are the ones printed on invoices and used for transfers, so the
 * hints mirror the server limits exactly. Nothing here is derived: every value
 * is typed (or left empty) by the capability holder.
 */
import type { InertiaForm } from '@inertiajs/react';
import { FileText, Fingerprint, Landmark } from 'lucide-react';
import FormSection from '@/Components/Forms/FormSection';
import TextareaField from '@/Components/Forms/TextareaField';
import TextField from '@/Components/Forms/TextField';
import type { CompanyProfileForm } from '@/Modules/CompanyData/schemas/companyProfile';

interface CompanyBillingPanelProps {
    form: Pick<InertiaForm<CompanyProfileForm>, 'data'>;
    errors: Partial<Record<string, string>>;
    onBlur: (field: keyof CompanyProfileForm) => void;
    readOnly: boolean;
    setField: (field: keyof CompanyProfileForm, value: string) => void;
}

export default function CompanyBillingPanel({
    form,
    errors,
    onBlur,
    readOnly,
    setField,
}: CompanyBillingPanelProps) {
    return (
        <div className="flex flex-col gap-6">
            <FormSection
                title="Fiscal identity"
                description="Tax identifiers that appear on invoices and receipts."
                icon={<Fingerprint className="size-4" aria-hidden="true" />}
            >
                <TextField
                    label="NIF / NIPC"
                    name="nif_nipc"
                    value={form.data.nif_nipc}
                    onChange={(value) => setField('nif_nipc', value)}
                    onBlur={() => onBlur('nif_nipc')}
                    error={errors.nif_nipc}
                    hint="Company tax number, up to 20 characters."
                    maxLength={20}
                    disabled={readOnly}
                />

                <TextField
                    label="NIE"
                    name="nie"
                    value={form.data.nie}
                    onChange={(value) => setField('nie', value)}
                    onBlur={() => onBlur('nie')}
                    error={errors.nie}
                    hint="Foreigner identification number, when applicable."
                    maxLength={20}
                    disabled={readOnly}
                />
            </FormSection>

            <FormSection
                title="Bank details"
                description="Used on invoices so customers can pay by transfer."
                icon={<Landmark className="size-4" aria-hidden="true" />}
                className="border-t border-aq-shell-line/70 pt-6"
            >
                <TextField
                    label="Beneficiary"
                    name="bank_beneficiary"
                    value={form.data.bank_beneficiary}
                    onChange={(value) => setField('bank_beneficiary', value)}
                    onBlur={() => onBlur('bank_beneficiary')}
                    error={errors.bank_beneficiary}
                    hint="The account holder exactly as the bank knows it."
                    maxLength={255}
                    disabled={readOnly}
                />

                <TextField
                    label="Bank name"
                    name="bank_name"
                    value={form.data.bank_name}
                    onChange={(value) => setField('bank_name', value)}
                    onBlur={() => onBlur('bank_name')}
                    error={errors.bank_name}
                    hint="The institution, not the branch."
                    maxLength={255}
                    disabled={readOnly}
                />

                <TextField
                    label="IBAN"
                    name="bank_iban"
                    value={form.data.bank_iban}
                    onChange={(value) => setField('bank_iban', value)}
                    onBlur={() => onBlur('bank_iban')}
                    error={errors.bank_iban}
                    hint="Up to 34 characters, no spaces required."
                    maxLength={34}
                    autocomplete="off"
                    spellCheck={false}
                    disabled={readOnly}
                />

                <TextField
                    label="BIC / SWIFT"
                    name="bank_bic"
                    value={form.data.bank_bic}
                    onChange={(value) => setField('bank_bic', value)}
                    onBlur={() => onBlur('bank_bic')}
                    error={errors.bank_bic}
                    hint="8 or 11 characters."
                    maxLength={11}
                    autocomplete="off"
                    spellCheck={false}
                    disabled={readOnly}
                />
            </FormSection>

            <FormSection
                title="Invoice defaults"
                description="Standing notes printed at the bottom of every invoice."
                icon={<FileText className="size-4" aria-hidden="true" />}
                className="border-t border-aq-shell-line/70 pt-6"
                columns={1}
            >
                <TextareaField
                    label="Invoice notes"
                    name="invoice_notes"
                    value={form.data.invoice_notes}
                    onChange={(value) => setField('invoice_notes', value)}
                    onBlur={() => onBlur('invoice_notes')}
                    error={errors.invoice_notes}
                    hint="Payment terms, thank-you line or legal note. Leave empty to print nothing."
                    rows={4}
                    disabled={readOnly}
                />
            </FormSection>
        </div>
    );
}
