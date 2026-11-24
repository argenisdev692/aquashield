<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class LeadBoardColumnData extends Data
{
    /**
     * @param list<LeadCardData> $leads
     */
    public function __construct(
        public string $status,
        public string $label,
        public int $total,
        public array $leads,
    ) {}
}
