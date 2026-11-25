/**
 * The month grid's data boundary. It uses `useSuspenseQuery` because a month
 * with no resolved days has nothing to say — the page supplies the skeleton.
 * The visible month lives above this component so the toolbar never suspends
 * with the grid.
 */
import { Skeleton } from '@/Components/ui/skeleton';
import { cn } from '@/lib/utils';
import { useAvailabilityCalendar } from '@/Modules/LeadAppointment/hooks/useAvailabilityCalendar';
import AvailabilityMonthGrid from './AvailabilityMonthGrid';

interface AvailabilityCalendarPanelProps {
    from: string;
    to: string;
    today: string;
    canEdit: boolean;
    onSelectDate: (date: string) => void;
}

export default function AvailabilityCalendarPanel({
    from,
    to,
    today,
    canEdit,
    onSelectDate,
}: AvailabilityCalendarPanelProps) {
    const { days, isFetching } = useAvailabilityCalendar(from, to);

    return (
        <div
            aria-busy={isFetching}
            className={cn(
                'transition-opacity duration-150 motion-reduce:transition-none',
                isFetching ? 'opacity-70' : undefined,
            )}
        >
            {/*
             * The month label in the page toolbar is the single live region for
             * this panel: it announces the new month name (and "loading") when
             * the window changes. A second `role="status"` here said the same
             * thing one tick later, so a month change was read out twice.
             * `aria-busy` still tells assistive tech that this subtree is
             * refreshing.
             */}

            <AvailabilityMonthGrid
                days={days}
                today={today}
                canEdit={canEdit}
                onSelectDate={onSelectDate}
            />
        </div>
    );
}

/** Five week rows, the same 7-column shape the real grid renders. */
export function AvailabilityMonthSkeleton() {
    return (
        <div className="grid grid-cols-7 gap-1" aria-hidden="true">
            {Array.from({ length: 35 }, (_, index) => (
                <Skeleton key={index} className="h-20 rounded-lg" />
            ))}
        </div>
    );
}
