<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Ports;

use Modules\LeadAppointment\Application\Dtos\MetaLeadData;
use Modules\LeadAppointment\Domain\Exceptions\MetaLeadFetchException;

interface LeadSourceGateway
{
    /**
     * Verify, persist and enqueue the Meta webhook payload. The controller has
     * already checked the HMAC; this method owns idempotency (`leadgen_id`).
     *
     * @param array<string, mixed> $payload
     */
    public function registerWebhook(array $payload): void;

    /**
     * @throws MetaLeadFetchException
     */
    public function fetch(string $externalId): MetaLeadData;
}
