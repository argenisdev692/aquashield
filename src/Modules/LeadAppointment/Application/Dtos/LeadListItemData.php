<?php

declare(strict_types=1);

namespace Modules\LeadAppointment\Application\Dtos;

use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapOutputName(SnakeCaseMapper::class)]
final class LeadListItemData extends Data
{
    public function __construct(
        public string $uuid,
        public string $firstName,
        public string $lastName,
        public string $fullName,
        public ?string $email,
        public ?string $phone,
        public ?string $city,
        public ?string $state,
        public ?string $region,
        public string $source,
        public string $status,
        public ?string $ownerName,
        public bool $isEmergency,
        public bool $isSpam,
        public ?string $lastContactedAt,
        public ?string $createdAt,
        public ?string $deletedAt,
    ) {}
}
