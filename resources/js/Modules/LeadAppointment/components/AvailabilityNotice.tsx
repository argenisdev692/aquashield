/**
 * AvailabilityNotice — the operator's day check inside the appointment form.
 *
 * It reads the same resolved feed as the availability month, for a bounded
 * 15-day window. That window is keyed by its own bounds (`from`, `to`,
 * `region`), so each date picked in the form is a separate cache entry rather
 * than a slice of the month panel's entry — deliberate: the lookahead must be
 * able to cross a month boundary, and the month key cannot answer a window
 * that starts mid-month.
 *
 * When the day is closed it offers the next open days as one-click
 * replacements, which is the whole point of recording a crew closure.
 *
 * The notice is a courtesy, not a control: the scheduling action still
 * re-checks the window, the past-date rule and the daily cap on the server.
 */
import { usePage } from '@inertiajs/react';
import { CalendarCheck2, CalendarX2 } from 'lucide-react';
import { Skeleton } from '@/Components/ui/skeleton';
import { formatWindows, type AvailabilityDay } from '@/lib/availability';
import { formatIsoDate, isIsoDate, parseIsoDate, toIsoDate } from '@/lib/date';
import { useAvailabilityWindow } from '../hooks/useAvailabilityCalendar';

type CalendarDay = AvailabilityDay;

/**
 * Compile-time-checked against the generated union, so renaming the server
 * permission fails `tsc` instead of silently hiding the notice forever.
 */
const VIEW_ANY_AVAILABILITY_RULE: Shared.Domain.Enums.Permission = 'VIEW_ANY_AVAILABILITY_RULE';

const LOOKAHEAD_DAYS = 14;

interface AvailabilityNoticeProps {
    date: string;
    region: string | null;
    onPickDate: (date: string) => void;
}

const addDays = (isoDate: string, days: number): string => {
    const parsed = parseIsoDate(isoDate);

    if (parsed === undefined) {
        return isoDate;
    }

    return toIsoDate(
        new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate() + days, 12),
    );
};

export default function AvailabilityNotice({ date, region, onPickDate }: AvailabilityNoticeProps) {
    const page = usePage();
    const canView = page.props.authPermissions.includes(VIEW_ANY_AVAILABILITY_RULE);
    const validDate = isIsoDate(date);
    const to = validDate ? addDays(date, LOOKAHEAD_DAYS) : '';
    const { days, isPending, isError } = useAvailabilityWindow(
        date,
        to,
        region,
        canView && validDate,
    );

    if (!canView || !validDate) {
        return null;
    }

    if (isPending) {
        return <Skeleton className="h-9 w-full rounded-lg" />;
    }

    if (isError) {
        return (
            <p className="text-xs text-surface-500 dark:text-surface-300">
                The company calendar could not be loaded, so this day is unchecked here.
            </p>
        );
    }

    const selected = days.find((day) => day.date === date) ?? null;

    if (selected === null) {
        return null;
    }

    if (selected.is_open) {
        return (
            <p className="flex items-center gap-1.5 rounded-lg border border-aq-success/30 bg-aq-success/5 px-3 py-2 text-xs text-surface-700 dark:text-surface-200">
                <CalendarCheck2 className="size-3.5 shrink-0 text-aq-success" aria-hidden="true" />
                <span>
                    Open {formatWindows(selected.windows)}
                    {selected.reason === null ? '' : ` · ${selected.reason}`}
                </span>
            </p>
        );
    }

    const nextOpen: CalendarDay[] = [];

    for (const day of days) {
        if (day.is_open && day.date > date && nextOpen.length < 3) {
            nextOpen.push(day);
        }
    }

    return (
        <div className="rounded-lg border border-aq-danger/30 bg-aq-danger/5 px-3 py-2 text-xs">
            <p
                aria-live="polite"
                className="flex items-center gap-1.5 font-medium text-aq-danger-strong"
            >
                <CalendarX2 className="size-3.5 shrink-0" aria-hidden="true" />
                <span>Closed{selected.reason === null ? '' : ` — ${selected.reason}`}</span>
            </p>

            {nextOpen.length > 0 && (
                <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                    <span className="text-surface-600 dark:text-surface-300">Next open:</span>
                    {nextOpen.map((day) => (
                        <button
                            key={day.date}
                            type="button"
                            onClick={() => onPickDate(day.date)}
                            className="rounded-full border border-surface-300 px-2 py-0.5 font-medium text-surface-800 transition-colors duration-150 hover:border-aq-accent hover:text-aq-accent focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none dark:border-surface-600 dark:text-surface-100"
                        >
                            {formatIsoDate(day.date, 'en-US', {
                                weekday: 'short',
                                month: 'short',
                                day: 'numeric',
                            })}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
