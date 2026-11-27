/**
 * CompanyHeader — the profile-style identity block shared by every tab.
 *
 * It reads only the generated DTO and the resolved asset URLs: the brand mark
 * is the same one the public site renders, and the status tags come from the
 * server capability (`canUpdate`) instead of a role guessed in the browser.
 */
import { BadgeCheck, Building2, Globe, Mail, MapPin } from 'lucide-react';
import { Tag } from '@/Components/ui/tag';

type CompanyData = Modules.CompanyData.Data.CompanyDataDto;
type CompanyAssets = Modules.CompanyData.Data.CompanyAssetsDto;

interface CompanyHeaderProps {
    company: CompanyData;
    assets: CompanyAssets;
    canUpdate: boolean;
}

const nonEmpty = (value: string | null): value is string => value !== null && value.trim() !== '';

export default function CompanyHeader({ company, assets, canUpdate }: CompanyHeaderProps) {
    const location = [company.city, company.state, company.country].filter(nonEmpty).join(', ');

    const verifiedAddress = company.latitude !== null && company.longitude !== null;

    return (
        <section className="overflow-hidden rounded-2xl border border-aq-shell-line bg-surface-0 shadow-sm dark:bg-surface-900">
            <div className="flex flex-col gap-5 p-5 sm:flex-row sm:items-start sm:p-6">
                <div className="grid size-16 shrink-0 place-items-center overflow-hidden rounded-2xl border border-aq-shell-line bg-surface-50 dark:bg-surface-950/60">
                    {assets.logo_url !== null ? (
                        <img
                            src={assets.logo_url}
                            alt={`${company.company_name} logo`}
                            className="size-full object-contain p-2"
                        />
                    ) : (
                        <Building2 className="size-7 text-aq-accent" aria-hidden="true" />
                    )}
                </div>

                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="min-w-0 truncate font-heading text-xl font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                            {company.company_name}
                        </h2>

                        <Tag variant={canUpdate ? 'success' : 'outline'}>
                            {canUpdate ? 'Can edit' : 'View only'}
                        </Tag>

                        <Tag variant={verifiedAddress ? 'success' : 'outline'}>
                            {verifiedAddress ? 'Verified address' : 'Manual address'}
                        </Tag>
                    </div>

                    {company.name !== null && company.name !== '' && (
                        <p className="mt-1 text-sm text-surface-600 dark:text-surface-300">
                            {company.name}
                        </p>
                    )}

                    <dl className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                        {company.email !== null && company.email !== '' && (
                            <div className="flex min-w-0 items-center gap-1.5">
                                <dt className="sr-only">Email</dt>
                                <Mail
                                    className="size-3.5 shrink-0 text-surface-500 dark:text-surface-300"
                                    aria-hidden="true"
                                />
                                <dd className="min-w-0 truncate">
                                    <a
                                        href={`mailto:${company.email}`}
                                        className="text-surface-700 underline-offset-2 transition-colors duration-150 hover:text-aq-accent hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none dark:text-surface-200"
                                    >
                                        {company.email}
                                    </a>
                                </dd>
                            </div>
                        )}

                        {company.website !== null && company.website !== '' && (
                            <div className="flex min-w-0 items-center gap-1.5">
                                <dt className="sr-only">Website</dt>
                                <Globe
                                    className="size-3.5 shrink-0 text-surface-500 dark:text-surface-300"
                                    aria-hidden="true"
                                />
                                <dd className="min-w-0 truncate">
                                    <a
                                        href={company.website}
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        className="text-surface-700 underline-offset-2 transition-colors duration-150 hover:text-aq-accent hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-accent motion-reduce:transition-none dark:text-surface-200"
                                    >
                                        {company.website}
                                    </a>
                                </dd>
                            </div>
                        )}

                        {location !== '' && (
                            <div className="flex min-w-0 items-center gap-1.5">
                                <dt className="sr-only">Location</dt>
                                <MapPin
                                    className="size-3.5 shrink-0 text-surface-500 dark:text-surface-300"
                                    aria-hidden="true"
                                />
                                <dd className="min-w-0 truncate text-surface-600 dark:text-surface-300">
                                    {location}
                                </dd>
                            </div>
                        )}
                    </dl>

                    {verifiedAddress && (
                        <p className="mt-3 flex items-center gap-1.5 text-xs text-aq-success">
                            <BadgeCheck className="size-3.5" aria-hidden="true" />
                            Address confirmed with Google Places.
                        </p>
                    )}
                </div>
            </div>
        </section>
    );
}
