/**
 * Dashboard lead drawer content.
 *
 * The page keeps the single `<Sheet>` (so the focus trap, Escape handling and
 * close animation have one owner); this component owns the lead-specific copy
 * and actions.
 */
import { Link } from '@inertiajs/react';
import { CalendarPlus } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { SheetDescription, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Tag } from '@/Components/ui/tag';
import { formatDateTime, formatWallClockDateTime } from '@/lib/date';
import { humanize } from '@/lib/utils';
import type { LeadCard, ScheduleLead } from '../types';

interface LeadDetailSheetProps {
    lead: LeadCard;
    canScheduleAppointment: boolean;
    canViewLead: boolean;
    onSchedule: (date: string, lead: ScheduleLead) => void;
    onClose: () => void;
}

export default function LeadDetailSheet({
    lead,
    canScheduleAppointment,
    canViewLead,
    onSchedule,
    onClose,
}: LeadDetailSheetProps) {
    return (
        <>
            <SheetHeader>
                <SheetTitle className="text-lg font-bold text-surface-900 dark:text-surface-0">
                    {lead.full_name}
                </SheetTitle>
                <SheetDescription>
                    {humanize(lead.status)} · {humanize(lead.source)}
                </SheetDescription>
            </SheetHeader>

            <div className="mt-4 flex flex-col gap-3 px-4 text-sm text-surface-600 dark:text-surface-300">
                <p>
                    <span className="font-semibold text-surface-900 dark:text-surface-0">
                        Phone:
                    </span>{' '}
                    {lead.phone ?? '—'}
                </p>
                <p>
                    <span className="font-semibold text-surface-900 dark:text-surface-0">
                        Location:
                    </span>{' '}
                    {[lead.city, lead.state].filter(Boolean).join(', ') ||
                        humanize(lead.region ?? '—')}
                </p>
                <p>
                    <span className="font-semibold text-surface-900 dark:text-surface-0">
                        Created:
                    </span>{' '}
                    {formatDateTime(lead.created_at)}
                </p>
                {lead.inspection_at !== null && (
                    <p>
                        <span className="font-semibold text-surface-900 dark:text-surface-0">
                            Inspection:
                        </span>{' '}
                        {formatWallClockDateTime(lead.inspection_at)}
                    </p>
                )}
                {lead.is_emergency && <Tag variant="destructive">Emergency</Tag>}
            </div>

            <div className="mt-6 flex flex-col gap-2 px-4">
                {canScheduleAppointment && (
                    <Button
                        className="w-full gap-2"
                        onClick={() => {
                            onSchedule('', {
                                uuid: lead.uuid,
                                name: lead.full_name,
                                phone: lead.phone,
                                region: lead.region,
                            });
                            onClose();
                        }}
                    >
                        <CalendarPlus className="size-4" aria-hidden="true" />
                        Schedule visit
                    </Button>
                )}
                {canViewLead && (
                    <Button
                        render={<Link href={`/leads/${lead.uuid}`} />}
                        nativeButton={false}
                        variant="outline"
                        className="w-full"
                    >
                        Open lead
                    </Button>
                )}
            </div>
        </>
    );
}
