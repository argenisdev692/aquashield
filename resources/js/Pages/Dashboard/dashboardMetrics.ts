/**
 * Pure KPI arithmetic for the dashboard row.
 *
 * The chip's copy is derived from the two server counts, never from a prop that
 * asserts a direction. Percent only when there is a base to divide by;
 * otherwise the absolute change is the honest number. A rounded 0% with a real
 * difference falls back to the count, so "+1" never renders as "0%".
 *
 * Two zero counts produce a neutral state, not a comparison: "same as previous
 * period" on a fresh workspace is noise dressed as a delta.
 */
type DashboardMetric = Modules.LeadAppointment.Application.Dtos.DashboardMetricData;

export type DeltaDirection = 'up' | 'down' | 'flat';

export interface PeriodDelta {
    trend: DeltaDirection;
    label: string;
}

export const periodDelta = ({ current, previous }: DashboardMetric): PeriodDelta => {
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
