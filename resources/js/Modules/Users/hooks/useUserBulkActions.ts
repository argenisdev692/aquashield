/**
 * Bulk operations for the Users table.
 *
 * All five endpoints answer the same `{ updated, skipped }` summary, so the
 * page can report what actually happened instead of assuming every selected row
 * was eligible. The request is a plain JSON call, not an Inertia visit: the
 * table stays mounted and the current URL (filters, sort, page) is preserved.
 *
 * Feedback is lifted to the page through `onFeedback`. It cannot live in the
 * bulk bar: a successful action reloads `rows`, the kit clears the selection,
 * and the bar (with any message inside it) unmounts before the user can read it.
 */
import { useState } from 'react';
import { router } from '@inertiajs/react';
import { HttpRequestError, requestJson } from '@/lib/http';

/** Generated from `Modules\Users\Data\BulkSkipData` — never hand-written. */
export type BulkSkip = Modules.Users.Data.BulkSkipData;

/** Generated from `Modules\Users\Data\BulkActionResultData` — never hand-written. */
export type BulkResult = Modules.Users.Data.BulkActionResultData;

export interface BulkFeedback {
    tone: 'success' | 'error';
    message: string;
}

interface BulkRow {
    uuid: string;
}

interface BulkAction {
    singular: string;
    plural: string;
    target: string;
    verb: string;
    pastTense: string;
    failure: string;
}

const ACTIONS = {
    activate: {
        singular: 'account',
        plural: 'accounts',
        target: 'accounts',
        verb: 'activate',
        pastTense: 'activated',
        failure: 'No accounts were activated.',
    },
    suspend: {
        singular: 'account',
        plural: 'accounts',
        target: 'accounts',
        verb: 'suspend',
        pastTense: 'suspended',
        failure: 'No accounts were suspended.',
    },
    assignRole: {
        singular: 'account',
        plural: 'accounts',
        target: 'accounts',
        verb: 'update',
        pastTense: 'updated with the new role',
        failure: 'No accounts were updated.',
    },
    resendInvitations: {
        singular: 'invitation',
        plural: 'invitations',
        target: 'accounts',
        verb: 'send invitations to',
        pastTense: 'sent',
        failure: 'No invitations were sent.',
    },
    delete: {
        singular: 'account',
        plural: 'accounts',
        target: 'accounts',
        verb: 'delete',
        pastTense: 'deleted',
        failure: 'No accounts were deleted.',
    },
    restore: {
        singular: 'account',
        plural: 'accounts',
        target: 'accounts',
        verb: 'restore',
        pastTense: 'restored',
        failure: 'No accounts were restored.',
    },
} as const satisfies Record<string, BulkAction>;

interface UseUserBulkActionsOptions {
    onFeedback?: (feedback: BulkFeedback) => void;
}

interface UseUserBulkActions {
    processing: boolean;
    changeStatus: (rows: readonly BulkRow[], status: 'active' | 'suspended') => void;
    assignRole: (rows: readonly BulkRow[], role: string) => void;
    resendInvitations: (rows: readonly BulkRow[]) => void;
    deleteRows: (rows: readonly BulkRow[]) => void;
    restoreRows: (rows: readonly BulkRow[]) => void;
}

const RELOAD_PROPS = [
    'rows',
    'total',
    'currentPage',
    'perPage',
    'filters',
    'activeSuperAdminCount',
];

const isBulkResult = (value: unknown): value is BulkResult =>
    typeof value === 'object' &&
    value !== null &&
    'updated' in value &&
    typeof value.updated === 'number' &&
    'skipped' in value &&
    Array.isArray(value.skipped);

const plural = (count: number, singular: string, pluralForm: string): string =>
    `${count} ${count === 1 ? singular : pluralForm}`;

const skipSuffix = (result: BulkResult): string => {
    if (result.skipped.length === 0) {
        return '';
    }

    const first = result.skipped[0];

    return ` ${result.skipped.length} skipped — ${first.reason}`;
};

const feedbackFor = (result: BulkResult, action: BulkAction): BulkFeedback => {
    const skipped = skipSuffix(result);

    if (result.updated === 0) {
        return { tone: 'error', message: `${action.failure}${skipped}` };
    }

    return {
        tone: 'success',
        message: `${plural(result.updated, action.singular, action.plural)} ${action.pastTense}.${skipped}`,
    };
};

const errorMessage = (cause: unknown, action: BulkAction): string => {
    if (cause instanceof HttpRequestError && cause.status === 429) {
        return 'Too many attempts. Wait a minute and try again.';
    }

    const reason =
        cause instanceof HttpRequestError
            ? (Object.values(cause.fieldErrors)[0] ?? cause.message)
            : cause instanceof Error
              ? cause.message
              : 'The request could not be completed. Try again.';

    return `Could not ${action.verb} the selected ${action.target}. ${reason}`;
};

export function useUserBulkActions({
    onFeedback,
}: UseUserBulkActionsOptions = {}): UseUserBulkActions {
    const [processing, setProcessing] = useState(false);

    const run = async (
        url: string,
        rows: readonly BulkRow[],
        action: BulkAction,
        extra: Record<string, string> = {},
    ): Promise<void> => {
        if (rows.length === 0) {
            return;
        }

        setProcessing(true);

        try {
            const payload = await requestJson(url, {
                method: 'POST',
                body: {
                    uuids: rows.map((row) => row.uuid),
                    ...extra,
                },
            });

            /*
             * The five endpoints answer `{ updated, skipped }`, and that summary
             * IS the result. Falling back to `updated: rows.length` for an
             * unrecognised body reported success for work the server never
             * confirmed (a 204, a proxy's empty reply, a future shape change).
             * Report the uncertainty and reload: the refreshed rows are the
             * only honest source of what actually happened.
             */
            if (!isBulkResult(payload)) {
                onFeedback?.({
                    tone: 'error',
                    message: `${action.failure} The server did not confirm the result. The table was refreshed — check the rows before retrying.`,
                });

                router.reload({
                    only: RELOAD_PROPS,
                });

                return;
            }

            onFeedback?.(feedbackFor(payload, action));

            // Reload the props the table renders; the URL (and therefore the
            // active filters) stays exactly where the user left it.
            router.reload({
                only: RELOAD_PROPS,
            });
        } catch (cause) {
            onFeedback?.({ tone: 'error', message: errorMessage(cause, action) });
        } finally {
            setProcessing(false);
        }
    };

    return {
        processing,
        changeStatus: (rows, status) => {
            void run(
                '/users/bulk-status',
                rows,
                status === 'active' ? ACTIONS.activate : ACTIONS.suspend,
                { status },
            );
        },
        assignRole: (rows, role) => {
            void run('/users/bulk-role', rows, ACTIONS.assignRole, { role });
        },
        resendInvitations: (rows) => {
            void run('/users/bulk-invite', rows, ACTIONS.resendInvitations);
        },
        deleteRows: (rows) => {
            void run('/users/bulk-delete', rows, ACTIONS.delete);
        },
        restoreRows: (rows) => {
            void run('/users/bulk-restore', rows, ACTIONS.restore);
        },
    };
}
