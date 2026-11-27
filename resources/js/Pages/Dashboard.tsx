/**
 * Dashboard — the real operations view.
 *
 * Server props only: the lead columns come from the same `LeadRepository::board`
 * used by `/leads/board`, scoped to the selected period, and the calendar
 * fetches the visible month through `/appointments/calendar-events`. There is
 * no mock row and no second pipeline state in the browser.
 *
 * The page is ordered by operational priority: four real period metrics, then
 * the pipeline itself in three kept-alive views (board, table, calendar). The
 * shell owns the page title and primary action; the page owns the toolbar
 * (view selector, period, scope filters) and the content surfaces. The period
 * and the scope filters live in the URL, so reload and back/forward resolve to
 * the same dashboard.
 */
import { Activity, lazy, Suspense, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarClock,
    CalendarPlus,
    KanbanSquare,
    Plus,
    Table2,
    Trophy,
    UserPlus,
} from 'lucide-react';
import KpiCard from '@/Components/KpiCard';
import PasskeyPrompt from '@/Pages/Dashboard/components/PasskeyPrompt';
import { Button } from '@/Components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Skeleton } from '@/Components/ui/skeleton';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import { Tag } from '@/Components/ui/tag';
import { CrudFilters, type CrudFilterDef } from '@/Components/DataTable';
import AppLayout from '@/Layouts/AppLayout';
import { usePasskeyPrompt } from '@/Modules/Auth/hooks/usePasskeyPrompt';
import LeadKanban from '@/Modules/LeadAppointment/components/LeadKanban';
import ScheduleAppointmentDialog from '@/Modules/LeadAppointment/components/ScheduleAppointmentDialog';
import { useAppointmentDetail } from '@/Modules/LeadAppointment/hooks/useAppointmentDetail';
import {
    useCalendarEvents,
    useCalendarEventsInvalidator,
    type CalendarEvent,
} from '@/Modules/LeadAppointment/hooks/useCalendarEvents';
import LeadCreateDialog from '@/Pages/Dashboard/components/LeadCreateDialog';
import LeadsPanel from '@/Pages/Dashboard/components/LeadsPanel';
import { formatDateTime } from '@/lib/date';
import {
    buildTableUrl,
    isBlankFilter,
    parseTableParams,
    type TableFilters,
    type TableState,
} from '@/lib/table';
import { useTheme } from '@/lib/theme';
import { cn, humanize } from '@/lib/utils';

const BoardCalendar = lazy(() => import('@/Pages/Dashboard/BoardCalendar'));

type LeadCard = Modules.LeadAppointment.Application.Dtos.LeadCardData;
type LeadBoardColumn = Modules.LeadAppointment.Application.Dtos.LeadBoardColumnData;

interface StatComparison {
    current: number;
    previous: number;
    /** Daily counts for the selected period, oldest first. */
    series: number[];
}

type Option = { value: string; label: string };

interface DashboardProps {
    [key: string]: unknown;
    columns: LeadBoardColumn[];
    transitions: Record<string, string[]>;
    stats: {
        leads: StatComparison;
        inspections: StatComparison;
        won: StatComparison;
        appointments: StatComparison;
    };
    canCreate: boolean;
    canUpdate: boolean;
    canViewLeads: boolean;
    canScheduleAppointment: boolean;
    canViewCalendar: boolean;
    canViewAppointment: boolean;
    canViewLead: boolean;
    options: {
        kinds: Option[];
        regions: Option[];
        statuses: Option[];
        sources: Option[];
    };
    leadOptions: {
        regions: Option[];
        owners: Modules.LeadAppointment.Application.Dtos.AssignableOwnerData[];
    };
    googleMapsApiKey: string;
    defaultDuration: number;
    defaultTimezone: string;
    /** Selected period, echoed from the URL (`range` query key). */
    range: string;
    rangeOptions: Option[];
    /** The selected period's inclusive bounds (company timezone, YYYY-MM-DD). */
    rangeFrom: string;
    rangeTo: string;
    passkeyPrompt: boolean | null;
    errors: Record<string, string>;
}

type DashboardView = 'kanban' | 'table' | 'calendar';

/** The server default; omitted from the URL so a clean link stays clean. */
const DEFAULT_RANGE = 'last_30_days';

