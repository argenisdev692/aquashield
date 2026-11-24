<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Domain\ValueObjects;

final readonly class DuplicateMatch
{
    public function __construct(
        public string $leadUuid,
        public string $reason,
    ) {}
}
