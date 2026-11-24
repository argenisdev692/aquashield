/**
 * Dashboard — the real operations view.
 *
 * Server props only: the lead columns come from the same `LeadRepository::board`
 * used by `/leads/board`, and the calendar is the current month of the
 * `appointments` table. There is no mock row and no second pipeline state in
 * the browser.
 */
import { lazy, Suspense, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CalendarCheck,
    CalendarClock,
    CalendarPlus,
    KanbanSquare,
    Plus,
    Table2,
    Trophy,
} from 'lucide-react';
import KpiCard from '@/Components/KpiCard';
import PasskeyPrompt from '@/Pages/Dashboard/components/PasskeyPrompt';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import { Tabs, TabsList, TabsPanel, TabsTab } from '@/Components/ui/tabs';
import { Tag } from '@/Components/ui/tag';
import AppLayout from '@/Layouts/AppLayout';
import { usePasskeyPrompt } from '@/Modules/Auth/hooks/usePasskeyPrompt';
import LeadKanban from '@/Modules/LeadAppointment/components/LeadKanban';
import ScheduleAppointmentDialog from '@/Modules/LeadAppointment/components/ScheduleAppointmentDialog';
import LeadCreateDialog from '@/Pages/Dashboard/components/LeadCreateDialog';
import { formatDateTime } from '@/lib/date';
import { useTheme } from '@/lib/theme';
import type { CalendarAppointment } from '@/Pages/Dashboard/BoardCalendar';

const BoardCalendar = lazy(() => import('@/Pages/Dashboard/BoardCalendar'));

type LeadCard = Modules.LeadAppointment.Application.Dtos.LeadCardData;
type LeadBoardColumn = Modules.LeadAppointment.Application.Dtos.LeadBoardColumnData;
type Appointment = Modules.LeadAppointment.Application.Dtos.AppointmentData;

interface StatComparison {
    current: number;
    previous: number;
}

interface DashboardProps {
    [key: string]: unknown;
    columns: LeadBoardColumn[];
    transitions: Record<string, string[]>;
    appointments: Appointment[];
    stats: {
        leads: StatComparison;
        inspections: StatComparison;
        won: StatComparison;
        appointments: StatComparison;
    };
    canCreate: boolean;
    canUpdate: boolean;
    canScheduleAppointment: boolean;
    options: {
        kinds: { value: string; label: string }[];
        regions: { value: string; label: string }[];
    };
    leadOptions: {
        regions: { value: string; label: string }[];
        owners: Modules.LeadAppointment.Application.Dtos.AssignableOwnerData[];
    };
    googleMapsApiKey: string;
    defaultDuration: number;
    defaultTimezone: string;
    passkeyPrompt: boolean | null;
    errors: Record<string, string>;
}

const humanize = (value: string): string => {
    const label = value.replace(/_/g, ' ');
    return label.charAt(0).toUpperCase() + label.slice(1);
};

type DeltaDirection = 'up' | 'down' | 'flat';

/**
 * The chip's copy is derived from the two server counts, never from a prop that
 * asserts a direction. Percent only when there is a base to divide by;
 * otherwise the absolute change is the honest number. A rounded 0% with a real
 * difference falls back to the count, so "+1" never renders as "0%".
 */
const monthDelta = ({
    current,
    previous,
}: StatComparison): { trend: DeltaDirection; label: string } => {
    if (current === previous) {
        return { trend: 'flat', label: 'same as last month' };
    }

    const difference = current - previous;
    const trend: DeltaDirection = difference > 0 ? 'up' : 'down';
    const absolute = `${difference > 0 ? '+' : ''}${difference} vs. last month`;

    if (previous === 0) {
        return { trend, label: absolute };
    }

    const percent = Math.round((difference / previous) * 100);

    // A rounded -100% with a non-zero current count hides the remaining leads
    // (e.g. 300 → 1). Show the real count instead of a loss that never happened.
    return percent === 0 || (percent === -100 && current > 0)
        ? { trend, label: absolute }
        : { trend, label: `${percent > 0 ? '+' : ''}${percent}% vs. last month` };
};

