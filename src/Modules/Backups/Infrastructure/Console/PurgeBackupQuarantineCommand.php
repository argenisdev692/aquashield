<?php

declare(strict_types=1);

namespace Modules\Backups\Infrastructure\Console;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Modules\Backups\Application\Ports\BackupArchiveStore;
use Modules\Backups\Domain\Exceptions\BackupDestinationUnavailable;
use Modules\Backups\Domain\Models\BackupArchive;
use Shared\Domain\Ports\AuditPort;
use Throwable;

/**
 * The only irreversible deletion path in the module.
 *
 * It reads the quarantine only, so a misconfiguration can never purge an
 * active archive. The age is derived from the dump filename, not from the
 * move date, so the retention window matches the backup it destroys.
 */
final class PurgeBackupQuarantineCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'backups:purge-quarantine
        {--days= : Override backup.quarantine.retain_days}
        {--dry-run : List candidates without deleting anything}
        {--force : Run without the production confirmation}';

    protected $description = 'Permanently delete quarantined database backups whose retention window has expired.';

    public function handle(BackupArchiveStore $archives, AuditPort $audit): int
    {
        if (! $this->option('dry-run') && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        // `--days=0` used to mean "purge everything in quarantine". The
        // irreversible path should not accept that by accident.
        if (! $this->validDaysOption()) {
            return self::FAILURE;
        }

        $days = $this->days();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        try {
            $quarantined = $archives->trashed();
        } catch (BackupDestinationUnavailable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $expired = array_values(array_filter(
            $quarantined,
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

        $failed = 0;

        foreach ($expired as $archive) {
            try {
                $archives->purge($archive);
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
                $this->error('Could not purge '.$archive->filename.' ('.$archive->disk.'): '.$exception::class.'.');

                continue;
            }

            try {
                $audit->log('backup.purged', null, [
                    'disk' => $archive->disk,
                    'filename' => $archive->filename,
                    'size_bytes' => $archive->sizeBytes,
                ], null, 'backups');
            } catch (Throwable $exception) {
                // The archive is gone; a failed audit is reported, not a
                // reason to leave the remaining expired archives in place.
                report($exception);
            }

            $this->line("Purged {$archive->filename} ({$archive->disk}).");
        }

        if ($failed > 0) {
            $this->error("{$failed} archive(s) could not be purged.");

            return self::FAILURE;
        }

        $this->info(count($expired)." archive(s) purged after {$days} day(s) in quarantine.");

        return self::SUCCESS;
    }

    private function validDaysOption(): bool
    {
        $days = $this->option('days');

        if ($days === null) {
            return true;
        }

        if (filter_var($days, FILTER_VALIDATE_INT) === false || (int) $days < 1) {
            $this->error('The --days option must be a positive integer.');

            return false;
        }

        return true;
    }

    private function days(): int
    {
        $days = $this->option('days');

        if (filter_var($days, FILTER_VALIDATE_INT) !== false) {
            return max(1, (int) $days);
        }

        return max(1, (int) config('backup.quarantine.retain_days', 30));
    }
}
