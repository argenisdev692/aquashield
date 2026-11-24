<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Console\Commands;

use Illuminate\Console\Command;
use Modules\LeadAppointment\Application\Actions\SyncHolidaysAction;

final class SyncUsHolidaysCommand extends Command
{
    protected $signature = 'lead-appointment:sync-holidays {--year= : Materialise only this year; omit to resync current + next}';

    protected $description = 'Materialise US federal holidays as company availability exceptions.';

    public function handle(SyncHolidaysAction $action): int
    {
        $year = $this->option('year');
        $result = $action->execute($year === null ? null : (int) $year);

        $this->info(json_encode($result, JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
