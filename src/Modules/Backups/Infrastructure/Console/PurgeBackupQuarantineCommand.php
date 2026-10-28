<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Console;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Modules\Backups\Application\Repositories\BackupArchiveStore;
use Modules\Backups\Domain\Models\BackupArchive;
use Shared\Domain\Ports\AuditPort;

/**
 * The only irreversible deletion path in the module.
 *
 * It reads the quarantine only, so a misconfiguration can never purge an
 * active archive. The age is derived from the dump filename, not from the
 * move date, so the retention window matches the backup it destroys.
 */
final class PurgeBackupQuarantineCommand extends Command
{
    protected $signature = 'backups:purge-quarantine
        {--days= : Override backup.quarantine.retain_days}
        {--dry-run : List candidates without deleting anything}';

    protected $description = 'Permanently delete quarantined database backups whose retention window has expired.';

    public function handle(BackupArchiveStore $archives, AuditPort $audit): int
    {
        $days = $this->days();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $expired = array_values(array_filter(
            $archives->trashed(),
            static fn (BackupArchive $archive): bool => $archive->ageInDays($now) >= $days,
        ));

        if ($expired === []) {
            $this->info("No quarantined backups are older than {$days} day(s).");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($expired as $archive) {
                $this->line("Would purge {$archive->filename} ({$archive->disk}).");
            }

            $this->info(count($expired).' archive(s) would be purged.');

            return self::SUCCESS;
        }

        foreach ($expired as $archive) {
            $archives->purge($archive);

            $audit->log('backup.purged', null, [
                'disk' => $archive->disk,
                'filename' => $archive->filename,
                'size_bytes' => $archive->sizeBytes,
            ], null, 'backups');

            $this->line("Purged {$archive->filename} ({$archive->disk}).");
        }

        $this->info(count($expired)." archive(s) purged after {$days} day(s) in quarantine.");

        return self::SUCCESS;
    }

    private function days(): int
    {
        $days = $this->option('days');

        if (is_numeric($days)) {
            return max(0, (int) $days);
        }

        return max(0, (int) config('backup.quarantine.retain_days', 30));
    }
}
