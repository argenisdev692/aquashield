export type DashboardView = 'kanban' | 'table' | 'calendar';

export type Option = { value: string; label: string };

export type LeadCard = Modules.LeadAppointment.Application.Dtos.LeadCardData;

export interface ScheduleLead {
    uuid: string;
    name: string;
    phone: string | null;
    region: string | null;
}
