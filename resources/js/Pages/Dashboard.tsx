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
import { Head, router, usePage } from '@inertiajs/react';
import { KanbanSquare, Plus } from 'lucide-react';
import ErrorBoundary from '@/Components/ErrorBoundary';
import PasskeyPrompt from '@/Pages/Dashboard/components/PasskeyPrompt';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import { Sheet, SheetContent } from '@/Components/ui/sheet';
import type { CrudFilterDef } from '@/Components/DataTable/CrudFilters';
import AppLayout from '@/Layouts/AppLayout';
import { usePasskeyPrompt } from '@/Modules/Auth/hooks/usePasskeyPrompt';
import LeadKanban from '@/Modules/LeadAppointment/components/LeadKanban';
import { useAppointmentDetail } from '@/Modules/LeadAppointment/hooks/useAppointmentDetail';
import {
    useCalendarEvents,
    useCalendarEventsInvalidator,
    type CalendarEvent,
} from '@/Modules/LeadAppointment/hooks/useCalendarEvents';
import { type TableState } from '@/lib/table';
import { useTheme } from '@/lib/theme';
import AppointmentDetailSheet from '@/Pages/Dashboard/components/AppointmentDetailSheet';
import KpiRow from '@/Pages/Dashboard/components/KpiRow';
import LeadDetailSheet from '@/Pages/Dashboard/components/LeadDetailSheet';
import PipelineToolbar from '@/Pages/Dashboard/components/PipelineToolbar';
import { useDashboardScope } from '@/Pages/Dashboard/useDashboardScope';
import type { DashboardView, LeadCard, Option, ScheduleLead } from '@/Pages/Dashboard/types';

const BoardCalendar = lazy(() => import('@/Pages/Dashboard/BoardCalendar'));
const LeadCreateDialog = lazy(() => import('@/Pages/Dashboard/components/LeadCreateDialog'));
const LeadsPanel = lazy(() => import('@/Pages/Dashboard/components/LeadsPanel'));
const ScheduleAppointmentDialog = lazy(
    () => import('@/Modules/LeadAppointment/components/ScheduleAppointmentDialog'),
);

type LeadBoardColumn = Modules.LeadAppointment.Application.Dtos.LeadBoardColumnData;