export default function Dashboard() {
    const {
        columns,
        transitions,
        appointments,
        stats,
        canCreate,
        canUpdate,
        canScheduleAppointment,
        options,
        leadOptions,
        googleMapsApiKey,
        defaultDuration,
        defaultTimezone,
        passkeyPrompt,
        errors,
    } = usePage<DashboardProps>().props;
    const prompt = usePasskeyPrompt();
    const { isDark } = useTheme();

    const [view, setView] = useState<'kanban' | 'calendar'>('kanban');
    const [selectedLead, setSelectedLead] = useState<LeadCard | null>(null);
    const [selectedAppointment, setSelectedAppointment] = useState<Appointment | null>(null);
    const [leadCreateOpen, setLeadCreateOpen] = useState(false);
    const [scheduleOpen, setScheduleOpen] = useState(false);
    const [scheduleDate, setScheduleDate] = useState('');
    const [scheduleLead, setScheduleLead] = useState<{
        uuid: string;
        name: string;
        phone: string | null;
        region: string | null;
    } | null>(null);

    const drawerOpen = selectedLead !== null || selectedAppointment !== null;

    const openSchedule = (
        date: string,
        lead: { uuid: string; name: string; phone: string | null; region: string | null } | null,
    ): void => {
        setScheduleDate(date);
        setScheduleLead(lead);
        setScheduleOpen(true);
    };

    const calendarAppointments: CalendarAppointment[] = appointments.map((appointment) => ({
        id: appointment.uuid,
        title: appointment.customer_name,
        start: new Date(appointment.starts_at_local),
        tone: 'normal',
    }));

    const openAppointment = (uuid: string): void => {
        const appointment = appointments.find((row) => row.uuid === uuid) ?? null;
        setSelectedAppointment(appointment);
        setSelectedLead(null);
    };

    const leadsDelta = monthDelta(stats.leads);
    const inspectionsDelta = monthDelta(stats.inspections);
    const wonDelta = monthDelta(stats.won);
    const appointmentsDelta = monthDelta(stats.appointments);

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="flex flex-col gap-6">
                {/*
                 * The shell's <h1> already says "Dashboard", so this page owns
                 * only the description and the actions — a second heading here
                 * read as a second page title, not a section.
                 */}
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <p className="max-w-prose text-sm text-surface-500 dark:text-surface-400">
                        Live lead pipeline and scheduled inspections.
                    </p>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            render={<Link href="/leads" />}
                            nativeButton={false}
                            variant="outline"
                            className="h-9 gap-2"
                        >
                            <Table2 className="size-4" aria-hidden="true" />
                            Table view
                        </Button>
                        {canCreate && (
                            <Button className="h-9 gap-2" onClick={() => setLeadCreateOpen(true)}>
                                <Plus className="size-4" aria-hidden="true" />
                                New lead
                            </Button>
                        )}
                    </div>
                </header>

                {passkeyPrompt === true && prompt.visible ? (
                    <PasskeyPrompt
                        pending={prompt.pending}
                        error={prompt.error}
                        onDismiss={() => void prompt.dismiss()}
                    />
                ) : null}

                {/*
                 * Month-over-month, same scope on every card and every chip.
                 * The board below is the live pipeline snapshot, so repeating
                 * its column totals here would say the same thing twice. The
                 * values are this calendar month (company timezone) and the
                 * chips are computed from the previous month's counts.
                 */}
                <section
                    aria-label="Key metrics"
                    className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <KpiCard
                        label="New leads"
                        value={stats.leads.current}
                        delta={leadsDelta.label}
                        trend={leadsDelta.trend}
                        index={0}
                        icon={<KanbanSquare className="size-5" aria-hidden="true" />}
                    />
                    <KpiCard
                        label="Inspection scheduled"
                        value={stats.inspections.current}
                        delta={inspectionsDelta.label}
                        trend={inspectionsDelta.trend}
                        index={1}
                        icon={<CalendarCheck className="size-5" aria-hidden="true" />}
                    />
                    <KpiCard
                        label="Won"
                        value={stats.won.current}
                        delta={wonDelta.label}
                        trend={wonDelta.trend}
                        index={2}
                        icon={<Trophy className="size-5" aria-hidden="true" />}
                    />
                    <KpiCard
                        label="Appointments"
                        value={stats.appointments.current}
                        delta={appointmentsDelta.label}
                        trend={appointmentsDelta.trend}
                        index={3}
                        icon={<CalendarClock className="size-5" aria-hidden="true" />}
                    />
                </section>

                <section
                    id="pipeline"
                    aria-label="Lead pipeline and inspection calendar"
                    className="scroll-mt-24 overflow-hidden rounded-xl border border-surface-200 bg-surface-0 dark:border-surface-800 dark:bg-surface-900"
                >
                    <Tabs
                        value={view}
                        onValueChange={(value) => setView(value as 'kanban' | 'calendar')}
                    >
                        <TabsList>
                            <TabsTab value="kanban">
                                <span className="flex items-center gap-2">
                                    <KanbanSquare className="size-3" aria-hidden="true" />
                                    Kanban
                                </span>
                            </TabsTab>
                            <TabsTab value="calendar">
                                <span className="flex items-center gap-2">
                                    <CalendarClock className="size-3" aria-hidden="true" />
                                    Calendar
                                </span>
                            </TabsTab>
                        </TabsList>

                        <TabsPanel value="kanban">
                            <LeadKanban
                                columns={columns}
                                transitions={transitions}
                                canUpdate={canUpdate}
                                errors={errors}
                                onSelect={(lead) => {
                                    setSelectedLead(lead);
                                    setSelectedAppointment(null);
                                }}
                            />
                        </TabsPanel>

                        <TabsPanel value="calendar">
                            <Suspense
                                fallback={
                                    <div className="p-4" aria-busy="true">
                                        <Skeleton className="mb-3 h-8 w-56" />
                                        <div className="grid grid-cols-7 gap-2">
                                            {Array.from({ length: 35 }, (_, index) => (
                                                <Skeleton key={index} className="h-20 rounded-lg" />
                                            ))}
                                        </div>
                                    </div>
                                }
                            >
                                <BoardCalendar
                                    appointments={calendarAppointments}
                                    colorScheme={isDark ? 'dark' : 'light'}
                                    onSelect={openAppointment}
                                    onDateClick={
                                        canScheduleAppointment
                                            ? (date) => openSchedule(date, null)
                                            : undefined
                                    }
                                />
                            </Suspense>
                        </TabsPanel>
                    </Tabs>
                </section>
            </div>

            <Sheet
                open={drawerOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setSelectedLead(null);
                        setSelectedAppointment(null);
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
                                <Button
                                    render={<Link href={`/leads/${selectedLead.uuid}`} />}
                                    nativeButton={false}
                                    variant="outline"
                                    className="w-full"
                                >
                                    Open lead
                                </Button>
                            </div>
                        </>
                    )}

                    {selectedAppointment !== null && (
                        <>
                            <SheetHeader>
                                <SheetTitle className="text-lg font-bold text-surface-900 dark:text-surface-0">
                                    {selectedAppointment.customer_name}
                                </SheetTitle>
                                <SheetDescription>
                                    {humanize(selectedAppointment.kind)} ·{' '}
                                    {humanize(selectedAppointment.status)}
                                </SheetDescription>
                            </SheetHeader>

                            <div className="mt-4 flex flex-col gap-3 px-4 text-sm text-surface-600 dark:text-surface-300">
                                <p>
                                    <span className="font-semibold text-surface-900 dark:text-surface-0">
                                        When:
                                    </span>{' '}
                                    {formatDateTime(selectedAppointment.starts_at_local, 'en-US', {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                        timeZone: selectedAppointment.timezone,
                                    })}{' '}
                                    –{' '}
                                    {formatDateTime(selectedAppointment.ends_at_local, 'en-US', {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                        timeZone: selectedAppointment.timezone,
                                    })}
                                </p>
                                <p>
                                    <span className="font-semibold text-surface-900 dark:text-surface-0">
                                        Timezone:
                                    </span>{' '}
                                    {selectedAppointment.timezone}
                                </p>
                                <p>
                                    <span className="font-semibold text-surface-900 dark:text-surface-0">
                                        Property:
                                    </span>{' '}
                                    {selectedAppointment.address_line || '—'}
                                </p>
                                {selectedAppointment.notes !== null && (
                                    <p>
                                        <span className="font-semibold text-surface-900 dark:text-surface-0">
                                            Notes:
                                        </span>{' '}
                                        {selectedAppointment.notes}
                                    </p>
                                )}
                                {selectedAppointment.cancel_reason !== null && (
                                    <p>
                                        <span className="font-semibold text-surface-900 dark:text-surface-0">
                                            Cancelled:
                                        </span>{' '}
                                        {selectedAppointment.cancel_reason}
                                    </p>
                                )}
                            </div>

                            <div className="mt-6 flex flex-col gap-2 px-4">
                                <Button
                                    render={
                                        <Link href={`/appointments/${selectedAppointment.uuid}`} />
                                    }
                                    nativeButton={false}
                                    className="w-full"
                                >
                                    Open appointment
                                </Button>
                                <Button
                                    render={
                                        <Link href={`/leads/${selectedAppointment.lead_uuid}`} />
                                    }
                                    nativeButton={false}
                                    variant="outline"
                                    className="w-full"
                                >
                                    Open lead
                                </Button>
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
                        router.reload({ only: ['appointments', 'columns', 'stats'] });
                    }}
                />
            )}
        </AppLayout>
    );
}