/*
 * KPI tiles deep-link into the filtered lists through the one table URL
 * contract, so a filter-key rename on the list screens cannot silently
 * break the tile destinations.
 */
const INSPECTIONS_HREF = buildTableUrl(
    '/appointments',
    { page: 1, perPage: 15, search: '', sort: null, filters: { kind: 'inspection' } },
    { paginate: false },
);
const WON_LEADS_HREF = buildTableUrl(
    '/leads',
    { page: 1, perPage: 15, search: '', sort: null, filters: { status: 'won' } },
    { paginate: false },
);

/** Period + scope filters are the dashboard URL; everything else is local. */
const dashboardUrl = (range: string, filters: TableFilters): string => {
    const params = new URLSearchParams();

    if (range !== DEFAULT_RANGE) {
        params.set('range', range);
    }

    for (const [key, value] of Object.entries(filters)) {
        if (!isBlankFilter(value)) {
            params.set(`filter[${key}]`, String(value));
        }
    }

    const query = params.toString();

    return query === '' ? '/dashboard' : `/dashboard?${query}`;
};

type DeltaDirection = 'up' | 'down' | 'flat';

/**
 * The chip's copy is derived from the two server counts, never from a prop that
 * asserts a direction. Percent only when there is a base to divide by;
 * otherwise the absolute change is the honest number. A rounded 0% with a real
 * difference falls back to the count, so "+1" never renders as "0%".
 *
 * Two zero counts produce a neutral state, not a comparison: "same as previous
 * period" on a fresh workspace is noise dressed as a delta.
 */
const periodDelta = ({
    current,
    previous,
}: StatComparison): { trend: DeltaDirection; label: string } => {
    if (current === 0 && previous === 0) {
        return { trend: 'flat', label: 'No activity in this period' };
    }

    if (current === previous) {
        return { trend: 'flat', label: 'same as previous period' };
    }

    const difference = current - previous;
    const trend: DeltaDirection = difference > 0 ? 'up' : 'down';
    const absolute = `${difference > 0 ? '+' : ''}${difference} vs. previous period`;

    if (previous === 0) {
        return { trend, label: absolute };
    }

    const percent = Math.round((difference / previous) * 100);

    // A rounded -100% with a non-zero current count hides the remaining leads
    // (e.g. 300 → 1). Show the real count instead of a loss that never happened.
    return percent === 0 || (percent === -100 && current > 0)
        ? { trend, label: absolute }
        : { trend, label: `${percent > 0 ? '+' : ''}${percent}% vs. previous period` };
};

const segmentClass = (active: boolean): string =>
    cn(
        'inline-flex h-8 items-center justify-center gap-1.5 rounded-md px-3 text-xs font-semibold transition-colors duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-aq-focus motion-reduce:transition-none',
        active
            ? 'bg-surface-0 text-surface-900 shadow-sm ring-1 ring-surface-200/80 dark:bg-surface-700 dark:text-surface-0 dark:ring-surface-600/80'
            : 'text-surface-500 hover:bg-surface-0/70 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800/60 dark:hover:text-surface-0',
    );

const segmentIconClass = (active: boolean): string =>
    cn(
        'size-3.5 shrink-0',
        active ? 'text-aq-accent-text' : 'text-surface-400 dark:text-surface-400',
    );

function PipelineEmptyState({
    icon,
    title,
    description,
}: {
    icon: React.ReactNode;
    title: string;
    description: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-surface-300/80 bg-surface-0/60 px-6 py-14 text-center dark:border-surface-700 dark:bg-surface-900/40">
            <span className="grid size-11 place-items-center rounded-xl bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-300">
                {icon}
            </span>
            <p className="mt-3 text-sm font-semibold text-surface-900 dark:text-surface-0">
                {title}
            </p>
            <p className="mt-1 max-w-sm text-sm text-surface-600 dark:text-surface-300">
                {description}
            </p>
        </div>
    );
}