interface DashboardProps {
    [key: string]: unknown;
    columns: LeadBoardColumn[];
    transitions: Record<string, string[]>;
    stats: Modules.LeadAppointment.Application.Dtos.DashboardStatsData;
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
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-aq-content-line bg-transparent px-6 py-14 text-center">
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

function TablePanelSkeleton() {
    return (
        <div
            className="rounded-xl border border-aq-shell-line bg-surface-0 p-4 dark:bg-surface-900"
            aria-busy="true"
        >
            <Skeleton className="h-8 w-56" />
            <div className="mt-4 flex flex-col gap-2">
                {Array.from({ length: 8 }, (_, index) => (
                    <Skeleton key={index} className="h-10 w-full" />
                ))}
            </div>
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

    const initialView: DashboardView = canViewLeads ? 'kanban' : 'calendar';
    const [view, setView] = useState<DashboardView>(initialView);
    /*
     * A pane's chunk is fetched the first time its tab is opened, not when the
     * dashboard mounts. `<Activity>` preserves the mounted pane afterwards;
     * the gate is what keeps the first paint from pre-rendering the table and
     * the calendar (and their dependency chunks).
     */
    const [openedViews, setOpenedViews] = useState<ReadonlySet<DashboardView>>(
        () => new Set([initialView]),
    );
    const openView = (next: DashboardView): void => {
        setView(next);
        setOpenedViews((current) => (current.has(next) ? current : new Set(current).add(next)));
    };
    const [selectedLead, setSelectedLead] = useState<LeadCard | null>(null);
    const [selectedEvent, setSelectedEvent] = useState<CalendarEvent | null>(null);
    const [leadCreateOpen, setLeadCreateOpen] = useState(false);
    const [scheduleOpen, setScheduleOpen] = useState(false);
    const [scheduleDate, setScheduleDate] = useState('');
    const [scheduleLead, setScheduleLead] = useState<ScheduleLead | null>(null);
    const [calendarRange, setCalendarRange] = useState<{ from: string; to: string } | null>(null);

    /*
     * The URL owns the period and the scope filters, exactly like the list
     * screens own their table state: reload, back/forward and a pasted link
     * all resolve to the same dashboard. The board, the KPI row, the calendar
     * scope and the embedded table read this one scope.
     */
    const scope = useDashboardScope({
        url,
        range,
        visit: (target, options) => {
            router.visit(target, {
                preserveScroll: true,
                preserveState: true,
                ...options,
            });
        },
    });
    const filterState: TableState = {
        page: 1,
        perPage: 15,
        search: '',
        sort: null,
        filters: scope.filters,
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

    const rangeLabel =
        rangeOptions.find((option) => option.value === scope.range)?.label ?? scope.range;
    const regionFilter = typeof scope.filters.region === 'string' ? scope.filters.region : '';

    const calendar = useCalendarEvents(
        calendarRange?.from ?? '',
        calendarRange?.to ?? '',
        regionFilter,
        // Only the visible calendar owns a live feed; a hidden pane must not
        // refetch on window focus while the user works the board or table.
        canViewCalendar && calendarRange !== null && view === 'calendar',
    );
    const detail = useAppointmentDetail(selectedEvent?.uuid ?? null, canViewAppointment);
    const appointment = detail.appointment;

    const drawerOpen = selectedLead !== null || selectedEvent !== null;

    const openSchedule = (date: string, lead: ScheduleLead | null): void => {
        setScheduleDate(date);
        setScheduleLead(lead);
        setScheduleOpen(true);
    };

    const openAppointment = (uuid: string): void => {
        setSelectedEvent(calendar.events.find((row) => row.uuid === uuid) ?? null);
        setSelectedLead(null);
    };

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
                <KpiRow
                    stats={stats}
                    rangeFrom={rangeFrom}
                    rangeTo={rangeTo}
                    canViewLeads={canViewLeads}
                    canViewCalendar={canViewCalendar}
                />

                <section
                    id="pipeline"
                    aria-label="Lead pipeline and inspection calendar"
                    className="scroll-mt-24"
                >
                    {hasAnyView && (
                        <PipelineToolbar
                            view={view}
                            onViewChange={openView}
                            canViewLeads={canViewLeads}
                            canViewCalendar={canViewCalendar}
                            pipelineTotal={pipelineTotal}
                            stageCount={columns.length}
                            rangeOptions={rangeOptions}
                            range={scope.range}
                            rangeLabel={rangeLabel}
                            onRangeChange={(next) => scope.commit(next, {})}
                            filterDefs={boardFilterDefs}
                            filterState={filterState}
                            onFiltersChange={(patch) => scope.commit(scope.range, patch)}
                            disabled={scope.pending}
                        />
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
                                {canViewLeads && openedViews.has('table') ? (
                                    <Suspense fallback={<TablePanelSkeleton />}>
                                        <LeadsPanel
                                            options={options}
                                            filters={scope.filters}
                                            rangeFrom={rangeFrom}
                                            rangeTo={rangeTo}
                                            onFiltersChange={(patch) =>
                                                scope.commit(scope.range, patch)
                                            }
                                            canView={canViewLead}
                                            canUpdate={canUpdate}
                                            enabled={view === 'table'}
                                        />
                                    </Suspense>
                                ) : null}
                            </Activity>

                            <Activity mode={view === 'calendar' ? 'visible' : 'hidden'}>
                                {canViewCalendar && openedViews.has('calendar') ? (
                                    <div className="overflow-hidden rounded-xl border border-aq-content-line bg-aq-card">
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

                                        <ErrorBoundary
                                            onReset={() => calendar.retry()}
                                            fallback={({ reset }) => (
                                                <div
                                                    role="alert"
                                                    className="flex flex-col items-center gap-3 px-4 py-10 text-center"
                                                >
                                                    <p className="text-sm font-medium text-surface-900 dark:text-surface-0">
                                                        The calendar could not be loaded.
                                                    </p>
                                                    <p className="max-w-md text-xs text-surface-600 dark:text-surface-300">
                                                        The rest of the dashboard is still
                                                        available.
                                                    </p>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={reset}
                                                    >
                                                        Try again
                                                    </Button>
                                                </div>
                                            )}
                                        >
                                            <Suspense
                                                fallback={
                                                    <div className="p-4" aria-busy="true">
                                                        <Skeleton className="mb-3 h-8 w-56" />
                                                        <div className="grid grid-cols-7 gap-2">
                                                            {Array.from(
                                                                { length: 42 },
                                                                (_, index) => (
                                                                    <Skeleton
                                                                        key={index}
                                                                        className="h-20 rounded-lg"
                                                                    />
                                                                ),
                                                            )}
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
                                                            current?.from === from &&
                                                            current.to === to
                                                                ? current
                                                                : { from, to },
                                                        )
                                                    }
                                                />
                                            </Suspense>
                                        </ErrorBoundary>
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
                        <LeadDetailSheet
                            lead={selectedLead}
                            canScheduleAppointment={canScheduleAppointment}
                            canViewLead={canViewLead}
                            onSchedule={openSchedule}
                            onClose={() => setSelectedLead(null)}
                        />
                    )}

                    {selectedEvent !== null && (
                        <AppointmentDetailSheet
                            event={selectedEvent}
                            appointment={appointment}
                            isPending={detail.isPending}
                            isError={detail.isError}
                            canViewAppointment={canViewAppointment}
                            canViewLead={canViewLead}
                        />
                    )}
                </SheetContent>
            </Sheet>

            {canCreate && leadCreateOpen && (
                <Suspense fallback={null}>
                    <LeadCreateDialog
                        open={leadCreateOpen}
                        onOpenChange={setLeadCreateOpen}
                        options={leadOptions}
                        googleMapsApiKey={googleMapsApiKey}
                        onCreated={() => {
                            router.reload({ only: ['columns', 'stats'] });
                        }}
                    />
                </Suspense>
            )}

            {canScheduleAppointment && scheduleOpen && (
                <Suspense fallback={null}>
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
                </Suspense>
            )}
        </AppLayout>
    );
}
