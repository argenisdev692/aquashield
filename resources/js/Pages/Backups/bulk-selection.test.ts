import { describe, expect, it } from 'vitest';
import { bulkDeleteError, bulkRestoreError } from './bulk-selection';

const archive = (
    uuid: string,
    overrides: Partial<Modules.Backups.Data.BackupArchiveData> = {},
): Modules.Backups.Data.BackupArchiveData => ({
    uuid,
    disk: 'local',
    filename: `${uuid}.zip`,
    size_bytes: 1024,
    size_human: '1.0 KB',
    taken_at: '2026-11-26T00:00:00+00:00',
    age_days: 1,
    is_newest: false,
    is_trashed: false,
    ...overrides,
});

describe('Backup bulk selection', () => {
    it('accepts active non-newest archives for quarantine', () => {
        expect(bulkDeleteError([archive('active')])).toBeNull();
    });

    it('rejects quarantined rows in a delete selection', () => {
        expect(bulkDeleteError([archive('active'), archive('trashed', { is_trashed: true })])).toBe(
            'Quarantine only active archives. Remove quarantined rows from the selection.',
        );
    });

    it('rejects the protected newest archive', () => {
        expect(bulkDeleteError([archive('newest', { is_newest: true })])).toBe(
            'The newest archive is protected. Remove it from the selection and try again.',
        );
    });

    it('accepts quarantined archives for restore', () => {
        expect(bulkRestoreError([archive('trashed', { is_trashed: true })])).toBeNull();
    });

    it('rejects active rows in a restore selection', () => {
        expect(
            bulkRestoreError([archive('active'), archive('trashed', { is_trashed: true })]),
        ).toBe('Restore only quarantined archives. Remove active rows from the selection.');
    });
});
