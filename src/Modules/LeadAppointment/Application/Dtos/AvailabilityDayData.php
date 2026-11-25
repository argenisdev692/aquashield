<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class AvailabilityDayData extends Data
{
    /**
     * @param list<array{start: string, end: string}> $windows
     */
    public function __construct(
        public string $date,
        public bool $isOpen,
        public string $source,
        public ?string $reason,
        public array $windows,
    ) {}
}