export default function Dashboard() {
    const { url, props } = usePage<DashboardProps>();
    const {
        columns,
        transitions,
        stats,
        canCreate,
        canUpdate,
        canViewLeads,
        canScheduleAppointment,
        canViewCalendar,
        canViewAppointment,
        canViewLead,
        options,
        leadOptions,
        googleMapsApiKey,
        defaultDuration,
        defaultTimezone,
        passkeyPrompt,
        errors,
        range,
        rangeOptions,
        rangeFrom,
        rangeTo,
    } = props;
    const prompt = usePasskeyPrompt();
    const { isDark } = useTheme();
    const invalidateCalendar = useCalendarEventsInvalidator();

    const [view, setView] = useState<DashboardView>(canViewLeads ? 'kanban' : 'calendar');
    const [selectedLead, setSelectedLead] = useState<LeadCard | null>(null);
    const [selectedEvent, setSelectedEvent] = useState<CalendarEvent | null>(null);
    const [leadCreateOpen, setLeadCreateOpen] = useState(false);
    const [scheduleOpen, setScheduleOpen] = useState(false);
    const [scheduleDate, setScheduleDate] = useState('');
    const [scheduleLead, setScheduleLead] = useState<{
        uuid: string;
        name: string;
        phone: string | null;
        region: string | null;
    } | null>(null);
    const [calendarRange, setCalendarRange] = useState<{ from: string; to: string } | null>(null);

    /*
     * The URL owns the period and the scope filters, exactly like the list
     * screens own their table state: reload, back/forward and a pasted link
     * all resolve to the same dashboard. The board, the KPI row and the
     * embedded table read this one scope.
     */
    const urlFilters = parseTableParams(url).filters;
    const filterState: TableState = {
        page: 1,
        perPage: 15,
        search: '',
        sort: null,
        filters: urlFilters,
    };

    const commitScope = (nextRange: string, patch: TableFilters): void => {
        const filters: TableFilters = { ...urlFilters };

        for (const [key, value] of Object.entries(patch)) {
            if (isBlankFilter(value)) {
                delete filters[key];

                continue;
            }

            filters[key] = value;
        }

        router.visit(dashboardUrl(nextRange, filters), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const boardFilterDefs: CrudFilterDef[] = [
        {
            key: 'region',
            label: 'Region',
            type: 'select',
            placeholder: 'Any region',
            options: options.regions,
        },
        {
            key: 'source',
            label: 'Source',
            type: 'select',
            placeholder: 'Any source',
            options: options.sources,
        },
    ];

    const rangeLabel = rangeOptions.find((option) => option.value === range)?.label ?? range;

    const calendar = useCalendarEvents(
        calendarRange?.from ?? '',
        calendarRange?.to ?? '',
        // Only the visible calendar owns a live feed; a hidden pane must not
        // refetch on window focus while the user works the board or table.
        canViewCalendar && calendarRange !== null && view === 'calendar',
    );
    const detail = useAppointmentDetail(selectedEvent?.uuid ?? null, canViewAppointment);
    const appointment = detail.appointment;

    const drawerOpen = selectedLead !== null || selectedEvent !== null;

    const openSchedule = (
        date: string,
        lead: { uuid: string; name: string; phone: string | null; region: string | null } | null,
    ): void => {
        setScheduleDate(date);
        setScheduleLead(lead);
        setScheduleOpen(true);
    };

    const openAppointment = (uuid: string): void => {
        setSelectedEvent(calendar.events.find((row) => row.uuid === uuid) ?? null);
        setSelectedLead(null);
    };

    const leadsDelta = periodDelta(stats.leads);
    const inspectionsDelta = periodDelta(stats.inspections);
    const wonDelta = periodDelta(stats.won);
    const appointmentsDelta = periodDelta(stats.appointments);

    const pipelineTotal = columns.reduce((total, column) => total + column.total, 0);
    const hasAnyView = canViewLeads || canViewCalendar;

    return (
        <AppLayout
            title="Dashboard"
            subtitle="Your lead pipeline and scheduled inspections."
            actions={
                canCreate ? (
                    <Button size="header" className="gap-2" onClick={() => setLeadCreateOpen(true)}>
                        <Plus className="size-4" aria-hidden="true" />
                        New lead
                    </Button>
                ) : undefined
            }
        >
            <Head title="Dashboard" />

            <div className="flex flex-col gap-5">
                {passkeyPrompt === true && prompt.visible ? (
                    <PasskeyPrompt
                        pending={prompt.pending}
                        error={prompt.error}
                        onDismiss={() => void prompt.dismiss()}
                    />
                ) : null}

                {/*
                 * Period-over-period, same scope on every card and every chip.
                 * The board below is the period's intake cohort, so repeating
                 * its column totals here would say the same thing twice. The
                 * values, the daily sparkline and the comparison window all
                 * come from the server for the selected period (company
                 * timezone).
                 */}
                <section
                    id="kpis"
                    aria-label="Key metrics"
                    className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <KpiCard
                        label="New leads"
                        value={stats.leads.current}
                        delta={leadsDelta.label}
                        trend={leadsDelta.trend}
                        series={stats.leads.series}
                        index={0}
                        href={canViewLeads ? '/leads' : undefined}
                        icon={<UserPlus className="size-5" aria-hidden="true" />}
                    />
                    <KpiCard
                        label="Inspections"
                        value={stats.inspections.current}
                        delta={inspectionsDelta.label}
                        trend={inspectionsDelta.trend}
                        series={stats.inspections.series}
                        index={1}
                        href={canViewCalendar ? INSPECTIONS_HREF : undefined}
                        icon={<CalendarCheck className="size-5" aria-hidden="true" />}
                    />
                    <KpiCard
                        label="Won"
                        value={stats.won.current}
                        delta={wonDelta.label}
                        trend={wonDelta.trend}
                        series={stats.won.series}
                        index={2}
                        href={canViewLeads ? WON_LEADS_HREF : undefined}
                        icon={<Trophy className="size-5" aria-hidden="true" />}
                    />
                    <KpiCard
                        label="Appointments"
                        value={stats.appointments.current}
                        delta={appointmentsDelta.label}
                        trend={appointmentsDelta.trend}
                        series={stats.appointments.series}
                        index={3}
                        href={canViewCalendar ? '/appointments' : undefined}
                        icon={<CalendarClock className="size-5" aria-hidden="true" />}
                    />
                </section>

                <section
                    id="pipeline"
                    aria-label="Lead pipeline and inspection calendar"
                    className="scroll-mt-24"
                >
                    {hasAnyView && (
                        <div className="mb-3 flex flex-col gap-3 rounded-xl border border-surface-200 bg-surface-0 p-2 xl:flex-row xl:items-center xl:justify-between dark:border-surface-800 dark:bg-surface-900">
                            <div
                                role="group"
                                aria-label="Pipeline view"
                                className="inline-flex self-start items-center gap-0.5 rounded-lg border border-surface-200 bg-surface-100/70 p-1 dark:border-surface-800 dark:bg-surface-900/60"
                            >
                                {canViewLeads && (
                                    <button
                                        type="button"
                                        aria-pressed={view === 'kanban'}
                                        onClick={() => setView('kanban')}
                                        className={segmentClass(view === 'kanban')}
                                    >
                                        <KanbanSquare
                                            className={segmentIconClass(view === 'kanban')}
                                            aria-hidden="true"
                                        />
                                        Kanban
                                    </button>
                                )}

                                {canViewLeads && (
                                    <button
                                        type="button"
                                        aria-pressed={view === 'table'}
                                        onClick={() => setView('table')}
                                        className={segmentClass(view === 'table')}
                                    >
                                        <Table2
                                            className={segmentIconClass(view === 'table')}
                                            aria-hidden="true"
                                        />
                                        Table
                                    </button>
                                )}

                                {canViewCalendar && (
                                    <button
                                        type="button"
                                        aria-pressed={view === 'calendar'}
                                        onClick={() => setView('calendar')}
                                        className={segmentClass(view === 'calendar')}
                                    >
                                        <CalendarClock
                                            className={segmentIconClass(view === 'calendar')}
                                            aria-hidden="true"
                                        />
                                        Calendar
                                    </button>
                                )}
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                {canViewLeads && pipelineTotal > 0 && (
                                    <p className="text-xs text-surface-600 dark:text-surface-300">
                                        <span className="font-semibold text-surface-700 tabular-nums dark:text-surface-200">
                                            {pipelineTotal}
                                        </span>{' '}
                                        {pipelineTotal === 1 ? 'lead' : 'leads'} across{' '}
                                        {columns.length} {columns.length === 1 ? 'stage' : 'stages'}
                                    </p>
                                )}

                                <Select
                                    items={rangeOptions}
                                    value={range}
                                    onValueChange={(next) => commitScope(String(next), {})}
                                >
                                    <SelectTrigger className="w-40" aria-label="Metric period">
                                        <SelectValue>{rangeLabel}</SelectValue>
                                    </SelectTrigger>
                                    <SelectContent>
                                        {rangeOptions.map((option) => (
                                            <SelectItem key={option.value} value={option.value}>
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                {canViewLeads && (
                                    <CrudFilters
                                        defs={boardFilterDefs}
                                        state={filterState}
                                        onChange={(patch) => commitScope(range, patch)}
                                        className="w-auto"
                                    />
                                )}
                            </div>
                        </div>
                    )}

                    {!hasAnyView ? (
                        <PipelineEmptyState
                            icon={<KanbanSquare className="size-5" aria-hidden="true" />}
                            title="Pipeline unavailable"
                            description="Your role does not include access to the lead pipeline or the appointment calendar."
                        />
                    ) : (
                        <>
                            {/*
                             * Activity keeps each pane's DOM and state alive across
                             * view switches: the calendar keeps the visited month,
                             * the board keeps its drag state, the table keeps its
                             * page. Hidden panes fetch nothing (enabled gates).
                             */}
                            <Activity mode={view === 'kanban' ? 'visible' : 'hidden'}>
                                {canViewLeads ? (
                                    pipelineTotal > 0 ? (
                                        <LeadKanban
                                            columns={columns}
                                            transitions={transitions}
                                            canUpdate={canUpdate}
                                            errors={errors}
                                            variant="fit"
                                            onSelect={(lead) => {
                                                setSelectedLead(lead);
                                                setSelectedEvent(null);
                                            }}
                                        />
                                    ) : (
                                        <PipelineEmptyState
                                            icon={
                                                <KanbanSquare
                                                    className="size-5"
                                                    aria-hidden="true"
                                                />
                                            }
                                            title="No leads in this period"
                                            description={
                                                canCreate
                                                    ? 'Adjust the period or filters, or create a lead to get started.'
                                                    : 'Adjust the period or filters to widen the view.'
                                            }
                                        />
                                    )
                                ) : null}
                            </Activity>

                            <Activity mode={view === 'table' ? 'visible' : 'hidden'}>
                                {canViewLeads ? (
                                    <LeadsPanel
                                        options={options}
                                        filters={urlFilters}
                                        rangeFrom={rangeFrom}
                                        rangeTo={rangeTo}
                                        onFiltersChange={(patch) => commitScope(range, patch)}
                                        canView={canViewLead}
                                        canUpdate={canUpdate}
                                        enabled={view === 'table'}
                                    />
                                ) : null}
                            </Activity>

                            <Activity mode={view === 'calendar' ? 'visible' : 'hidden'}>
                                {canViewCalendar ? (
                                    <div className="overflow-hidden rounded-xl border border-surface-200 bg-surface-0 dark:border-surface-800 dark:bg-surface-900">
                                        {calendar.isError ? (
                                            <div
                                                role="alert"
                                                className="mx-3 mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-aq-danger/40 bg-aq-danger/5 px-3 py-2 text-sm text-aq-danger-strong"
                                            >
                                                <span>The calendar could not be loaded.</span>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={calendar.retry}
                                                >
                                                    Try again
                                                </Button>
                                            </div>
                                        ) : null}

                                        <Suspense
                                            fallback={
                                                <div className="p-4" aria-busy="true">
                                                    <Skeleton className="mb-3 h-8 w-56" />
                                                    <div className="grid grid-cols-7 gap-2">
                                                        {Array.from({ length: 35 }, (_, index) => (
                                                            <Skeleton
                                                                key={index}
                                                                className="h-20 rounded-lg"
                                                            />
                                                        ))}
                                                    </div>
                                                </div>
                                            }
                                        >
                                            <BoardCalendar
                                                events={
                                                    calendar.isPlaceholderData
                                                        ? []
                                                        : calendar.events
                                                }
                                                isFetching={calendar.isFetching}
                                                colorScheme={isDark ? 'dark' : 'light'}
                                                onSelect={openAppointment}
                                                onDateClick={
                                                    canScheduleAppointment
                                                        ? (date) => openSchedule(date, null)
                                                        : undefined
                                                }
                                                onRangeChange={(from, to) =>
                                                    setCalendarRange((current) =>
                                                        current?.from === from && current.to === to
                                                            ? current
                                                            : { from, to },
                                                    )
                                                }
                                            />
                                        </Suspense>
                                    </div>
                                ) : null}
                            </Activity>
                        </>
                    )}
                </section>
            </div>

            <Sheet
                open={drawerOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedLead(null);
                        setSelectedEvent(null);
                    }
                }}
            >
                <SheetContent side="right" className="sm:max-w-md">
                    {selectedLead !== null && (
                        <>
                            <SheetHeader>
                                <SheetTitle className="text-lg font-bold text-surface-900 dark:text-surface-0">
                                    {selectedLead.full_name}
                                </SheetTitle>
                                <SheetDescription>
                                    {humanize(selectedLead.status)} ·{' '}
                                    {humanize(selectedLead.source)}
                                </SheetDescription>
                            </SheetHeader>

                            <div className="mt-4 flex flex-col gap-3 px-4 text-sm text-surface-600 dark:text-surface-300">
                                <p>
                                    <span className="font-semibold text-surface-900 dark:text-surface-0">
                                        Phone:
                                    </span>{' '}
                                    {selectedLead.phone ?? '—'}
                                </p>
                                <p>
                                    <span className="font-semibold text-surface-900 dark:text-surface-0">
                                        Location:
                                    </span>{' '}
                                    {[selectedLead.city, selectedLead.state]
                                        .filter(Boolean)
                                        .join(', ') || humanize(selectedLead.region ?? '—')}
                                </p>
                                <p>
                                    <span className="font-semibold text-surface-900 dark:text-surface-0">
                                        Created:
                                    </span>{' '}
                                    {formatDateTime(selectedLead.created_at)}
                                </p>
                                {selectedLead.inspection_at !== null && (
                                    <p>
                                        <span className="font-semibold text-surface-900 dark:text-surface-0">
                                            Inspection:
                                        </span>{' '}
                                        {formatDateTime(selectedLead.inspection_at)}
                                    </p>
                                )}
                                {selectedLead.is_emergency && (
                                    <Tag variant="destructive">Emergency</Tag>
                                )}
                            </div>

                            <div className="mt-6 flex flex-col gap-2 px-4">
                                {canScheduleAppointment && (
                                    <Button
                                        className="w-full gap-2"
                                        onClick={() => {
                                            openSchedule('', {
                                                uuid: selectedLead.uuid,
                                                name: selectedLead.full_name,
                                                phone: selectedLead.phone,
                                                region: selectedLead.region,
                                            });
                                            setSelectedLead(null);
                                        }}
                                    >
                                        <CalendarPlus className="size-4" aria-hidden="true" />
                                        Schedule visit
                                    </Button>
                                )}
                                {canViewLead && (
                                    <Button
                                        render={<Link href={`/leads/${selectedLead.uuid}`} />}
                                        nativeButton={false}
                                        variant="outline"
                                        className="w-full"
                                    >
                                        Open lead
                                    </Button>
                                )}
                            </div>
                        </>
                    )}

                    {selectedEvent !== null && (
                        <>
                            <SheetHeader>
                                <SheetTitle className="text-lg font-bold text-surface-900 dark:text-surface-0">
                                    {appointment?.customer_name ?? selectedEvent.customer_name}
                                </SheetTitle>
                                <SheetDescription>
                                    {humanize(appointment?.kind ?? selectedEvent.kind)} ·{' '}
                                    {humanize(appointment?.status ?? selectedEvent.status)}
                                </SheetDescription>
                            </SheetHeader>

                            <div className="mt-4 flex flex-col gap-3 px-4 text-sm text-surface-600 dark:text-surface-300">
                                {canViewAppointment && detail.isPending ? (
                                    <div className="flex flex-col gap-2" aria-busy="true">
                                        <Skeleton className="h-5 w-3/4" />
                                        <Skeleton className="h-5 w-1/2" />
                                        <Skeleton className="h-16 w-full" />
                                    </div>
                                ) : appointment !== null ? (
                                    <>
                                        <p>
                                            <span className="font-semibold text-surface-900 dark:text-surface-0">
                                                When:
                                            </span>{' '}
                                            {formatDateTime(appointment.starts_at_local, 'en-US', {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                                timeZone: appointment.timezone,
                                            })}{' '}
                                            –{' '}
                                            {formatDateTime(appointment.ends_at_local, 'en-US', {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                                timeZone: appointment.timezone,
                                            })}
                                        </p>
                                        <p>
                                            <span className="font-semibold text-surface-900 dark:text-surface-0">
                                                Timezone:
                                            </span>{' '}
                                            {appointment.timezone}
                                        </p>
                                        <p>
                                            <span className="font-semibold text-surface-900 dark:text-surface-0">
                                                Property:
                                            </span>{' '}
                                            {appointment.address_line || '—'}
                                        </p>
                                        {appointment.notes !== null && (
                                            <p>
                                                <span className="font-semibold text-surface-900 dark:text-surface-0">
                                                    Notes:
                                                </span>{' '}
                                                {appointment.notes}
                                            </p>
                                        )}
                                        {appointment.cancel_reason !== null && (
                                            <p>
                                                <span className="font-semibold text-surface-900 dark:text-surface-0">
                                                    Cancelled:
                                                </span>{' '}
                                                {appointment.cancel_reason}
                                            </p>
                                        )}
                                    </>
                                ) : (
                                    <>
                                        <p>
                                            <span className="font-semibold text-surface-900 dark:text-surface-0">
                                                When:
                                            </span>{' '}
                                            {formatDateTime(
                                                selectedEvent.starts_at_local,
                                                'en-US',
                                                {
                                                    dateStyle: 'medium',
                                                    timeStyle: 'short',
                                                    timeZone: selectedEvent.timezone,
                                                },
                                            )}
                                        </p>
                                        <p>
                                            <span className="font-semibold text-surface-900 dark:text-surface-0">
                                                Timezone:
                                            </span>{' '}
                                            {selectedEvent.timezone}
                                        </p>
                                    </>
                                )}

                                {canViewAppointment && detail.isError ? (
                                    <p role="alert" className="text-sm text-aq-danger-strong">
                                        The full appointment could not be loaded. Open the
                                        appointment file to retry.
                                    </p>
                                ) : null}
                            </div>

                            <div className="mt-6 flex flex-col gap-2 px-4">
                                {canViewAppointment && (
                                    <Button
                                        render={
                                            <Link href={`/appointments/${selectedEvent.uuid}`} />
                                        }
                                        nativeButton={false}
                                        className="w-full"
                                    >
                                        Open appointment
                                    </Button>
                                )}
                                {canViewLead && (
                                    <Button
                                        render={
                                            <Link
                                                href={`/leads/${appointment?.lead_uuid ?? selectedEvent.lead_uuid}`}
                                            />
                                        }
                                        nativeButton={false}
                                        variant="outline"
                                        className="w-full"
                                    >
                                        Open lead
                                    </Button>
                                )}
                            </div>
                        </>
                    )}
                </SheetContent>
            </Sheet>

            {canCreate && leadCreateOpen && (
                <LeadCreateDialog
                    open={leadCreateOpen}
                    onOpenChange={setLeadCreateOpen}
                    options={leadOptions}
                    googleMapsApiKey={googleMapsApiKey}
                    onCreated={() => {
                        router.reload({ only: ['columns', 'stats'] });
                    }}
                />
            )}

            {canScheduleAppointment && scheduleOpen && (
                <ScheduleAppointmentDialog
                    open={scheduleOpen}
                    onOpenChange={(open) => {
                        setScheduleOpen(open);

                        if (!open) {
                            setScheduleLead(null);
                            setScheduleDate('');
                        }
                    }}
                    options={options}
                    defaultDuration={defaultDuration}
                    timezone={defaultTimezone}
                    defaultDate={scheduleDate}
                    lead={scheduleLead}
                    onScheduled={() => {
                        invalidateCalendar();
                        router.reload({ only: ['columns', 'stats'] });
                    }}
                />
            )}
        </AppLayout>
    );
}
