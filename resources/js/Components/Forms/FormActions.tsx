/**
 * FormActions — primary + secondary action row for forms.
 * Typically Submit + Cancel.
 */
import { Button } from '@/Components/ui/button';
import AppButton from './AppButton';
import { cn } from '@/lib/utils';

interface FormActionsProps {
    submitLabel?: string;
    cancelLabel?: string;
    onCancel?: () => void;
    loading?: boolean;
    disabled?: boolean;
    className?: string;
}

export default function FormActions({
    submitLabel = 'Save',
    cancelLabel,
    onCancel,
    loading = false,
    disabled = false,
    className,
}: FormActionsProps) {
    return (
        <div className={cn('flex flex-col sm:flex-row sm:justify-end gap-3', className)}>
            {cancelLabel && onCancel && (
                <Button
                    type="button"
                    variant="outline"
                    onClick={onCancel}
                    disabled={loading || disabled}
                    className="h-11 min-w-28 rounded-md"
                >
                    {cancelLabel}
                </Button>
            )}
            <AppButton type="submit" loading={loading} disabled={disabled} className="min-w-28">
                {submitLabel}
            </AppButton>
        </div>
    );
}
