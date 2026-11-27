/**
 * StickyFormBar — bottom action bar for long forms.
 *
 * Shows a one-line summary of what is about to be saved, the dirty/saved state
 * (text + dot, never colour alone) and the primary/secondary actions, pinned to
 * the viewport bottom. `z-[70]` clears the shell's cursor orb (z-60), the same
 * constraint CompanyData's inline bar documents.
 */
import { Button } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import AppButton from './AppButton';

interface StickyFormBarProps {
    /** One line describing the record or the pending operation. */
    summary: string;
    isDirty: boolean;
    processing: boolean;
    submitLabel?: string;
    cancelLabel?: string;
    onCancel?: () => void;
    onDiscard?: () => void;
    discardLabel?: string;
    idleLabel?: string;
    idleTone?: 'success' | 'neutral';
    processingLabel?: string;
    disabled?: boolean;
    className?: string;
}

export default function StickyFormBar({
    summary,
    isDirty,
    processing,
    submitLabel = 'Save changes',
    cancelLabel,
    onCancel,
    onDiscard,
    discardLabel = 'Discard',
    idleLabel = 'All changes saved',
    idleTone = 'success',
    processingLabel = 'Saving changes…',
    disabled = false,
    className,
}: StickyFormBarProps) {
    const status = processing ? processingLabel : isDirty ? 'Unsaved changes' : idleLabel;

    const tone = processing
        ? 'bg-aq-accent'
        : isDirty
          ? 'bg-aq-warning'
          : idleTone === 'success'
            ? 'bg-aq-success'
            : 'bg-surface-400';

    return (
        <div
            className={cn(
                'sticky bottom-0 z-[70] flex flex-col gap-3 rounded-2xl border border-aq-shell-line bg-surface-0/95 px-4 py-3 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between dark:bg-surface-900/95',
                className,
            )}
        >
            <div className="min-w-0">
                <p className="truncate text-sm font-medium text-surface-800 dark:text-surface-100">
                    {summary}
                </p>
                <p
                    aria-live="polite"
                    className="mt-0.5 flex items-center gap-2 text-xs text-surface-600 dark:text-surface-300"
                >
                    <span className={cn('size-2 shrink-0 rounded-full', tone)} aria-hidden="true" />
                    {status}
                </p>
            </div>

            {/* Secondary actions pin AppButton's h-11 action height — the same
                pairing FormActions and FormOverlay use. */}
            <div className="flex flex-col gap-2 sm:flex-row">
                {onDiscard ? (
                    <Button
                        type="button"
                        variant="outline"
                        disabled={!isDirty || processing}
                        onClick={onDiscard}
                        className="h-11 min-w-28 rounded-md"
                    >
                        {discardLabel}
                    </Button>
                ) : null}

                {cancelLabel && onCancel ? (
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={onCancel}
                        className="h-11 min-w-28 rounded-md"
                    >
                        {cancelLabel}
                    </Button>
                ) : null}

                <AppButton
                    type="submit"
                    loading={processing}
                    disabled={disabled || !isDirty}
                    className="min-w-28"
                >
                    {submitLabel}
                </AppButton>
            </div>
        </div>
    );
}
