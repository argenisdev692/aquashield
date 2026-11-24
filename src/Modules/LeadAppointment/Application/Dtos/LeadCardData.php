<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Compact card payload for the pipeline board. The board is server-grouped:
 * it receives columns, so the client never filters or sorts a stage.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class LeadCardData extends Data
{
    public function __construct(
        public string $uuid,
        public string $fullName,
        public ?string $phone,
        public ?string $city,
        public ?string $state,
        public ?string $region,
        public string $source,
        public string $status,
        public bool $isEmergency,
        public bool $isSpam,
        public ?string $lastContactedAt,
        public ?string $inspectionAt,
        public ?string $createdAt,
    ) {}
}
