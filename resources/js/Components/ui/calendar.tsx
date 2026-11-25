/**
 * Calendar — shadcn/ui calendar on react-day-picker.
 *
 * Generated from the shadcn Base UI registry, then mapped onto the AquaShield
 * token layer (`surface-*`, `aq-accent`) so the month grid inherits the brand
 * ramp instead of the registry's neutral default. Behaviour and the
 * react-day-picker class map are upstream.
 */
import * as React from 'react';
import { cn } from 'cn';
import { DayPicker, getDefaultClassNames, type DayButton, type Locale } from 'react-day-picker';

import { Button, buttonVariants } from '@/Components/ui/button';
import { ChevronLeftIcon, ChevronRightIcon, ChevronDownIcon } from 'lucide-react';

function Calendar({
    className,
    classNames,
    showOutsideDays = true,
    captionLayout = 'label',
    buttonVariant = 'ghost',
    locale,
    formatters,
    components,
    ...props
}: React.ComponentProps<typeof DayPicker> & {
    buttonVariant?: React.ComponentProps<typeof Button>['variant'];
}) {
    const defaultClassNames = getDefaultClassNames();

    return (
        <DayPicker
            showOutsideDays={showOutsideDays}
            className={cn(
                'group/calendar bg-surface-0 p-2 [--cell-radius:var(--radius-md)] [--cell-size:--spacing(7)] in-data-[slot=card-content]:bg-transparent in-data-[slot=popover-content]:bg-transparent dark:bg-surface-900',
                String.raw`rtl:**:[.rdp-button\_next>svg]:rotate-180`,
                String.raw`rtl:**:[.rdp-button\_previous>svg]:rotate-180`,
                className,
            )}
            captionLayout={captionLayout}
            locale={locale}
            formatters={{
                formatMonthDropdown: (date) =>
                    date.toLocaleString(locale?.code, { month: 'short' }),
                ...formatters,
            }}
            classNames={{
                root: cn('w-fit', defaultClassNames.root),
                months: cn('relative flex flex-col gap-4 md:flex-row', defaultClassNames.months),
                month: cn('flex w-full flex-col gap-4', defaultClassNames.month),
                nav: cn(
                    'absolute inset-x-0 top-0 flex w-full items-center justify-between gap-1',
                    defaultClassNames.nav,
                ),
                button_previous: cn(
                    buttonVariants({ variant: buttonVariant }),
                    'size-(--cell-size) p-0 select-none aria-disabled:opacity-50',
                    defaultClassNames.button_previous,
                ),
                button_next: cn(
                    buttonVariants({ variant: buttonVariant }),
                    'size-(--cell-size) p-0 select-none aria-disabled:opacity-50',
                    defaultClassNames.button_next,
                ),
                month_caption: cn(
                    'flex h-(--cell-size) w-full items-center justify-center px-(--cell-size)',
                    defaultClassNames.month_caption,
                ),
                dropdowns: cn(
                    'flex h-(--cell-size) w-full items-center justify-center gap-1.5 text-sm font-medium',
                    defaultClassNames.dropdowns,
                ),
                dropdown_root: cn(
                    'relative rounded-(--cell-radius)',
                    defaultClassNames.dropdown_root,
                ),
                dropdown: cn(
                    'absolute inset-0 bg-surface-0 opacity-0 dark:bg-surface-900',
                    defaultClassNames.dropdown,
                ),
                caption_label: cn(
                    'font-medium select-none',
                    captionLayout === 'label'
                        ? 'text-sm'
                        : 'flex items-center gap-1 rounded-(--cell-radius) text-sm [&>svg]:size-3.5 [&>svg]:text-surface-500 dark:[&>svg]:text-surface-400',
                    defaultClassNames.caption_label,
                ),
                month_grid: cn('w-full border-collapse', defaultClassNames.month_grid),
                weekdays: cn('flex', defaultClassNames.weekdays),
                weekday: cn(
                    'flex-1 rounded-(--cell-radius) text-[0.8rem] font-normal text-surface-500 select-none dark:text-surface-300',
                    defaultClassNames.weekday,
                ),
                week: cn('mt-2 flex w-full', defaultClassNames.week),
                week_number_header: cn(
                    'w-(--cell-size) select-none',
                    defaultClassNames.week_number_header,
                ),
                week_number: cn(
                    'text-[0.8rem] text-surface-500 select-none dark:text-surface-300',
                    defaultClassNames.week_number,
                ),
                day: cn(
                    'group/day relative aspect-square h-full w-full rounded-(--cell-radius) p-0 text-center select-none [&:last-child[data-selected=true]_button]:rounded-r-(--cell-radius)',
                    props.showWeekNumber
                        ? '[&:nth-child(2)[data-selected=true]_button]:rounded-l-(--cell-radius)'
                        : '[&:first-child[data-selected=true]_button]:rounded-l-(--cell-radius)',
                    defaultClassNames.day,
                ),
                range_start: cn(
                    'relative isolate z-0 rounded-l-(--cell-radius) bg-surface-100 after:absolute after:inset-y-0 after:right-0 after:w-4 after:bg-surface-100 dark:bg-surface-800 dark:after:bg-surface-800',
                    defaultClassNames.range_start,
                ),
                range_middle: cn('rounded-none', defaultClassNames.range_middle),
                range_end: cn(
                    'relative isolate z-0 rounded-r-(--cell-radius) bg-surface-100 after:absolute after:inset-y-0 after:left-0 after:w-4 after:bg-surface-100 dark:bg-surface-800 dark:after:bg-surface-800',
                    defaultClassNames.range_end,
                ),
                today: cn(
                    'rounded-(--cell-radius) bg-surface-100 text-surface-700 data-[selected=true]:rounded-none dark:bg-surface-800 dark:text-surface-0',
                    defaultClassNames.today,
                ),
                outside: cn(
                    'text-surface-500 aria-selected:text-surface-500 dark:text-surface-300 dark:aria-selected:text-surface-400',
                    defaultClassNames.outside,
                ),
                disabled: cn(
                    'text-surface-500 opacity-50 dark:text-surface-300',
                    defaultClassNames.disabled,
                ),
                hidden: cn('invisible', defaultClassNames.hidden),
                ...classNames,
            }}
            components={{
                Root: ({ className, rootRef, ...props }) => {
                    return (
                        <div
                            data-slot="calendar"
                            ref={rootRef}
                            className={cn(className)}
                            {...props}
                        />
                    );
                },
                Chevron: ({ className, orientation, ...props }) => {
                    if (orientation === 'left') {
                        return <ChevronLeftIcon className={cn('size-4', className)} {...props} />;
                    }

                    if (orientation === 'right') {
                        return <ChevronRightIcon className={cn('size-4', className)} {...props} />;
                    }

                    return <ChevronDownIcon className={cn('size-4', className)} {...props} />;
                },
                DayButton: ({ ...props }) => <CalendarDayButton locale={locale} {...props} />,
                WeekNumber: ({ children, ...props }) => {
                    return (
                        <td {...props}>
                            <div className="flex size-(--cell-size) items-center justify-center text-center">
                                {children}
                            </div>
                        </td>
                    );
                },
                ...components,
            }}
            {...props}
        />
    );
}

