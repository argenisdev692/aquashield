<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Modules\LeadAppointment\Domain\Enums\LeadStatus;

final readonly class LeadStatusChanged implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $leadUuid,
        public LeadStatus $from,
        public LeadStatus $to,
    ) {}
}
