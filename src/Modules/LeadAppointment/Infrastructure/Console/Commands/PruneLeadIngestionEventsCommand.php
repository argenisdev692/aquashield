<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\LeadIngestionEvent;

final class PruneLeadIngestionEventsCommand extends Command
{
    protected $signature = 'lead-appointment:prune-ingestion-events
        {--days=30 : Retain processed ingestion events for this many days}';

    protected $description = 'Prune processed Meta ingestion events so their PII payload does not live forever.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = CarbonImmutable::now()->subDays($days);

        $deleted = LeadIngestionEvent::query()
            ->whereNotNull('processed_at')
            ->where('processed_at', '<', $cutoff)
            ->delete();

        $this->info("Pruned {$deleted} processed lead ingestion event(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