function CalendarDayButton({
    className,
    day,
    modifiers,
    locale,
    ...props
}: React.ComponentProps<typeof DayButton> & { locale?: Partial<Locale> }) {
    const defaultClassNames = getDefaultClassNames();

    const ref = React.useRef<HTMLButtonElement>(null);
    React.useEffect(() => {
        if (modifiers.focused) ref.current?.focus();
    }, [modifiers.focused]);

    return (
        <Button
            variant="ghost"
            size="icon"
            data-day={day.date.toLocaleDateString(locale?.code)}
            data-selected-single={
                modifiers.selected &&
                !modifiers.range_start &&
                !modifiers.range_end &&
                !modifiers.range_middle
            }
            data-range-start={modifiers.range_start}
            data-range-end={modifiers.range_end}
            data-range-middle={modifiers.range_middle}
            className={cn(
                'relative isolate z-10 flex aspect-square size-auto w-full min-w-(--cell-size) flex-col gap-1 border-0 leading-none font-normal group-data-[focused=true]/day:relative group-data-[focused=true]/day:z-10 group-data-[focused=true]/day:border-aq-accent group-data-[focused=true]/day:ring-[3px] group-data-[focused=true]/day:ring-aq-accent/50 data-[range-end=true]:rounded-(--cell-radius) data-[range-end=true]:rounded-r-(--cell-radius) data-[range-end=true]:bg-aq-accent data-[range-end=true]:text-aq-void data-[range-middle=true]:rounded-none data-[range-middle=true]:bg-surface-100 data-[range-middle=true]:text-surface-700 data-[range-start=true]:rounded-(--cell-radius) data-[range-start=true]:rounded-l-(--cell-radius) data-[range-start=true]:bg-aq-accent data-[range-start=true]:text-aq-void data-[selected-single=true]:bg-aq-accent data-[selected-single=true]:text-aq-void dark:hover:text-surface-0 dark:data-[range-middle=true]:bg-surface-800 dark:data-[range-middle=true]:text-surface-0 [&>span]:text-xs [&>span]:opacity-70',
                defaultClassNames.day,
                className,
            )}
            {...props}
        />
    );
}

export { Calendar, CalendarDayButton };
