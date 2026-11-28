/**
 * The four period metrics at the top of the dashboard.
 *
 * The server owns the numbers, the daily series and the comparison window; this
 * component only maps them to the shared metric card and keeps the destination
 * period-scoped. Extracted from the page so the KPI arithmetic and link
 * contract can be tested without mounting the board, table or calendar.
 */
import { CalendarCheck, CalendarClock, Trophy, UserPlus } from 'lucide-react';
import KpiCard from '@/Components/KpiCard';
import type { TableFilters } from '@/lib/table';
import { dashboardMetricLinks } from '@/Pages/Dashboard/dashboardLinks';
import { periodDelta } from '@/Pages/Dashboard/dashboardMetrics';

type DashboardStats = Modules.LeadAppointment.Application.Dtos.DashboardStatsData;

interface KpiRowProps {
    stats: DashboardStats;
    rangeFrom: string;
    rangeTo: string;
    /** Dashboard region/source scope; used by the drill-down links. */
    filters?: TableFilters;
    canViewLeads: boolean;
    canViewCalendar: boolean;
}

export default function KpiRow({
    stats,
    rangeFrom,
    rangeTo,
    filters = {},
    canViewLeads,
    canViewCalendar,
}: KpiRowProps) {
    const links = dashboardMetricLinks(rangeFrom, rangeTo, filters);
    const leadsDelta = periodDelta(stats.leads);
    const inspectionsDelta = periodDelta(stats.inspections);
    const wonDelta = periodDelta(stats.won);
    const appointmentsDelta = periodDelta(stats.appointments);

    return (
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
                href={canViewLeads ? links.newLeads : undefined}
                icon={<UserPlus className="size-5" aria-hidden="true" />}
            />
            <KpiCard
                label="Inspections"
                value={stats.inspections.current}
                delta={inspectionsDelta.label}
                trend={inspectionsDelta.trend}
                series={stats.inspections.series}
                index={1}
                href={canViewCalendar ? links.inspections : undefined}
                icon={<CalendarCheck className="size-5" aria-hidden="true" />}
            />
            <KpiCard
                label="Won"
                value={stats.won.current}
                delta={wonDelta.label}
                trend={wonDelta.trend}
                series={stats.won.series}
                index={2}
                href={canViewLeads ? links.won : undefined}
                icon={<Trophy className="size-5" aria-hidden="true" />}
            />
            <KpiCard
                label="Appointments"
                value={stats.appointments.current}
                delta={appointmentsDelta.label}
                trend={appointmentsDelta.trend}
                series={stats.appointments.series}
                index={3}
                href={canViewCalendar ? links.appointments : undefined}
                icon={<CalendarClock className="size-5" aria-hidden="true" />}
            />
        </section>
    );
}
