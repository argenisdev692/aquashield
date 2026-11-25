/**
 * FormDialog / FormSheet — form-aware shells around the Base UI dialog and
 * sheet primitives.
 *
 * They own the native `<form>`, the submit/cancel row and the dirty-close guard.
 * The page still owns the schema, the fields and the actual Inertia call; the
 * `form` prop is the object returned by `useZodForm` (structurally typed so the
 * shell does not depend on its generics).
 */
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/Components/ui/dialog';
import { Sheet, SheetContent } from '@/Components/ui/sheet';
import { cn } from '@/lib/utils';
import AppButton from './AppButton';
import FormErrorSummary from './FormErrorSummary';

export interface FormOverlayState {
    processing: boolean;
    isDirty: boolean;
}

export interface FormOverlayProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    form: FormOverlayState;
    errors?: Record<string, string | undefined>;
    onSubmit: () => void;
    submitLabel?: string;
    cancelLabel?: string;
    guardDirty?: boolean;
    /** Keep submit disabled until the form has a meaningful change. */
    submitDisabled?: boolean;
    destructive?: boolean;
    /** Width override for a longer form (the lead modal); defaults to `sm:max-w-lg`. */
    contentClassName?: string;
    children: React.ReactNode;
}

function FormBody({
    title,
    description,
    form,
    errors,
    onSubmit,
    submitLabel,
    cancelLabel,
    submitDisabled,
    destructive,
    children,
    onRequestClose,
}: Omit<FormOverlayProps, 'open' | 'onOpenChange' | 'guardDirty' | 'contentClassName'> & {
    onRequestClose: () => void;
}) {
    const busy = form.processing;

    return (
        <form
            className="flex min-h-0 flex-col gap-4"
            noValidate
            onSubmit={(event) => {
                event.preventDefault();
                onSubmit();
            }}
        >
            <div className="flex flex-col gap-2 pr-8">
                <DialogTitle className="font-heading text-xl leading-tight font-semibold tracking-tight text-surface-900 dark:text-surface-0">
                    {title}
                </DialogTitle>
                {description && (
                    <DialogDescription className="text-sm leading-relaxed text-surface-500 dark:text-surface-400">
                        {description}
                    </DialogDescription>
                )}
            </div>

            {errors && <FormErrorSummary errors={errors} />}

            <div className="max-h-[60vh] space-y-3 overflow-y-auto pr-1">{children}</div>

            <div className="flex flex-col-reverse gap-2 border-t pt-4 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    onClick={onRequestClose}
                    disabled={busy}
                    className="h-11 min-w-28 rounded-md"
                >
                    {cancelLabel ?? 'Cancel'}
                </Button>
                <AppButton
                    type="submit"
                    loading={busy}
                    disabled={busy || submitDisabled}
                    className={cn(
                        'min-w-28',
                        destructive ? 'border-aq-danger bg-aq-danger text-surface-0' : undefined,
                    )}
                >
                    {submitLabel ?? 'Save'}
                </AppButton>
            </div>
        </form>
    );
}

export function FormDialog(props: FormOverlayProps) {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const { open, onOpenChange, form, guardDirty = true, contentClassName } = props;

    const requestClose = () => {
        if (form.processing) {
            return;
        }

        if (guardDirty && form.isDirty) {
            setConfirmOpen(true);

            return;
        }

        onOpenChange(false);
    };

    return (
        <>
            <Dialog
                open={open}
                onOpenChange={(next) => (next ? onOpenChange(true) : requestClose())}
            >
                <DialogContent
                    className={cn('sm:max-w-lg', contentClassName)}
                    showCloseButton={!form.processing}
                >
                    <FormBody {...props} onRequestClose={requestClose} />
                </DialogContent>
            </Dialog>

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent className="sm:max-w-sm">
                    <div className="flex flex-col gap-1.5">
                        <DialogTitle>Discard changes?</DialogTitle>
                        <DialogDescription>
                            This form has unsaved changes. Closing it will discard them.
                        </DialogDescription>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={() => setConfirmOpen(false)}>
                            Keep editing
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                setConfirmOpen(false);
                                onOpenChange(false);
                            }}
                        >
                            Discard
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}

export function FormSheet(props: Omit<FormOverlayProps, 'contentClassName'>) {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const { open, onOpenChange, form, guardDirty = true } = props;

    const requestClose = () => {
        if (form.processing) {
            return;
        }

        if (guardDirty && form.isDirty) {
            setConfirmOpen(true);

            return;
        }

        onOpenChange(false);
    };

    return (
        <>
            <Sheet
                open={open}
                onOpenChange={(next) => (next ? onOpenChange(true) : requestClose())}
            >
                <SheetContent
                    side="right"
                    className="w-full sm:max-w-lg"
                    showCloseButton={!form.processing}
                >
                    <div className="flex min-h-0 flex-1 flex-col p-4">
                        <FormBody {...props} onRequestClose={requestClose} />
                    </div>
                </SheetContent>
            </Sheet>

            <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <DialogContent className="sm:max-w-sm">
                    <div className="flex flex-col gap-1.5">
                        <DialogTitle>Discard changes?</DialogTitle>
                        <DialogDescription>
                            This form has unsaved changes. Closing it will discard them.
                        </DialogDescription>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button variant="outline" onClick={() => setConfirmOpen(false)}>
                            Keep editing
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => {
                                setConfirmOpen(false);
                                onOpenChange(false);
                            }}
                        >
                            Discard
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
