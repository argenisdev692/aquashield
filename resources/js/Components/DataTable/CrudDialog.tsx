/**
 * CrudDialog — small confirmation dialog for table actions.
 */
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';

interface CrudDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    children: React.ReactNode;
    confirmLabel?: string;
    onConfirm?: () => void;
    loading?: boolean;
    confirmDisabled?: boolean;
    /** Hides the confirm control for a dialog that has already succeeded. */
    confirmHidden?: boolean;
    /** Dismissal copy; `Close` once the action can no longer be re-run. */
    cancelLabel?: string;
    confirmVariant?: 'default' | 'destructive';
}

export default function CrudDialog({
    open,
    onOpenChange,
    title,
    description,
    children,
    confirmLabel = 'Save',
    onConfirm,
    loading = false,
    confirmDisabled = false,
    confirmHidden = false,
    cancelLabel = 'Cancel',
    confirmVariant = 'default',
}: CrudDialogProps) {
    /*
     * A dialog that can be dismissed mid-request turns a later 422 (an
     * assigned role/permission cannot be deleted) into a message nobody sees,
     * because the page clears the target and the error on close.
     */
    const handleOpenChange = (next: boolean): void => {
        if (loading && !next) {
            return;
        }

        onOpenChange(next);
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && <DialogDescription>{description}</DialogDescription>}
                </DialogHeader>
                <div className="py-4">{children}</div>
                <div className="flex justify-end gap-2 pt-4">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => handleOpenChange(false)}
                        disabled={loading}
                    >
                        {cancelLabel}
                    </Button>
                    {confirmHidden ? null : (
                        <Button
                            type="button"
                            variant={confirmVariant}
                            onClick={onConfirm}
                            disabled={loading || confirmDisabled}
                        >
                            {confirmLabel}
                        </Button>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
