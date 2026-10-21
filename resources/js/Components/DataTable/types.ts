/**
 * Public vocabulary for the table kit. Kept in one file so a page imports the
 * contract (`RowAction`, `ExportFormat`) without pulling a component in.
 */

export type ExportFormat = 'csv' | 'xlsx' | 'pdf';

export type CrudDialogSize = 'sm' | 'md' | 'lg';

export type RowActionTone = 'default' | 'accent' | 'danger';

export interface RowActionConfirm<T> {
    header: string;
    message: string | ((row: T) => string);
    acceptLabel?: string;
    rejectLabel?: string;
}

/**
 * One row action. `hidden` and `disabled` are functions so a soft-deleted row
 * can expose restore instead of delete (and both can coexist) without the
 * component knowing the domain.
 */
export interface RowAction<T> {
    key: string;
    label: string;
    icon: string;
    tone?: RowActionTone;
    hidden?: (row: T) => boolean;
    disabled?: (row: T) => boolean;
    confirm?: RowActionConfirm<T>;
    action: (row: T) => void | Promise<void>;
}
