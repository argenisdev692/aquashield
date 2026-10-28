export type ActivitySeverity = 'success' | 'info' | 'warn' | 'danger' | 'secondary';

/**
 * Event names are verbs (`backup.quarantined`, `user.deleted`). Severity is a
 * presentation hint derived from the verb, never a substitute for reading it.
 */
export function eventSeverity(event: string): ActivitySeverity {
    if (event.includes('failed')) {
        return 'danger';
    }

    if (
        event.includes('quarantined') ||
        event.includes('deleted') ||
        event.includes('purged') ||
        event.includes('reset')
    ) {
        return 'warn';
    }

    if (event.includes('restored') || event.includes('completed') || event.includes('created')) {
        return 'success';
    }

    return 'info';
}
