<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class LeadCaptured implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $leadUuid,
        public bool $isEmergency = false,
    ) {}
}
