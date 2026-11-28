<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Infrastructure\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\LeadAppointment\Application\Actions\IngestMetaLeadAction;
use Modules\LeadAppointment\Application\Ports\LeadSourceGateway;
use Modules\LeadAppointment\Infrastructure\Persistence\Eloquent\Models\LeadIngestionEvent;
use Throwable;

final class IngestMetaLeadJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 60;

    public function __construct(public string $eventUuid) {}

    public function uniqueId(): string
    {
        return $this->eventUuid;
    }

    public function handle(LeadSourceGateway $gateway, IngestMetaLeadAction $action): void
    {
        $event = DB::transaction(function (): ?LeadIngestionEvent {
            $event = LeadIngestionEvent::query()->where('uuid', $this->eventUuid)->lockForUpdate()->first();

            if ($event === null || $event->status === 'processed') {
                return null;
            }

            $event->status = 'processing';
            $event->attempts++;
            $event->last_error = null;
            $event->save();

            return $event;
        });

        if ($event === null) {
            return;
        }

        try {
            $meta = $gateway->fetch($event->external_id);
            $action->execute($meta);

            $event->status = 'processed';
            $event->processed_at = CarbonImmutable::now();
            $event->save();
        } catch (Throwable $exception) {
            // A duplicate dispatch can lose the race to a successful one; never
            // downgrade a processed event back to failed.
            if ($event->fresh()?->status === 'processed') {
                return;
            }

            $event->status = 'failed';
            $event->last_error = $this->safeError($exception);
            $event->save();

            throw $exception;
        }
    }

    /**
     * `last_error` is a database sink for third-party error text: a Graph error
     * can echo the request URL and its access token. Redact before persisting.
     */
    private function safeError(Throwable $exception): string
    {
        $message = (string) preg_replace(
            '/((?:access_token|key|token|secret|password)=)[^&\s]+/i',
            '$1[REDACTED]',
            $exception->getMessage(),
        );

        return Str::limit($message, 1000);
    }
}
