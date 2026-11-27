type BackupArchive = Modules.Backups.Data.BackupArchiveData;

export const bulkDeleteError = (rows: readonly BackupArchive[]): string | null => {
    if (rows.length === 0) {
        return 'Select at least one active archive to quarantine.';
    }

    if (rows.some((row) => row.is_trashed)) {
        return 'Quarantine only active archives. Remove quarantined rows from the selection.';
    }

    if (rows.some((row) => row.is_newest)) {
        return 'The newest archive is protected. Remove it from the selection and try again.';
    }

    return null;
};

export const bulkRestoreError = (rows: readonly BackupArchive[]): string | null => {
    if (rows.length === 0) {
        return 'Select at least one quarantined archive to restore.';
    }

    return rows.some((row) => !row.is_trashed)
        ? 'Restore only quarantined archives. Remove active rows from the selection.'
        : null;
};
