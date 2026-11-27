/**
 * CompanySocialPanel — the public profile links.
 *
 * One URL per network, each optional. The server validates every value as a
 * full URL, so the fields carry `type="url"` and the same hint the server
 * enforces. Empty means "not published", never a broken link.
 */
import type { InertiaForm } from '@inertiajs/react';
import { Link2, Share2 } from 'lucide-react';
import FormSection from '@/Components/Forms/FormSection';
import TextField from '@/Components/Forms/TextField';
import type { CompanyProfileForm } from '@/Modules/CompanyData/schemas/companyProfile';

interface CompanySocialPanelProps {
    form: Pick<InertiaForm<CompanyProfileForm>, 'data'>;
    errors: Partial<Record<string, string>>;
    onBlur: (field: keyof CompanyProfileForm) => void;
    readOnly: boolean;
    setField: (field: keyof CompanyProfileForm, value: string) => void;
}

const SOCIAL_FIELDS: readonly {
    field: keyof CompanyProfileForm;
    label: string;
    placeholder: string;
}[] = [
    { field: 'facebook_link', label: 'Facebook', placeholder: 'https://facebook.com/aquashield' },
    {
        field: 'instagram_link',
        label: 'Instagram',
        placeholder: 'https://instagram.com/aquashield',
    },
    {
        field: 'linkedin_link',
        label: 'LinkedIn',
        placeholder: 'https://linkedin.com/company/aquashield',
    },
    { field: 'twitter_link', label: 'X / Twitter', placeholder: 'https://x.com/aquashield' },
    { field: 'tiktok_link', label: 'TikTok', placeholder: 'https://tiktok.com/@aquashield' },
];

export default function CompanySocialPanel({
    form,
    errors,
    onBlur,
    readOnly,
    setField,
}: CompanySocialPanelProps) {
    return (
        <div className="flex flex-col gap-6">
            <FormSection
                title="Social profiles"
                description="Links shown on the public company profile and email footers."
                icon={<Share2 className="size-4" aria-hidden="true" />}
            >
                {SOCIAL_FIELDS.map(({ field, label, placeholder }) => (
                    <TextField
                        key={field}
                        label={label}
                        name={field}
                        type="url"
                        value={form.data[field]}
                        onChange={(value) => setField(field, value)}
                        onBlur={() => onBlur(field)}
                        error={errors[field]}
                        hint="Full URL, including https://. Leave empty to hide the network."
                        placeholder={placeholder}
                        autocomplete="url"
                        inputMode="url"
                        disabled={readOnly}
                    />
                ))}
            </FormSection>

            <div className="flex items-start gap-2 rounded-lg border border-aq-shell-line bg-surface-50/70 px-3 py-2.5 text-xs text-surface-600 dark:bg-surface-950/30 dark:text-surface-300">
                <Link2 className="mt-0.5 size-3.5 shrink-0 text-aq-accent" aria-hidden="true" />
                <p>
                    These are profile links, not embeds. The public site renders them as plain
                    anchors, so a wrong handle is visible immediately.
                </p>
            </div>
        </div>
    );
}
