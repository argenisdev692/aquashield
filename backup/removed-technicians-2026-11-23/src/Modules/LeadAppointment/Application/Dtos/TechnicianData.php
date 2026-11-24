<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class TechnicianData extends Data
{
    public function __construct(
        public string $uuid,
        public ?string $userUuid,
        public string $name,
        public string $region,
        public ?string $phone,
        public ?string $email,
        public bool $isActive,
        public int $dailyCapacity,
        public int $travelBufferMinutes,
    ) {}
}
