/**
 * AvailabilityMonthGrid — one resolved month, rendered from the server feed.
 *
 * Every cell is a button only when the viewer can change that day; otherwise
 * it is the same card as a non-interactive element, so a read-only month never
 * shows a dead control. The cell's accessible name carries the date, the state
 * and the reason, because colour alone is not a signal.
 */
import { CalendarCheck2, CalendarX2 } from 'lucide-react';
import { Tag } from '@/Components/ui/tag';
import { formatWindows, type AvailabilityDay } from '@/lib/availability';
import { cn } from '@/lib/utils';
import { WEEKDAYS, buildWeeks, sourceLabel, spokenDate, type CalendarWeek } from './calendarMonth';

type CalendarDay = AvailabilityDay;

interface DayCardProps {
    day: CalendarDay;
    today: string;
    interactive: boolean;
    onSelect: (date: string) => void;
}

function DayCard({ day, today, interactive, onSelect }: DayCardProps) {
    const isToday = day.date === today;
    const dayNumber = Number(day.date.slice(8, 10));
    const detail = day.is_open
        ? formatWindows(day.windows)
        : (day.reason ?? sourceLabel(day.source));
    const stateLabel = day.is_open
        ? `Open${day.windows.length > 0 ? ` ${formatWindows(day.windows)}` : ''}`
        : `Closed${day.reason === null ? '' : ` — ${day.reason}`}`;
    const label = `${spokenDate(day.date)} — ${stateLabel}${interactive ? '. Add or change this day.' : ''}`;

    const className = cn(
        'flex h-full min-h-20 w-full flex-col gap-1 rounded-lg border p-2 text-left transition-colors duration-150 motion-reduce:transition-none',
        day.is_open ? 'border-aq-success/30 bg-aq-success/5' : 'border-aq-danger/25 bg-aq-danger/5',
        interactive
            ? 'hover:border-aq-accent/60 hover:bg-surface-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus dark:hover:bg-surface-800'
            : undefined,
        isToday ? 'ring-1 ring-aq-accent' : undefined,
    );

    const body = (
        <>
            <span className="flex items-center justify-between gap-1">
                <span className="text-sm font-semibold tabular-nums text-surface-900 dark:text-surface-0">
                    {dayNumber}
                </span>
                {day.is_open ? (
                    <CalendarCheck2 className="size-3.5 text-aq-success" aria-hidden="true" />
                ) : (
                    <CalendarX2 className="size-3.5 text-aq-danger" aria-hidden="true" />
                )}
            </span>
            <Tag variant={day.is_open ? 'success' : 'destructive'}>
                {day.is_open ? 'Open' : 'Closed'}
            </Tag>
            <span className="line-clamp-2 text-xs leading-tight text-surface-600 dark:text-surface-300">
                {detail}
            </span>
        </>
    );

    if (!interactive) {
        return (
            <div className={className} aria-current={isToday ? 'date' : undefined}>
                <span className="sr-only">{spokenDate(day.date)} — </span>
                {body}
            </div>
        );
    }

    return (
        <button
            type="button"
            onClick={() => onSelect(day.date)}
            aria-label={label}
            aria-current={isToday ? 'date' : undefined}
            className={className}
        >
            {body}
        </button>
    );
}

interface AvailabilityMonthGridProps {
    days: readonly CalendarDay[];
    today: string;
    canEdit: boolean;
    /**
     * Per-day override. A create-only editor must not open the create form for
     * a day that already has an exception: the save is guaranteed to conflict.
     */
    canEditDay?: (day: CalendarDay) => boolean;
    onSelectDate: (date: string) => void;
}

export default function AvailabilityMonthGrid({
    days,
    today,
    canEdit,
    canEditDay,
    onSelectDate,
}: AvailabilityMonthGridProps) {
    const weeks = buildWeeks(days);

    if (weeks.length === 0) {
        return (
            <p className="rounded-lg border border-surface-200 px-4 py-10 text-center text-sm text-surface-500 dark:border-surface-800 dark:text-surface-300">
                No days in this window.
            </p>
        );
    }

    return (
        <div className="min-w-0 overflow-x-auto">
            <table className="w-full min-w-[44rem] border-separate border-spacing-1">
                <caption className="sr-only">
                    Company availability for the selected month. Open and closed days, with the
                    reason and the weekly or exception source.
                </caption>
                <thead>
                    <tr>
                        {WEEKDAYS.map((weekday) => (
                            <th
                                key={weekday}
                                scope="col"
                                className="px-1 pb-1 text-left text-xs font-semibold text-surface-500 dark:text-surface-300"
                            >
                                {weekday}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {weeks.map((week: CalendarWeek) => (
                        <tr key={week[0]?.date ?? 'empty-week'}>
                            {week.map((day, cell) => (
                                <td key={day?.date ?? `empty-${cell}`} className="p-0 align-top">
                                    {day === null ? (
                                        <div className="min-h-20 rounded-lg border border-dashed border-surface-200 dark:border-surface-800" />
                                    ) : (
                                        <DayCard
                                            day={day}
                                            today={today}
                                            interactive={canEditDay?.(day) ?? canEdit}
                                            onSelect={onSelectDate}
                                        />
                                    )}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
