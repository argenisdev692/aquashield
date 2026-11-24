<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Modules\LeadAppointment\Domain\Enums\Region;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
final class SaveTechnicianData extends Data
{
    public function __construct(
        public string $name,
        public Region $region,
        public ?string $userUuid = null,
        public ?string $phone = null,
        public ?string $email = null,
        public bool $isActive = true,
        public int $dailyCapacity = 4,
        public int $travelBufferMinutes = 30,
    ) {}
}
