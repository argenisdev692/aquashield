/**
 * Appointment vocabulary shared by the list, the file and the edit form.
 * Status transitions stay on the server; these predicates only decide which
 * action a screen may offer.
 */

export { humanize } from '@/lib/utils';

export type StatusTone = 'success' | 'warning' | 'destructive' | 'outline';

export const statusVariant = (status: string): StatusTone => {
    if (status === 'completed') {
        return 'success';
    }

    if (status === 'cancelled' || status === 'no_show') {
        return 'destructive';
    }

    if (status === 'requested' || status === 'scheduled' || status === 'confirmed') {
        return 'warning';
    }

    return 'outline';
};

export const canConfirm = (status: string): boolean => status === 'scheduled';

export const canComplete = (status: string): boolean =>
    status === 'confirmed' || status === 'on_site';

export const canCancel = (status: string): boolean =>
    status === 'requested' ||
    status === 'scheduled' ||
    status === 'confirmed' ||
    status === 'en_route' ||
    status === 'on_site';

export const canReschedule = (status: string): boolean =>
    status === 'requested' || status === 'scheduled' || status === 'confirmed';
