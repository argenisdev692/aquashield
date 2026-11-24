<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Modules\LeadAppointment\Domain\Enums\LeadActivityType;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class LogLeadActivityData extends Data
{
    public function __construct(
        public LeadActivityType $type,
        public ?string $outcome = null,
        public ?string $notes = null,
        public ?string $occurredAt = null,
    ) {}
}
