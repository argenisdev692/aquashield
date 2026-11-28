/**
 * Confirmation for an in-app navigation that would discard a dirty form.
 *
 * `useUnsavedChangesWarning` covers tab close/reload; this dialog covers the
 * common Inertia visit (Cancel, Back, sidebar). The owning page passes the
 * guard from `lib/leave-guard.ts` so the dialog has no navigation logic of its
 * own.
 */
import CrudDialog from '@/Components/DataTable/CrudDialog';
import type { NavigationGuard } from '@/lib/leave-guard';

interface UnsavedChangesDialogProps {
    guard: NavigationGuard;
}

export default function UnsavedChangesDialog({ guard }: UnsavedChangesDialogProps) {
    return (
        <CrudDialog
            open={guard.pendingUrl !== null}
            onOpenChange={(open) => {
                if (!open) {
                    guard.cancelLeave();
                }
            }}
            title="Discard changes?"
            description="This form has unsaved changes. Leaving now will discard them."
            confirmLabel="Discard changes"
            confirmVariant="destructive"
            onConfirm={guard.confirmLeave}
        >
            <p className="text-sm text-surface-600 dark:text-surface-300">
                Stay on this page to keep editing, or discard the draft and continue.
            </p>
        </CrudDialog>
    );
}
